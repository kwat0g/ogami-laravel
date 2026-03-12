---
description: "Run, verify, or export depreciation for a given fiscal period. Covers the assets:depreciate-monthly command, depreciation entry records, and the CSV export endpoint."
---
# Run & Verify Monthly Depreciation

## What This Prompt Does

Guides you through running depreciation for a fiscal period, verifying the results, and exporting the depreciation report. Use it when closing a period, investigating a wrong book value, or debugging a missing JE.

---

## Step 1 — Find the Target Fiscal Period

```bash
# List open fiscal periods to find the period ID
php artisan tinker --no-interaction <<'EOF'
use App\Domains\Accounting\Models\FiscalPeriod;
FiscalPeriod::where('status', 'open')->orderByDesc('start_date')->get(['id','name','start_date','end_date','status'])->each(fn($p) => dump($p->toArray()));
EOF
```

---

## Step 2 — Run Depreciation (Idempotent)

```bash
# Use the specific period ID found above
php artisan assets:depreciate-monthly --period-id=<PERIOD_ID> --actor-id=1
```

The command is **safe to re-run**: the unique constraint on `(fixed_asset_id, fiscal_period_id)` skips already-processed assets. The exit message reports how many assets were depreciated this run (0 = already done).

---

## Step 3 — Verify Results in Tinker

```php
// How many entries were created for this period?
use App\Domains\FixedAssets\Models\AssetDepreciationEntry;

$entries = AssetDepreciationEntry::where('fiscal_period_id', <PERIOD_ID>)
    ->with('fixedAsset:id,asset_code,name,accumulated_depreciation_centavos,status')
    ->get();

$entries->each(fn($e) => dump([
    'asset'  => $e->fixedAsset->asset_code,
    'method' => $e->method,
    'amount' => $e->depreciation_amount_centavos / 100,   // convert to pesos
    'has_je' => $e->journal_entry_id !== null,
]));

// Total depreciation this period (in pesos)
echo 'Total: ₱' . number_format($entries->sum('depreciation_amount_centavos') / 100, 2);
```

---

## Step 4 — Check for Assets NOT Depreciated

Assets skipped because `depreciableAmount <= 0` are auto-marked `fully_depreciated`. Check for any that look wrong:

```php
use App\Domains\FixedAssets\Models\FixedAsset;

// Assets still active but with no entry for this period
$processedIds = \App\Domains\FixedAssets\Models\AssetDepreciationEntry
    ::where('fiscal_period_id', <PERIOD_ID>)->pluck('fixed_asset_id');

FixedAsset::where('status', 'active')
    ->whereNotIn('id', $processedIds)
    ->get(['id','asset_code','name','acquisition_cost_centavos','accumulated_depreciation_centavos','residual_value_centavos'])
    ->each(fn($a) => dump([
        'asset'       => $a->asset_code,
        'book_value'  => $a->bookValueCentavos() / 100,
        'depreciable' => $a->depreciableAmountCentavos() / 100,
    ]));
```

---

## Step 5 — Check GL Journal Entries Were Posted

```php
// See which entries have a JE (category had all 3 GL accounts)
use App\Domains\FixedAssets\Models\AssetDepreciationEntry;

$withJe    = AssetDepreciationEntry::where('fiscal_period_id', <PERIOD_ID>)->whereNotNull('journal_entry_id')->count();
$withoutJe = AssetDepreciationEntry::where('fiscal_period_id', <PERIOD_ID>)->whereNull('journal_entry_id')->count();

echo "With JE: {$withJe}  |  Without JE (no GL accounts on category): {$withoutJe}";
```

Assets without a JE are those whose **category is missing at least one GL account** (`gl_asset_account_id`, `gl_depreciation_expense_account_id`, or `gl_accumulated_depreciation_account_id`). This is not an error — configure the category if GL posting is desired.

---

## Step 6 — Export the Depreciation Schedule

```bash
# Download CSV via the API (requires auth cookie in browser, or use curl with session)
curl -s -b "<session_cookie>" \
  "http://localhost:8000/api/v1/fixed-assets/depreciation-export" \
  -o depreciation_period_<PERIOD_ID>.csv
```

> **Known bug**: The export closure queries `asset_depreciation_entries` (old table name). The correct table is `fixed_asset_depreciation_entries`. If the export returns empty, verify the table name in `routes/api/v1/fixed_assets.php`.

---

## Common Issues & Fixes

| Symptom | Cause | Fix |
|---------|-------|-----|
| Command reports 0 assets | Period already fully processed | Normal — idempotent. Check entries in DB. |
| Asset still `active` after full depreciation | `depreciableAmount > 0` due to rounding | Check `accumulated_depreciation_centavos` vs `(cost - residual)` |
| No JE created | Category missing GL accounts | Set all 3 GL account IDs on the category |
| Wrong depreciation amount | Residual value or useful_life_years wrong on asset | Update those fields on the asset (not the entry) and recompute |
| `assets:depreciate-monthly` fails | No open fiscal period found | Create/open a fiscal period first, then run with `--period-id` |
