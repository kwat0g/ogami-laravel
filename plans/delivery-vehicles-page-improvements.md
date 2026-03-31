# Delivery Vehicles Page Improvements

## Context

The "Fleet Management" page was created in task `019d42d6` as a quick vehicle CRUD under `/delivery/fleet`. It works but has several issues: confusing naming, DB constraint mismatches that will cause insert failures, missing UX features, and a stale form data bug.

## Critical Bugs to Fix First

### 1. Vehicle Type Mismatch -- Will Cause Insert Failures

The DB CHECK constraint in [`2026_03_05_000018_create_vehicles_table.php:26`](database/migrations/2026_03_05_000018_create_vehicles_table.php:26) allows:
```
truck, van, motorcycle, other
```

But [`FleetPage.tsx:30`](frontend/src/pages/delivery/FleetPage.tsx:30) offers:
```
Truck, Van, Pickup, Motorcycle, Trailer, Other
```

**Problems:**
- "Pickup" and "Trailer" are not in the DB constraint -- inserts will fail with a CHECK violation
- Values are Title Case in frontend but lowercase in DB -- the backend validation at [`delivery.php:33`](routes/api/v1/delivery.php:33) accepts any string up to 50 chars, so Title Case values get stored and may not match the CHECK

**Fix:** Align frontend types to `truck, van, motorcycle, other` using lowercase. Remove Pickup and Trailer options, or add a migration to expand the CHECK constraint.

### 2. Status Mismatch -- Will Cause Update Failures

The DB CHECK constraint at [`2026_03_05_000018_create_vehicles_table.php:27`](database/migrations/2026_03_05_000018_create_vehicles_table.php:27) allows:
```
active, inactive, maintenance
```

But [`FleetPage.tsx:24-28`](frontend/src/pages/delivery/FleetPage.tsx:24) and the backend validation at [`delivery.php:36`](routes/api/v1/delivery.php:36) use:
```
active, maintenance, decommissioned
```

**Problem:** "decommissioned" will fail the CHECK constraint. "inactive" exists in DB but is not offered in the UI.

**Fix:** Either update the DB CHECK constraint to include `decommissioned`, or change the frontend/backend to use `inactive` instead. Recommendation: add a migration to change the CHECK to `active, inactive, maintenance, decommissioned` since decommissioned is more descriptive.

### 3. Stale Form Data Bug

[`VehicleFormModal`](frontend/src/pages/delivery/FleetPage.tsx:32) initializes state from props in `useState` defaults, but these only run on first mount. When clicking Edit on Vehicle A then Edit on Vehicle B without closing the modal, the form shows Vehicle A's data.

**Fix:** Add a `useEffect` or use `key={vehicle?.id}` on the modal to force re-mount when the vehicle changes.

### 4. Backend Validation Mismatch

The backend route at [`delivery.php:33`](routes/api/v1/delivery.php:33) validates type as `string, max:50` -- it does not enforce the CHECK constraint values. Same for status at [`delivery.php:36`](routes/api/v1/delivery.php:36) which validates `in:active,maintenance,decommissioned` but the DB only allows `active,inactive,maintenance`.

**Fix:** Update the `in:` validation rule to match whatever the final DB CHECK constraint allows.

## Naming Changes

### 5. Rename "Fleet" to "Delivery Vehicles"

Update these locations:

| File | Change |
|------|--------|
| [`AppLayout.tsx:247`](frontend/src/components/layout/AppLayout.tsx:247) | Sidebar label: "Fleet" to "Delivery Vehicles" |
| [`AppLayout.tsx:247`](frontend/src/components/layout/AppLayout.tsx:247) | Sidebar href: `/delivery/fleet` to `/delivery/vehicles` |
| [`router/index.tsx:252`](frontend/src/router/index.tsx:252) | Import name: `FleetPage` to `DeliveryVehiclesPage` |
| [`router/index.tsx:639`](frontend/src/router/index.tsx:639) | Route path: `/delivery/fleet` to `/delivery/vehicles` |
| [`FleetPage.tsx`](frontend/src/pages/delivery/FleetPage.tsx) | Rename file to `DeliveryVehiclesPage.tsx` |
| [`FleetPage.tsx:213`](frontend/src/pages/delivery/FleetPage.tsx:213) | Page title: "Fleet Management" to "Delivery Vehicles" |
| [`DeliveryReceiptDetailPage.tsx:107`](frontend/src/pages/delivery/DeliveryReceiptDetailPage.tsx:107) | Warning text: "Add vehicles in Fleet Management" to "Add vehicles in Delivery Vehicles" + make it a link to `/delivery/vehicles` |

## UX Improvements

### 6. Add Status Filter Tabs

Add a row of filter tabs above the table: All / Active / Maintenance / Inactive / Decommissioned. Each tab filters the vehicle list. Show count badges on each tab.

### 7. Add Search Input

Add a search input in the header area that filters vehicles by name, code, or plate number. Client-side filtering is fine since the vehicle list is small.

### 8. Quick Status Actions from Table Row

Replace the plain "Edit" link with a dropdown or action buttons:
- Edit -- opens the modal
- Mark Inactive / Mark Active -- quick status toggle
- Mark for Maintenance -- quick toggle

This avoids having to open the full edit modal just to change status.

### 9. Link Warning in Prepare Shipment Modal

At [`DeliveryReceiptDetailPage.tsx:107`](frontend/src/pages/delivery/DeliveryReceiptDetailPage.tsx:107), the warning "No active vehicles found. Add vehicles in Fleet Management." should be a clickable link to `/delivery/vehicles`.

## Optional Enhancements -- Lower Priority

### 10. Capacity/Payload Field

The DB schema does not have a capacity column. This would require a migration to add `capacity_kg DECIMAL(10,2)` or similar. Nice to have for logistics planning but not critical.

## Files to Modify

| File | Type of Change |
|------|---------------|
| `frontend/src/pages/delivery/FleetPage.tsx` | Rename file, fix types/statuses, add filters, search, stale form bug |
| `frontend/src/components/layout/AppLayout.tsx` | Rename sidebar label and href |
| `frontend/src/router/index.tsx` | Rename import, update route path |
| `frontend/src/pages/delivery/DeliveryReceiptDetailPage.tsx` | Update warning text + add link |
| `routes/api/v1/delivery.php` | Fix validation rules to match DB |
| New migration file | Expand CHECK constraints for type and status |

## Implementation Order

1. Fix critical bugs first: type mismatch, status mismatch, backend validation
2. Add migration to expand CHECK constraints
3. Rename everything from Fleet to Delivery Vehicles
4. Fix stale form data bug
5. Add status filter tabs
6. Add search input
7. Add quick status actions
8. Link the Prepare Shipment warning
