# Delivery Schedule Multi-Item Restructure Plan

## Goal
Merge `delivery_schedules` (per-item) and `combined_delivery_schedules` (per-order) into a single multi-item `delivery_schedules` table with a child `delivery_schedule_items` table. One client order = one delivery schedule with multiple items inside.

## Current Architecture
```
Client Order with 2 items
  ├── DeliverySchedule #1 - product A, 3 pcs -> ProductionOrder #1
  ├── DeliverySchedule #2 - product B, 3 pcs -> ProductionOrder #2
  └── CombinedDeliverySchedule - groups #1 + #2 for delivery
```

## Target Architecture
```
Client Order with 2 items
  └── DeliverySchedule - 1 record, customer, target date
        ├── DeliveryScheduleItem #1 - product A, 3 pcs -> ProductionOrder #1
        └── DeliveryScheduleItem #2 - product B, 3 pcs -> ProductionOrder #2
```

## Database Schema Changes

### New table: delivery_schedule_items
- id, ulid, delivery_schedule_id FK, product_item_id FK, qty_ordered, unit_price, status, notes, timestamps, softDeletes
- CHECK: status IN pending, in_production, ready, dispatched, delivered, cancelled

### Modify delivery_schedules -- absorb CombinedDeliverySchedule fields
Add: client_order_id, delivery_address, delivery_instructions, item_status_summary JSONB, total_items, ready_items, missing_items, dispatched_by_id, dispatched_at, actual_delivery_date, has_dispute, dispute_summary, dispute_resolved_at, created_by_id

Remove: product_item_id, qty_ordered, unit_price, combined_delivery_schedule_id, client_acknowledgment

Keep: ds_reference, customer_id, target_delivery_date, type, status, notes

### Modify production_orders
- Add delivery_schedule_item_id FK to delivery_schedule_items
- Keep delivery_schedule_id for quick parent access

### Drop: combined_delivery_schedules, client_order_delivery_schedules

## Files Requiring Changes - 30+ files

### Models: 7 files
- DeliverySchedule.php -- add items hasMany, add CDS fields
- NEW DeliveryScheduleItem.php
- DELETE CombinedDeliverySchedule.php
- ProductionOrder.php -- add delivery_schedule_item_id
- ClientOrder.php -- update relationship
- DELETE ClientOrderDeliverySchedule.php
- DeliveryReceipt.php -- keep delivery_schedule_id pointing to order-level DS

### Services: 6 files
- DeliveryScheduleService.php -- rewrite for multi-item
- DELETE CombinedDeliveryScheduleService.php, merge into DS service
- ClientOrderService.php -- create 1 DS + N items
- ProductionOrderService.php -- reference delivery_schedule_item_id
- DeliveryReceiptService.php -- update references
- ChainRecordService.php -- update trace logic

### Listeners: 5 files
- CreateDeliveryReceiptOnProductionComplete.php
- CreateDeliveryReceiptOnOqcPass.php
- CreateReworkOrderOnOqcFail.php
- UpdateClientOrderOnProductionComplete.php
- UpdateClientOrderOnShipmentDelivered.php

### Controllers + Resources + Routes: 6 files
- DeliveryScheduleController.php -- add item endpoints
- DELETE CombinedDeliveryScheduleController.php
- DeliveryScheduleResource.php -- include items
- DELETE CombinedDeliveryScheduleResource.php
- production.php routes -- merge CDS routes into DS

### Frontend: 10 files
- DeliveryScheduleListPage.tsx -- show item count
- DeliveryScheduleDetailPage.tsx -- items table, per-item status, dispatch/deliver
- CreateDeliverySchedulePage.tsx -- multi-item form
- DELETE CombinedDeliveryScheduleListPage.tsx
- DELETE CombinedDeliveryScheduleDetailPage.tsx
- ProductionOrderDetailPage.tsx -- update DS link
- useProduction.ts -- update hooks
- DELETE useCombinedDeliverySchedules.ts
- types/production.ts -- update types
- router index.tsx -- remove CDS routes

### Notifications: 3 files
- DeliveryScheduleDispatchedNotification.php
- DeliveryScheduleDelayedNotification.php
- DeliveryDisputeNotification.php

## Risk Assessment
- HIGH: Data migration for existing records
- HIGH: Breaking client order approval chain
- MEDIUM: Listener chain OQC pass to DR creation
- LOW: Frontend pages can be rebuilt cleanly
