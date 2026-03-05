# Ogami ERP — New Modules Summary Report

**Generated:** 2026-01-01  
**Sprints Covered:** A (Inventory), B (Production/PPC), C (QC/QA), D (Maintenance + Mold), E (Delivery/Logistics), F (ISO/IATF)

---

## Table of Contents

1. [Overview](#overview)
2. [Module Catalogue](#module-catalogue)
3. [Permissions Matrix](#permissions-matrix)
4. [API Endpoints](#api-endpoints)
5. [Database Migrations](#database-migrations)
6. [Frontend Pages & Routes](#frontend-pages--routes)
7. [Architecture Notes](#architecture-notes)
8. [Test Data (NewModulesSeeder)](#test-data-newmodulesseeder)
9. [Bug Fixes Applied](#bug-fixes-applied)

---

## Overview

Seven new functional modules were implemented across Sprints A–F, extending the core Ogami ERP (HR/Payroll/Accounting) with manufacturing and compliance capabilities:

| Sprint | Module(s) | Key Entities |
|--------|-----------|--------------|
| A | **Inventory** | Item Masters, Warehouse Locations, Stock Balances, Material Requisitions |
| B | **Production / PPC** | Bills of Materials, Production Orders, Delivery Schedules |
| C | **QC / QA** | Inspection Templates, Inspections, Non-Conformance Reports (NCRs), CAPAs |
| D | **Maintenance** | Equipment Master, Maintenance Work Orders, PM Schedules |
| D | **Mold** | Mold Masters, Mold Shot Logs |
| E | **Delivery / Logistics** | Delivery Receipts, Delivery Receipt Items, Shipments, Import/Export Documents |
| F | **ISO / IATF** | Controlled Documents, Document Revisions, Internal Audits, Audit Findings, Improvement Actions |

---

## Module Catalogue

### Sprint A — Inventory Management

| Item | Description |
|------|-------------|
| **Purpose** | Track raw materials, WIP, and finished goods across warehouse locations |
| **Models** | `ItemCategory`, `ItemMaster`, `WarehouseLocation`, `StockBalance`, `StockLedger`, `LotBatch`, `MaterialRequisition`, `MaterialRequisitionItem` |
| **Service** | `InventoryService` — item CRUD, stock adjustments, MRQ 7-step approval workflow |
| **Policy** | `ItemMasterPolicy`, `MaterialRequisitionPolicy` |
| **Key Features** | ULID-keyed item masters, multi-step MRQ workflow (submit → check → review → VP-approve → fulfill), stock ledger audit trail, low-stock query, lot/batch traceability |
| **Reference Numbers** | `MRQ-YYYY-MM-NNNNN` (PostgreSQL sequence + BEFORE INSERT trigger) |

### Sprint B — Production / PPC

| Item | Description |
|------|-------------|
| **Purpose** | Manage production planning, bill of materials, and production orders |
| **Models** | `BillOfMaterials`, `BomItem`, `ProductionOrder`, `ProductionOutput`, `DeliverySchedule` |
| **Service** | `ProductionService` — BOM CRUD, order lifecycle (release → start → complete/cancel), output logging |
| **Policy** | `ProductionOrderPolicy` |
| **Key Features** | Multi-level BOM with quantity and UoM, 5-state order machine, output logging per order, delivery schedule planning |
| **Reference Numbers** | `PRD-YYYY-MM-NNNNN` (sequence + trigger) |

### Sprint C — Quality Control / QA

| Item | Description |
|------|-------------|
| **Purpose** | Incoming/in-process/outgoing inspections, non-conformance management, CAPA tracking |
| **Models** | `InspectionTemplate`, `InspectionTemplateItem`, `Inspection`, `InspectionResult`, `NonConformanceReport`, `CapaAction` |
| **Service** | `QCService` — template CRUD, inspection lifecycle, NCR raise/CAPA/close |
| **Policy** | `QCPolicy` |
| **Key Features** | Configurable inspection templates (pass/fail + measured), result recording, NCR with severity grading, CAPA assignments |
| **Reference Numbers** | `NCR-YYYY-MM-NNNNN` (sequence + trigger) |

### Sprint D — Maintenance Management

| Item | Description |
|------|-------------|
| **Purpose** | Track equipment health, corrective/preventive work orders, and PM schedules |
| **Models** | `Equipment`, `MaintenanceWorkOrder`, `PmSchedule` |
| **Service** | `MaintenanceService` — equipment CRUD, work order lifecycle (open → in_progress → completed), PM schedule management |
| **Policy** | `MaintenancePolicy` |
| **Key Features** | Equipment categories (production/utility), priority levels (low/medium/high/critical), PM schedule with `next_due_on` computed column, type-based work orders (corrective/preventive/predictive) |
| **Reference Numbers** | `EQ-NNNNN`, `WO-YYYY-MM-NNNNN` (sequences + triggers) |

### Sprint D — Mold Management

| Item | Description |
|------|-------------|
| **Purpose** | Track injection moulding tools, shot counts, and maintenance cycles |
| **Models** | `MoldMaster`, `MoldShotLog` |
| **Service** | `MoldService` — mold CRUD, shot logging with cumulative update |
| **Policy** | `MoldPolicy` |
| **Key Features** | Shot counter with max_shots capacity, `isCritical()` helper (≥ 90% shots used), shot logs linked to production orders, auto-trigger updates `current_shots` on insert |
| **Reference Numbers** | `MLD-NNNNN` (sequence + trigger) |

### Sprint E — Delivery / Logistics

| Item | Description |
|------|-------------|
| **Purpose** | Record goods receipts / dispatches, link to vendors/customers, track shipments |
| **Models** | `DeliveryReceipt`, `DeliveryReceiptItem`, `Shipment`, `ImpexDocument` |
| **Service** | `DeliveryService` — receipt CRUD, confirm receipt, shipment CRUD |
| **Policy** | `DeliveryPolicy` |
| **Key Features** | Bi-directional (inbound/outbound), line-item quantity tracking, shipment with carrier + tracking number, status machine (draft → confirmed), importable/exportable document archiving |
| **Reference Numbers** | `DR-YYYY-MM-NNNNN`, `SHIP-YYYY-MM-NNNNN` (sequences + triggers) |

### Sprint F — ISO / IATF Compliance

| Item | Description |
|------|-------------|
| **Purpose** | Maintain controlled document register, internal audit scheduling, finding and improvement action tracking |
| **Models** | `ControlledDocument`, `DocumentRevision`, `InternalAudit`, `AuditFinding`, `ImprovementAction` |
| **Service** | `ISOService` — document CRUD, audit lifecycle (planned → in_progress → completed), finding management |
| **Policy** | `ISOPolicy` |
| **Key Features** | Versioned documents with review dates, multi-standard support (ISO 9001, IATF 16949, etc.), finding types (non_conformance/observation/opportunity), severity grading, linked improvement actions |
| **Reference Numbers** | `DOC-NNNNN`, `AUDIT-YYYY-MM-NNNNN` (sequences + triggers) |

---

## Permissions Matrix

### New Permissions by Module

| Permission | Description |
|------------|-------------|
| `inventory.items.view` | View item master catalogue |
| `inventory.items.create` | Create new item masters |
| `inventory.items.edit` | Edit existing item records |
| `inventory.locations.view` | View warehouse locations |
| `inventory.locations.manage` | Create/edit warehouse locations |
| `inventory.stock.view` | View stock balances and ledger |
| `inventory.adjustments.create` | Post manual stock adjustments |
| `inventory.mrq.view` | View material requisitions |
| `inventory.mrq.create` | Create and submit MRQs |
| `inventory.mrq.note` | Add notes to MRQs |
| `inventory.mrq.check` | Initial review/check step |
| `inventory.mrq.review` | Department head review step |
| `inventory.mrq.vp_approve` | VP final approval step |
| `inventory.mrq.fulfill` | Mark MRQ as fulfilled |
| `production.bom.view` | View bills of materials |
| `production.bom.manage` | Create/edit BOMs |
| `production.orders.view` | View production orders |
| `production.orders.create` | Create production orders |
| `production.orders.release` | Release orders to production floor |
| `production.orders.log_output` | Record production outputs |
| `production.orders.complete` | Mark orders complete/cancel |
| `production.delivery-schedule.view` | View delivery schedules |
| `production.delivery-schedule.manage` | Create/edit delivery schedules |
| `qc.templates.view` | View inspection templates |
| `qc.templates.manage` | Create/edit inspection templates |
| `qc.inspections.view` | View inspections and results |
| `qc.inspections.create` | Conduct inspections and record results |
| `qc.ncr.view` | View non-conformance reports |
| `qc.ncr.create` | Raise NCRs |
| `qc.ncr.close` | Close NCRs and CAPAs |
| `maintenance.view` | View equipment and work orders |
| `maintenance.manage` | Create/edit equipment, raise work orders, manage PM schedules |
| `mold.view` | View mold masters and shot logs |
| `mold.manage` | Create/edit mold masters |
| `mold.log_shots` | Record shot logs for molds |
| `delivery.view` | View delivery receipts and shipments |
| `delivery.manage` | Create/edit receipts, confirm, manage shipments |
| `iso.view` | View controlled documents and audits |
| `iso.manage` | Create/edit controlled documents |
| `iso.audit` | Create and manage internal audits and findings |

### Suggested Role Assignments

| Role | Suggested New Permissions |
|------|--------------------------|
| `admin` | All permissions |
| `vice_president` | `*.view`, `inventory.mrq.vp_approve`, `production.orders.complete` |
| `manager` | `*.view`, `*.manage`, `inventory.mrq.review`, `qc.ncr.close`, `iso.manage` |
| `head` | `*.view`, `inventory.mrq.check`, `production.orders.release`, `qc.inspections.create`, `qc.ncr.create`, `maintenance.manage`, `mold.manage`, `delivery.manage`, `iso.audit` |
| `officer` | `inventory.items.view`, `inventory.stock.view`, `inventory.mrq.create`, `production.orders.view`, `qc.inspections.create`, `maintenance.view`, `mold.log_shots`, `delivery.view`, `iso.view` |
| `staff` | `inventory.items.view`, `inventory.stock.view`, `production.orders.view`, `maintenance.view`, `mold.view`, `delivery.view` |

---

## API Endpoints

All routes require authentication (`auth:sanctum`) and live under `/api/v1/`.

### Inventory — `/api/v1/inventory/`

| Method | Path | Permission | Description |
|--------|------|-----------|-------------|
| GET | `/items` | `inventory.items.view` | Paginated item list |
| POST | `/items` | `inventory.items.create` | Create item master |
| GET | `/items/categories` | `inventory.items.view` | List item categories |
| POST | `/items/categories` | `inventory.items.create` | Create item category |
| GET | `/items/low-stock` | `inventory.stock.view` | Items below reorder point |
| GET | `/items/{item}` | `inventory.items.view` | Item detail |
| PUT | `/items/{item}` | `inventory.items.edit` | Update item |
| PATCH | `/items/{item}/toggle-active` | `inventory.items.edit` | Activate/deactivate |
| GET | `/locations` | `inventory.locations.view` | Warehouse location list |
| POST | `/locations` | `inventory.locations.manage` | Create location |
| PUT | `/locations/{warehouseLocation}` | `inventory.locations.manage` | Update location |
| GET | `/stock-balances` | `inventory.stock.view` | Stock on hand per item/location |
| GET | `/stock-ledger` | `inventory.stock.view` | Full stock movement ledger |
| POST | `/adjustments` | `inventory.adjustments.create` | Post stock adjustment |
| GET | `/requisitions` | `inventory.mrq.view` | MRQ list |
| POST | `/requisitions` | `inventory.mrq.create` | Create MRQ |
| GET | `/requisitions/{materialRequisition}` | `inventory.mrq.view` | MRQ detail |
| PATCH | `/requisitions/{materialRequisition}/submit` | `inventory.mrq.create` | Submit for processing |
| PATCH | `/requisitions/{materialRequisition}/note` | `inventory.mrq.note` | Add note |
| PATCH | `/requisitions/{materialRequisition}/check` | `inventory.mrq.check` | Check step |
| PATCH | `/requisitions/{materialRequisition}/review` | `inventory.mrq.review` | Review step |
| PATCH | `/requisitions/{materialRequisition}/vp-approve` | `inventory.mrq.vp_approve` | VP approval |
| PATCH | `/requisitions/{materialRequisition}/fulfill` | `inventory.mrq.fulfill` | Fulfill |
| PATCH | `/requisitions/{materialRequisition}/reject` | `inventory.mrq.review` | Reject |
| PATCH | `/requisitions/{materialRequisition}/cancel` | `inventory.mrq.create` | Cancel |

### Production — `/api/v1/production/`

| Method | Path | Permission | Description |
|--------|------|-----------|-------------|
| GET | `/boms` | `production.bom.view` | BOM list |
| POST | `/boms` | `production.bom.manage` | Create BOM |
| GET | `/boms/{bom}` | `production.bom.view` | BOM detail with items |
| PUT | `/boms/{bom}` | `production.bom.manage` | Update BOM |
| GET | `/orders` | `production.orders.view` | Production order list |
| POST | `/orders` | `production.orders.create` | Create production order |
| GET | `/orders/{productionOrder}` | `production.orders.view` | Order detail |
| PATCH | `/orders/{productionOrder}/release` | `production.orders.release` | Release to floor |
| PATCH | `/orders/{productionOrder}/start` | `production.orders.log_output` | Start production |
| PATCH | `/orders/{productionOrder}/complete` | `production.orders.complete` | Mark complete |
| PATCH | `/orders/{productionOrder}/cancel` | `production.orders.complete` | Cancel order |
| POST | `/orders/{productionOrder}/output` | `production.orders.log_output` | Log output |
| GET | `/delivery-schedules` | `production.delivery-schedule.view` | Delivery schedule list |
| POST | `/delivery-schedules` | `production.delivery-schedule.manage` | Create delivery schedule |
| GET | `/delivery-schedules/{deliverySchedule}` | `production.delivery-schedule.view` | Schedule detail |
| PUT | `/delivery-schedules/{deliverySchedule}` | `production.delivery-schedule.manage` | Update schedule |

### QC / QA — `/api/v1/qc/`

| Method | Path | Permission | Description |
|--------|------|-----------|-------------|
| GET | `/templates` | `qc.templates.view` | Inspection template list |
| POST | `/templates` | `qc.templates.manage` | Create template |
| GET | `/templates/{inspectionTemplate}` | `qc.templates.view` | Template detail |
| PUT | `/templates/{inspectionTemplate}` | `qc.templates.manage` | Update template |
| GET | `/inspections` | `qc.inspections.view` | Inspection list |
| POST | `/inspections` | `qc.inspections.create` | Create inspection |
| GET | `/inspections/{inspection}` | `qc.inspections.view` | Inspection detail |
| PATCH | `/inspections/{inspection}/results` | `qc.inspections.create` | Record results |
| GET | `/ncrs` | `qc.ncr.view` | NCR list |
| POST | `/ncrs` | `qc.ncr.create` | Raise NCR |
| GET | `/ncrs/{nonConformanceReport}` | `qc.ncr.view` | NCR detail |
| PATCH | `/ncrs/{nonConformanceReport}/capa` | `qc.ncr.create` | Assign CAPA |
| PATCH | `/ncrs/{nonConformanceReport}/close` | `qc.ncr.close` | Close NCR |
| PATCH | `/capa/{capaAction}/complete` | `qc.ncr.close` | Complete CAPA action |

### Maintenance — `/api/v1/maintenance/`

| Method | Path | Permission | Description |
|--------|------|-----------|-------------|
| GET | `/equipment` | `maintenance.view` | Equipment list |
| POST | `/equipment` | `maintenance.manage` | Register equipment |
| GET | `/equipment/{equipment}` | `maintenance.view` | Equipment detail |
| PUT | `/equipment/{equipment}` | `maintenance.manage` | Update equipment |
| POST | `/equipment/{equipment}/pm-schedules` | `maintenance.manage` | Add PM schedule |
| GET | `/work-orders` | `maintenance.view` | Work order list |
| POST | `/work-orders` | `maintenance.manage` | Create work order |
| GET | `/work-orders/{maintenanceWorkOrder}` | `maintenance.view` | Work order detail |
| PATCH | `/work-orders/{maintenanceWorkOrder}/start` | `maintenance.manage` | Start work order |
| PATCH | `/work-orders/{maintenanceWorkOrder}/complete` | `maintenance.manage` | Complete work order |

### Mold — `/api/v1/mold/`

| Method | Path | Permission | Description |
|--------|------|-----------|-------------|
| GET | `/molds` | `mold.view` | Mold master list |
| POST | `/molds` | `mold.manage` | Register mold |
| GET | `/molds/{moldMaster}` | `mold.view` | Mold detail + shot history |
| PUT | `/molds/{moldMaster}` | `mold.manage` | Update mold |
| POST | `/molds/{moldMaster}/shots` | `mold.log_shots` | Log shot count |

### Delivery / Logistics — `/api/v1/delivery/`

| Method | Path | Permission | Description |
|--------|------|-----------|-------------|
| GET | `/receipts` | `delivery.view` | Delivery receipt list |
| POST | `/receipts` | `delivery.manage` | Create delivery receipt |
| GET | `/receipts/{deliveryReceipt}` | `delivery.view` | Receipt detail with line items |
| PATCH | `/receipts/{deliveryReceipt}/confirm` | `delivery.manage` | Confirm receipt |
| GET | `/shipments` | `delivery.view` | Shipment list |
| POST | `/shipments` | `delivery.manage` | Create shipment |
| GET | `/shipments/{shipment}` | `delivery.view` | Shipment detail |

### ISO / IATF — `/api/v1/iso/`

| Method | Path | Permission | Description |
|--------|------|-----------|-------------|
| GET | `/documents` | `iso.view` | Controlled document list |
| POST | `/documents` | `iso.manage` | Create document |
| GET | `/documents/{controlledDocument}` | `iso.view` | Document detail |
| PUT | `/documents/{controlledDocument}` | `iso.manage` | Update document |
| GET | `/audits` | `iso.view` | Internal audit list |
| POST | `/audits` | `iso.audit` | Schedule audit |
| GET | `/audits/{internalAudit}` | `iso.view` | Audit detail with findings |
| PATCH | `/audits/{internalAudit}/start` | `iso.audit` | Start audit |
| PATCH | `/audits/{internalAudit}/complete` | `iso.audit` | Complete audit with summary |
| POST | `/audits/{internalAudit}/findings` | `iso.audit` | Raise finding |

---

## Database Migrations

| Migration File | Tables Created |
|----------------|---------------|
| `2026_03_05_000010_create_inventory_tables.php` | `item_categories`, `item_masters`, `warehouse_locations`, `stock_balances`, `stock_ledger`, `lot_batches`, `material_requisitions`, `material_requisition_items` |
| `2026_03_05_000011_create_production_tables.php` | `bills_of_materials`, `bom_items`, `production_orders`, `production_outputs`, `delivery_schedules` |
| `2026_03_05_000012_create_qc_tables.php` | `inspection_templates`, `inspection_template_items`, `inspections`, `inspection_results`, `non_conformance_reports`, `capa_actions` |
| `2026_03_05_000014_create_maintenance_tables.php` | `equipment`, `maintenance_work_orders`, `pm_schedules` |
| `2026_03_05_000015_create_mold_tables.php` | `mold_masters`, `mold_shot_logs` |
| `2026_03_05_000016_create_delivery_tables.php` | `delivery_receipts`, `delivery_receipt_items`, `shipments`, `impex_documents` |
| `2026_03_05_000017_create_iso_tables.php` | `controlled_documents`, `document_revisions`, `internal_audits`, `audit_findings`, `improvement_actions` |

### Auto-Generated Reference Numbers

All reference numbers use PostgreSQL sequences with BEFORE INSERT triggers:

| Sequence / Code Format | Entity |
|------------------------|--------|
| `MRQ-YYYY-MM-NNNNN` | Material Requisitions |
| `PRD-YYYY-MM-NNNNN` | Production Orders |
| `NCR-YYYY-MM-NNNNN` | Non-Conformance Reports |
| `EQ-NNNNN` | Equipment |
| `WO-YYYY-MM-NNNNN` | Maintenance Work Orders |
| `MLD-NNNNN` | Mold Masters |
| `DR-YYYY-MM-NNNNN` | Delivery Receipts |
| `SHIP-YYYY-MM-NNNNN` | Shipments |
| `DOC-NNNNN` | Controlled Documents |
| `AUDIT-YYYY-MM-NNNNN` | Internal Audits |

---

## Frontend Pages & Routes

All pages are lazily loaded via React Router and protected by the `RequirePermission` guard.

### New Frontend Pages

| Route Path | Component | Required Permission |
|-----------|-----------|---------------------|
| `/inventory/items` | `ItemListPage` | `inventory.items.view` |
| `/inventory/items/:id` | `ItemDetailPage` | `inventory.items.view` |
| `/inventory/locations` | `WarehouseLocationPage` | `inventory.locations.view` |
| `/inventory/stock` | `StockBalancePage` | `inventory.stock.view` |
| `/inventory/requisitions` | `MaterialRequisitionListPage` | `inventory.mrq.view` |
| `/inventory/requisitions/:id` | `MaterialRequisitionDetailPage` | `inventory.mrq.view` |
| `/production/boms` | `BOMListPage` | `production.bom.view` |
| `/production/orders` | `ProductionOrderListPage` | `production.orders.view` |
| `/production/orders/:id` | `ProductionOrderDetailPage` | `production.orders.view` |
| `/production/delivery-schedules` | `DeliverySchedulePage` | `production.delivery-schedule.view` |
| `/qc/templates` | `InspectionTemplatePage` | `qc.templates.view` |
| `/qc/inspections` | `InspectionListPage` | `qc.inspections.view` |
| `/qc/ncr` | `NCRListPage` | `qc.ncr.view` |
| `/maintenance/equipment` | `EquipmentListPage` | `maintenance.view` |
| `/maintenance/equipment/:id` | `EquipmentDetailPage` | `maintenance.view` |
| `/maintenance/work-orders` | `WorkOrderListPage` | `maintenance.view` |
| `/mold/molds` | `MoldListPage` | `mold.view` |
| `/mold/molds/:id` | `MoldDetailPage` | `mold.view` |
| `/delivery/receipts` | `DeliveryReceiptListPage` | `delivery.view` |
| `/iso/documents` | `DocumentRegisterPage` | `iso.view` |
| `/iso/audits` | `AuditListPage` | `iso.view` |

### Navigation Sections Added to AppLayout

| Icon | Label | Section |
|------|-------|---------|
| Factory | Production | Production Orders, BOMs, Delivery Schedules |
| ClipboardCheck | QC/QA | Inspections, NCRs, Templates |
| Wrench | Maintenance | Equipment, Work Orders |
| Settings | Mold | Mold Masters |
| Truck | Delivery | Delivery Receipts, Shipments |
| ShieldCheck | ISO/IATF | Document Register, Internal Audits |
| ClipboardList | VP Approvals | MRQ VP Approvals |

### New Custom Hooks (TanStack Query)

| Hook File | Exports |
|-----------|---------|
| `useInventory.ts` | `useItems`, `useItemCategories`, `useStockBalances`, `useLowStock`, `useMaterialRequisitions`, `useMaterialRequisition`, `useCreateItem`, `useCreateMRQ`, `useMRQWorkflow` |
| `useProduction.ts` | `useBOMs`, `useBOM`, `useProductionOrders`, `useProductionOrder`, `useCreateBOM`, `useCreateOrder`, `useOrderWorkflow`, `useLogOutput`, `useDeliverySchedules` |
| `useQC.ts` | `useInspectionTemplates`, `useInspections`, `useNonConformanceReports`, `useNCR`, `useCreateTemplate`, `useCreateInspection`, `useRecordResults`, `useCreateNCR`, `useNCRWorkflow` |
| `useMaintenance.ts` | `useEquipment`, `useEquipmentItem`, `useWorkOrders`, `useCreateEquipment`, `useUpdateEquipment`, `useCreateWorkOrder`, `useWorkOrderWorkflow`, `useStorePmSchedule` |
| `useMold.ts` | `useMolds`, `useMold`, `useCreateMold`, `useUpdateMold`, `useLogShots` |
| `useDelivery.ts` | `useDeliveryReceipts`, `useDeliveryReceipt`, `useShipments`, `useCreateReceipt`, `useConfirmReceipt`, `useCreateShipment` |
| `useISO.ts` | `useControlledDocuments`, `useControlledDocument`, `useInternalAudits`, `useInternalAudit`, `useCreateDocument`, `useUpdateDocument`, `useCreateAudit`, `useAuditWorkflow`, `useStoreFinding` |

---

## Architecture Notes

### Domain Services Pattern

All domain services implement `App\Shared\Contracts\ServiceContract` and use the patterns:
- `App\Shared\Exceptions\DomainException` (not raw `\DomainException`) for domain-specific errors
- `DB::transaction()` wrapping all multi-model writes
- `LengthAwarePaginator` for paginated list methods

### Policy Registration

All policies are registered in `App\Providers\AppServiceProvider::boot()` via `Gate::policy()`:

```php
// New module policies registered
Gate::policy(ItemMaster::class,            ItemMasterPolicy::class);
Gate::policy(MaterialRequisition::class,   MaterialRequisitionPolicy::class);
Gate::policy(BillOfMaterials::class,       ProductionOrderPolicy::class);
Gate::policy(ProductionOrder::class,       ProductionOrderPolicy::class);
Gate::policy(Inspection::class,            QCPolicy::class);
Gate::policy(NonConformanceReport::class,  QCPolicy::class);
Gate::policy(Equipment::class,             MaintenancePolicy::class);
Gate::policy(MaintenanceWorkOrder::class,  MaintenancePolicy::class);
Gate::policy(MoldMaster::class,            MoldPolicy::class);
Gate::policy(DeliveryReceipt::class,       DeliveryPolicy::class);
Gate::policy(Shipment::class,              DeliveryPolicy::class);
Gate::policy(ControlledDocument::class,    ISOPolicy::class);
Gate::policy(InternalAudit::class,         ISOPolicy::class);
```

### State Machines

| Entity | States |
|--------|--------|
| `ProductionOrder` | `draft → released → in_progress → completed / cancelled` |
| `MaintenanceWorkOrder` | `open → in_progress → completed` |
| `DeliveryReceipt` | `draft → confirmed` |
| `InternalAudit` | `planned → in_progress → completed` |
| `MaterialRequisition` | `draft → submitted → checked → reviewed → vp_approved → fulfilled / rejected / cancelled` |
| `NonConformanceReport` | `open → capa_assigned → capa_completed → closed` |

---

## Test Data (NewModulesSeeder)

Run `php artisan db:seed --class=NewModulesSeeder` after the standard seed, or use `php artisan migrate:fresh --seed` to rebuild everything.

### What is seeded

| Module | Records |
|--------|---------|
| **Inventory** | 1 item category (`Raw Materials`), 3 item masters (RAW-001, RAW-002, FGD-001), 1 warehouse location (WH-A1) |
| **Maintenance** | 3 equipment (operational, under_maintenance, decommissioned), 2 work orders (open-critical, completed-preventive), 2 PM schedules |
| **Mold** | 3 mold masters (1 near-critical at 91 % shots, 1 healthy, 1 under_maintenance), 1 shot log |
| **Delivery** | 1 demo vendor, 1 demo customer, 2 delivery receipts (1 inbound confirmed + items, 1 outbound draft), 1 shipment |
| **ISO** | 3 controlled documents (Quality Manual, Procedure, Work Instruction), 2 internal audits (1 completed with 2 findings + 1 improvement action, 1 planned) |

### Test Login Accounts (from SampleAccountsSeeder)

| Email | Password | Role |
|-------|----------|------|
| `admin@ogamierp.local` | `Admin@1234567890!` | admin |
| `hr.manager@ogamierp.local` | `HrManager@1234!` | hr_manager |
| `acctg.manager@ogamierp.local` | `AcctgManager@1234!` | acctg_mgr |
| `hr.supervisor@ogamierp.local` | `HrSupervisor@1234!` | head |
| `hr.staff@ogamierp.local` | `HrStaff@1234!` | staff |

> **Note:** Assign new module permissions to these roles via `php artisan permission:sync` or by re-running `RolePermissionSeeder` if role assignments need updates.

---

## Bug Fixes Applied

The following bugs were discovered and fixed during the post-implementation audit:

| # | File | Bug | Fix Applied |
|---|------|-----|------------|
| 1 | `AppServiceProvider.php` | `BillOfMaterial::class` (singular — class does not exist) | Corrected to `BillOfMaterials::class` |
| 2 | `AppServiceProvider.php` | `ItemPolicy::class` (non-existent class) | Corrected to `ItemMasterPolicy::class` |
| 3 | `AppServiceProvider.php` | `MrqPolicy::class` (non-existent class) | Corrected to `MaterialRequisitionPolicy::class` |
| 4 | `AppServiceProvider.php` | `ProductionPolicy::class` (non-existent class) | Corrected to `ProductionOrderPolicy::class` |
| 5 | `MaintenanceController.php` | Missing `storePmSchedule()` method — route POST `/equipment/{id}/pm-schedules` had no handler | Added method + import |
| 6 | `StorePmScheduleRequest.php` | File did not exist — referenced by route but missing | Created with `task_name`, `frequency_days`, `last_done_on` validation rules |
| 7 | `DeliveryService.php` | `throw new \DomainException(...)` — uses raw PHP exception bypassing global handler | Changed to `App\Shared\Exceptions\DomainException` |
| 8 | `ISOService.php` | `throw new \DomainException(...)` ×2 — same issue as above | Changed both throws to `App\Shared\Exceptions\DomainException` |

---

*This report was generated automatically as part of the Sprint A–F completion audit.*
