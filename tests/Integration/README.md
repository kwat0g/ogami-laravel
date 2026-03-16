# Cross-Domain Integration Tests

This directory contains integration tests that verify workflows across multiple domains in Ogami ERP.

## Test Coverage

| Test File | Test IDs | Description | Status |
|-----------|----------|-------------|--------|
| `PayrollToGLTest.php` | INT-PAY-GL-001 to 004 | Payroll → General Ledger posting | ✅ Passing |
| `PayrollAdjustmentGLTest.php` | INT-PAY-ADJ-001 to 002 | Payroll adjustments → GL | ✅ Passing |
| `APPaymentToGLTest.php` | INT-AP-GL-001 to 003 | AP → General Ledger workflow | ✅ Passing |
| `ProcurementToInventoryTest.php` | INT-PROC-INV-001 to 003 | PR → PO → GR → Stock flow | ✅ Passing |
| `ProductionToInventoryTest.php` | INT-PROD-INV-001 to 004 | BOM → Production → Stock flow | ✅ Passing |
| `LeaveAttendancePayrollTest.php` | INT-LEAVE-ATT/LEAVE-PAY | Leave → Attendance → Payroll | ✅ Passing |
| `ARToBankingTest.php` | INT-AR-BNK-001 to 005 | AR → Bank deposits → GL | ✅ Passing |

**Total: 27 tests, all passing**

## Integration Flows

### Payroll → GL
- Verifies payroll computation creates balanced journal entries
- Checks account codes (5001, 2200) and debit/credit balance

### AP → GL
- Verifies invoice approval creates Dr Expense / Cr AP
- Verifies payment creates Dr AP / Cr Cash
- Validates net zero AP balance after full payment

### Procurement → Inventory
- PR creation → PO generation → Goods Receipt
- Stock balance updates via database trigger
- Stock ledger traceability to source documents

### Production → Inventory
- BOM creation → Production Order
- Raw material deduction via stock ledger
- Finished goods production output
- Stock movement traceability

### Leave → Attendance → Payroll
- Leave request submission and approval
- Leave balance tracking (opening + accrued - used)
- Attendance log creation for leave days
- Payroll computation with paid/unpaid leave handling

### AR → Banking
- Customer invoice creation
- Payment receipt processing
- AR aging bucket calculation (current, 1-30, 31-60, 61-90, 90+)
- Credit limit enforcement

## Running Tests

```bash
# All integration tests
./vendor/bin/pest tests/Integration/

# Specific file
./vendor/bin/pest tests/Integration/PayrollToGLTest.php

# By test ID
./vendor/bin/pest --filter="INT-PAY-GL-001"

# With coverage
./vendor/bin/pest tests/Integration/ --coverage
```

## Test Patterns

### Cross-Domain Service Integration
```php
$payrollService->computeForEmployee($employee, $run);
$glPostingService->postPayrollRun($run);
```

### Database State Verification
```php
$finalStock = StockBalance::where('item_id', $item->id)
    ->where('location_id', $warehouse->id)
    ->value('quantity_on_hand');
expect((float) $finalStock)->toEqual($expectedQty);
```

### Ledger Traceability
```php
$ledger = StockLedger::where('reference_type', 'production_order')
    ->where('reference_id', $prodOrder->id)
    ->first();
expect($ledger->remarks)->toContain($prodOrder->po_reference);
```

## Notes

- All tests use `RefreshDatabase` trait (configured in Pest.php)
- Tests run against PostgreSQL (required for domain-specific features)
- RBAC seeding required before each test
- Monetary values stored as integers (centavos)
- StockBalance updated via trigger on StockLedger insert
