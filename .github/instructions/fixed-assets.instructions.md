---
description: "Use when writing or editing PHP or TypeScript files in the FixedAssets domain. Covers asset registration, depreciation computation, disposal, GL posting, and the Artisan command."
applyTo: "app/Domains/FixedAssets/**,app/Http/Controllers/FixedAssets/**,app/Http/Requests/FixedAssets/**,routes/api/v1/fixed_assets.php,frontend/src/hooks/useFixedAssets.ts,frontend/src/types/fixed_assets.ts"
---
# Ogami ERP — Fixed Assets Domain Guidelines

## Models Overview

| Model | Table | Notes |
|-------|-------|-------|
| `FixedAssetCategory` | `fixed_asset_categories` | Defines GL accounts for auto-JE posting |
| `FixedAsset` | `fixed_assets` | `asset_code` filled by PG trigger — never set manually |
| `AssetDepreciationEntry` | `fixed_asset_depreciation_entries` | `$timestamps = false`; unique on `(fixed_asset_id, fiscal_period_id)` |
| `AssetDisposal` | `fixed_asset_disposals` | `gain_loss_centavos` is signed: positive = gain, negative = loss |

## Critical Asset Code Rule

`asset_code` is populated by a **PostgreSQL trigger** (`fn_fixed_asset_code`) on INSERT.
- Pattern: `{code_prefix}-YYYY-NNNN` (e.g. `VEH-2025-0001`)
- **Never set `asset_code` in PHP code, factories, or tests.** Set it to `''` (empty string) or omit it entirely — the trigger fires when the value is `NULL` or empty.

## Status Values & Transitions

```
active --> fully_depreciated  (auto, when accumulated >= cost - residual)
active --> disposed           (via dispose())
active --> impaired           (manual update only)
```

Valid `status` values (CHECK constraint): `active`, `fully_depreciated`, `disposed`, `impaired`.

**Note**: The frontend TypeScript type uses `under_maintenance` instead of `impaired` — this is a known type divergence. The DB CHECK constraint is authoritative; do not add `under_maintenance` to the database.

## Depreciation Methods

| Method | Formula |
|--------|---------|
| `straight_line` | `(cost − residual) / (useful_life_years × 12)` per month, capped at depreciable balance |
| `double_declining` | `(2 / useful_life_years) × bookValue / 12` per month, capped at `bookValue − residual` |
| `units_of_production` | Not yet implemented in the service; reserved for future use |

## GL Posting Rules

Depreciation and disposal **only post a JournalEntry if all 3 GL accounts are set on the category**:
- `gl_asset_account_id`
- `gl_depreciation_expense_account_id`
- `gl_accumulated_depreciation_account_id`

If any is `null`, the `AssetDepreciationEntry` (or `AssetDisposal`) is still created, but **no JE is posted**. Never throw an error for missing GL accounts — silently skip the posting step.

## Depreciation Idempotency

The unique constraint `uq_fa_dep_period` on `(fixed_asset_id, fiscal_period_id)` prevents double-processing. `depreciateMonth()` skips any asset already present in `fixed_asset_depreciation_entries` for the given period. The Artisan command is therefore safe to re-run.

## Service Method Signatures

```php
// Register a new asset (asset_code is auto-set by trigger)
$service->register(array $data, User $actor): FixedAsset

// Depreciate all active assets for a fiscal period; returns count of assets processed
$service->depreciateMonth(FiscalPeriod $period, User $actor): int

// Dispose an asset; throws DomainException('FA_ALREADY_DISPOSED', 422) if already disposed
$service->dispose(FixedAsset $asset, array $data, User $actor): AssetDisposal

// Store a new category
$service->storeCategory(array $data, User $actor): FixedAssetCategory
```

## Artisan Command

```bash
php artisan assets:depreciate-monthly
    [--period-id=<fiscal_period_id>]   # omit to use latest open period
    [--actor-id=<user_id>]             # defaults to user 1
```

Idempotent — re-running for the same period is safe. Errors per asset are logged but do **not** abort the batch.

## Permissions

| Permission | Roles |
|-----------|-------|
| `fixed_assets.view` | All authenticated roles |
| `fixed_assets.manage` | `admin`, `executive`, `vice_president`, `manager` |
| `fixed_assets.depreciate` (policy guard) | `fixed_assets.manage` AND role in `[admin, super_admin, executive, vice_president]` |

## Disposal JE Line Structure

When all GL accounts are configured, the disposal JE contains:
1. **Dr** Accumulated Depreciation — removes accumulated dep
2. **Dr** Cash/Receivable (if `proceeds_centavos > 0`) — records proceeds
3. **Cr** Asset Account — removes original cost
4. **Cr** Gain on Disposal (if gain) or **Dr** Loss on Disposal (if loss)

`gain_loss_centavos = proceeds_centavos - bookValueCentavos` — positive means gain, negative means loss.

## Computed Model Properties

```php
$asset->bookValueCentavos()         // max(0, acquisition_cost - accumulated_depreciation)
$asset->depreciableAmountCentavos() // max(0, cost - residual - accumulated)
```

These are PHP methods, **not** database columns. Do not reference them in `orderBy()` or `where()` queries.

## Route Reference

| Method | URI | Action |
|--------|-----|--------|
| GET | `/api/v1/fixed-assets/categories` | `indexCategories` |
| POST | `/api/v1/fixed-assets/categories` | `storeCategory` |
| GET | `/api/v1/fixed-assets` | `index` (paginated, filters: `status`, `category_id`) |
| POST | `/api/v1/fixed-assets` | `store` |
| GET | `/api/v1/fixed-assets/{fixedAsset}` | `show` |
| PUT | `/api/v1/fixed-assets/{fixedAsset}` | `update` (blocked if `status === 'disposed'`) |
| POST | `/api/v1/fixed-assets/depreciate` | `depreciatePeriod` — body: `{fiscal_period_id}` |
| POST | `/api/v1/fixed-assets/{fixedAsset}/dispose` | `dispose` |
| GET | `/api/v1/fixed-assets/depreciation-export` | CSV stream |

Route parameter `{fixedAsset}` resolves via ULID (uses `HasPublicUlid`).

## Known Bug

The inline CSV export closure at `/depreciation-export` queries the table `asset_depreciation_entries` (old name). The correct table name is `fixed_asset_depreciation_entries`. Do not replicate this table name in new code.

## Frontend Hook Reference (`useFixedAssets.ts`)

```typescript
useFixedAssetCategories()              // staleTime: 120 000 ms
useCreateFixedAssetCategory()
useFixedAssets(params)                 // staleTime: 30 000 ms; params: {category_id, status, search, page, per_page}
useFixedAsset(ulid: string | null)     // enabled only when ulid !== null
useCreateFixedAsset()
useUpdateFixedAsset(ulid: string)
useDepreciatePeriod()                  // POST /fixed-assets/depreciate
useDisposeAsset(ulid: string)          // POST /fixed-assets/:ulid/dispose
```
