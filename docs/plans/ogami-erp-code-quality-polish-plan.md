# Ogami ERP -- Code Quality & Polish Plan

## Phase 1: Fix Pre-existing TypeScript Errors (Priority: Critical)

### 1.1 ConfirmDestructiveDialog Controlled Mode
- Add `open`/`onClose`/`loading` optional props (same pattern as ConfirmDialog fix)
- Fixes: ProductionOrderDetailPage, DeliveryReceiptDetailPage

### 1.2 Fix LeaveRequest Type
- Add missing fields to `LeaveRequest` type in `frontend/src/types/hr.ts`:
  `reviewed_by`, `reviewed_at`, `reviewer_remarks`, `head_approved_by_name`, etc.

### 1.3 Fix VendorInvoiceStatus Type
- Add missing statuses to `VendorInvoiceStatus` in `frontend/src/types/ap.ts`:
  `head_noted`, `manager_checked`, `officer_reviewed`, `rejected`
- Update `STATUS_COLORS` and `STATUS_LABEL` in APInvoicesPage

### 1.4 Fix ClientOrderDetailPage
- Add proper type for `order.items.map()` parameter

### 1.5 Fix CustomerInvoicesPage StatusBadge
- Fix children prop usage to match StatusBadge interface

## Phase 2: Type Safety Improvements (Priority: High)

### 2.1 Complete LeaveRequest Type
- Audit backend LeaveRequestResource to get all returned fields
- Update frontend type to match

### 2.2 Complete VendorInvoice Type  
- Add all workflow step fields (head_noted_by, manager_checked_by, etc.)

### 2.3 Fix PurchaseRequestDetailPage
- Fix `firstErrorMessage` 2-arg calls (already fixed in lib, pages should work now)
- Fix `returned_by`/`return_reason` missing from PurchaseRequest type

## Phase 3: Component Consolidation (Priority: Medium)

### 3.1 Remove duplicate InfoRow from pages
- Pages that define their own InfoRow: ProductionOrderDetailPage, others
- Replace with import from `@/components/ui/InfoRow`

## Phase 4: Frontend Tests (Priority: Medium)

### 4.1 ExportButton unit test
### 4.2 StatusTimeline unit test
### 4.3 useAutoSave unit test

## Implementation Order
1. ConfirmDestructiveDialog fix (unblocks multiple pages)
2. Type definition fixes (LeaveRequest, VendorInvoice, PurchaseRequest)
3. APInvoicesPage STATUS_COLORS fix
4. Component consolidation
5. Frontend tests
