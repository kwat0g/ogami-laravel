# Client Order to Delivery Workflow

## Overview

This document describes the complete workflow from **Client Order Placement** through **Delivery Receipt** and **Invoice Generation** in the Ogami ERP system.

## Workflow Diagram

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                           CLIENT ORDER WORKFLOW                                  │
└─────────────────────────────────────────────────────────────────────────────────┘

┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│   CLIENT        │    │     SALES       │    │   PRODUCTION    │
│   PORTAL        │    │    PORTAL       │    │    PORTAL       │
└────────┬────────┘    └────────┬────────┘    └────────┬────────┘
         │                        │                        │
         │  1. SUBMIT ORDER       │                        │
         │ ──────────────────────>│                        │
         │                        │                        │
         │              2. REVIEW & NEGOTIATE               │
         │ <──────────────────────>                       │
         │                        │                        │
         │  3. ACCEPT/DECLINE     │                        │
         │ ──────────────────────>│                        │
         │                        │                        │
         │              4. APPROVE ORDER                  │
         │                        │                        │
         │                        │  5. AUTO-CREATE      │
         │                        │     DELIVERY SCHEDULE  │
         │                        │ ──────────────────────>│
         │                        │                        │
         │                        │              6. CREATE │
         │                        │              PRODUCTION  │
         │                        │              ORDER (if   │
         │                        │              needed)     │
         │                        │ ──────────────────────>│
         │                        │                        │
         │                        │  7. MARK READY         │
         │                        │ <──────────────────────│
         │                        │                        │
         │              8. DISPATCH                       │
         │                        │──────────────────────>│
         │                        │                        │
         │  9. DELIVERED          │                        │
         │ <──────────────────────│                        │
         │                        │                        │
         │ 10. ACKNOWLEDGE RECEIPT│                        │
         │ ──────────────────────>│                        │
         │                        │                        │
         │              11. GENERATE INVOICE              │
         │ <──────────────────────│                        │
         │                        │                        │
```

## Detailed Process Flow

### Phase 1: Order Submission (Client Portal)

**Actor**: Client User

1. **Browse Products** (`/client-portal/shop`)
   - View available products and prices
   - Add items to cart

2. **Submit Order** (`/client-portal/orders/new`)
   - Select delivery date
   - Add special instructions
   - Submit order

3. **System Actions**:
   - Status: `pending`
   - Auto-generate order reference (e.g., `CO-2026-00001`)
   - Send notification to sales team
   - Create audit log

**API**: `POST /api/v1/crm/client-orders`

---

### Phase 2: Sales Review & Negotiation (Sales Portal)

**Actor**: Sales Officer/Manager

4. **Review Order** (`/sales/client-orders`)
   - View pending orders
   - Check product availability
   - Assess delivery capacity

5. **Actions Available**:

   | Action | When to Use | Result |
   |--------|-------------|--------|
   | **Approve** | Stock available, can meet date | Status → `approved` |
   | **Negotiate** | Need to change date/terms | Status → `negotiating` |
   | **Reject** | Cannot fulfill | Status → `rejected` |

6. **Negotiation Process**:
   ```
   Sales: Propose new delivery date/terms
           ↓
   Client: Accept, Counter, or Cancel
           ↓
   If Counter: Back to Sales (max 5 rounds)
   ```

**API**: 
- `POST /api/v1/crm/client-orders/{id}/approve`
- `POST /api/v1/crm/client-orders/{id}/negotiate`
- `POST /api/v1/crm/client-orders/{id}/reject`

---

### Phase 3: Delivery Schedule Creation (Automatic)

**Trigger**: Order approved

7. **System Creates**:
   - **Combined Delivery Schedule** (`CDS-YYYY-NNNN`)
     - Groups all order items
     - Tracks overall progress
   - **Item Delivery Schedules** (1 per item)
     - Individual tracking per product

8. **Status Calculation**:
   ```
   planning          → Initial state
   partially_ready   → Some items ready
   ready             → All items ready
   dispatched        → Out for delivery
   delivered         → Physically delivered
   ```

**View**: `/production/combined-delivery-schedules`

---

### Phase 4: Production or Stock Fulfillment

**Actor**: Production Team

#### Option A: Production Required

9. **Create Production Order**:
   - Select BOM (Bill of Materials)
   - Set production dates
   - Allocate materials

10. **Production Status Flow**:
    ```
    draft → released → in_progress → completed
    ```

11. **On Completion**:
    - Production order: `completed`
    - Item delivery schedule: `ready`
    - Update stock levels

**View**: `/production/orders`

#### Option B: Stock Fulfillment

12. **Direct Fulfillment**:
    - Check stock availability
    - Deduct from warehouse
    - Mark as `ready`
    - Skip production entirely

**API**: `POST /api/v1/production/delivery-schedules/{id}/fulfill`

---

### Phase 5: Dispatch & Delivery (Sales Portal)

**Actor**: Sales/Delivery Team

13. **Dispatch Order**:
    - Assign driver/vehicle
    - Set delivery date
    - Print delivery receipt
    - Status: `dispatched`

14. **Physical Delivery**:
    - Deliver to customer
    - Mark as `delivered`
    - **NO INVOICE YET** (waits for client acknowledgment)

**API**: `POST /api/v1/production/combined-delivery-schedules/{id}/dispatch`

---

### Phase 6: Client Acknowledgment (Client Portal)

**Actor**: Client User

15. **Receive Notification**:
    - Email: "Your order has been delivered"
    - Portal notification

16. **Log into Portal** (`/client-portal/deliveries/{id}`):
    - View delivered items
    - Confirm quantity received
    - Report condition:
      - ✓ Good (perfect)
      - ⚠ Damaged (defects noted)
      - ✗ Missing (not received)
    - Add notes/photos

17. **Submit Acknowledgment**:
    - All items must be acknowledged
    - Disputes recorded for resolution

**API**: `POST /api/v1/production/combined-delivery-schedules/{id}/acknowledge`

---

### Phase 7: Invoice Generation (Automatic)

**Trigger**: Client acknowledgment submitted

18. **System Creates Invoice**:
    - Pull order totals
    - Calculate VAT (12%)
    - Apply any credits for damaged items
    - Generate invoice PDF
    - Send to customer email

19. **Invoice Status**:
    ```
    draft → approved → sent → paid
    ```

**View**: Client portal → "My Invoices"

---

## Status Reference

### Client Order Status

| Status | Description | Next Actions |
|--------|-------------|--------------|
| `pending` | Submitted, awaiting review | Approve, Negotiate, Reject |
| `negotiating` | Under discussion | Accept, Counter, Cancel |
| `client_responded` | Client made counter-offer | Accept, Counter, Reject |
| `approved` | Accepted, in fulfillment | Create delivery schedule |
| `rejected` | Cannot fulfill | Archive |
| `cancelled` | Client or system cancelled | Archive |

### Delivery Schedule Status

| Status | Description |
|--------|-------------|
| `planning` | Items not yet ready |
| `partially_ready` | Some items ready, waiting for others |
| `ready` | All items ready for dispatch |
| `dispatched` | Out for delivery |
| `delivered` | Physically delivered, awaiting acknowledgment |
| `completed` | Acknowledged, invoiced |
| `cancelled` | Delivery cancelled |

### Production Order Status

| Status | Description |
|--------|-------------|
| `draft` | Created, not yet released |
| `released` | Ready to start production |
| `in_progress` | Actively being produced |
| `completed` | Finished, items ready |
| `cancelled` | Production cancelled |

---

## Notifications

| Event | Recipient | Channel |
|-------|-----------|---------|
| Order submitted | Sales team | Email + Dashboard |
| Order approved | Client | Email + SMS |
| Order rejected | Client | Email |
| Negotiation started | Client | Email |
| Production complete | Sales | Dashboard |
| Items ready | Sales | Dashboard |
| Order dispatched | Client | Email + SMS |
| Order delivered | Client | Email + Portal |
| Invoice generated | Client | Email + PDF |
| Payment due | Client | Email reminder |

---

## Edge Cases & Special Scenarios

### 1. Partial Delivery

**Scenario**: Client orders 10 items, only 8 ready

**Solution**:
- Option A: Wait for all items (keep status `planning`)
- Option B: Deliver partial (`partially_ready` → notify client about missing 2 items)
- Option C: Split into 2 separate deliveries

### 2. Damaged Items

**Scenario**: Client reports damaged goods

**Resolution**:
1. Acknowledge damage in portal
2. Sales reviews dispute
3. Options:
   - Issue credit note
   - Replace items (new production order)
   - Partial refund

### 3. Production Delay

**Scenario**: Production takes longer than estimated

**Actions**:
1. Update delivery schedule with new expected date
2. Notify client automatically
3. Client can:
   - Accept new date
   - Cancel remaining items
   - Negotiate partial delivery

### 4. Stock Shortage

**Scenario**: Insufficient stock to fulfill order

**Resolution**:
1. System blocks fulfillment (shows error)
2. Options:
   - Create production order
   - Partial delivery + backorder
   - Cancel + notify client

---

## Permissions

### Client Portal
- ✓ View own orders
- ✓ Create new orders
- ✓ Respond to negotiations
- ✓ Acknowledge deliveries
- ✓ View invoices
- ✗ Approve orders
- ✗ View other clients

### Sales Portal
- ✓ View all client orders
- ✓ Approve/reject/negotiate
- ✓ Create delivery schedules
- ✓ Dispatch orders
- ✓ Mark delivered
- ✗ Acknowledge (client only)

### Production Portal
- ✓ Create production orders
- ✓ Update production status
- ✓ Mark items complete
- ✗ Approve client orders
- ✗ Dispatch deliveries

---

## Data Model Relationships

```
ClientOrder
├── Customer (belongs to)
├── Items (has many)
│   └── ItemMaster (belongs to)
├── Activities (has many)
└── CombinedDeliverySchedules (has many)
    ├── Customer (belongs to)
    ├── ItemSchedules (has many)
    │   ├── ProductItem (belongs to)
    │   ├── ProductionOrders (has many)
    │   └── ClientAcknowledgment (json)
    └── CustomerInvoice (has one)
```

---

## API Endpoints Summary

### Client Order Management
| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/crm/client-orders` | Submit new order |
| GET | `/crm/client-orders` | List orders |
| GET | `/crm/client-orders/{id}` | View order |
| POST | `/crm/client-orders/{id}/approve` | Approve order |
| POST | `/crm/client-orders/{id}/negotiate` | Negotiate terms |
| POST | `/crm/client-orders/{id}/reject` | Reject order |

### Delivery Management
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/production/combined-delivery-schedules` | List schedules |
| POST | `/production/combined-delivery-schedules/{id}/dispatch` | Dispatch |
| POST | `/production/combined-delivery-schedules/{id}/delivered` | Mark delivered |
| POST | `/production/combined-delivery-schedules/{id}/acknowledge` | Client ack |

### Production Management
| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/production/orders` | Create PO |
| PATCH | `/production/orders/{id}/complete` | Complete PO |

---

## Testing Scenarios

### Scenario 1: Standard Order
```
1. Client submits order for 10 items
2. Sales approves
3. System creates production order
4. Production completes
5. Items marked ready
6. Sales dispatches
7. Sales marks delivered
8. Client acknowledges (all good)
9. Invoice generated automatically
```

### Scenario 2: Negotiation Required
```
1. Client submits order for next week
2. Sales negotiates: "Need 3 weeks"
3. Client counters: "2 weeks max"
4. Sales accepts counter
5. Order approved with 2-week date
6. Continues as standard order...
```

### Scenario 3: Damaged Items
```
1. Order delivered
2. Client acknowledges:
   - 8 items: good
   - 2 items: damaged
3. System flags dispute
4. Sales reviews
5. Issues credit note for 2 items
6. Invoice generated for 8 items only
```

### Scenario 4: Stock Fulfillment
```
1. Client orders item with 500 in stock
2. Sales approves
3. System auto-fulfills from stock
4. No production needed
5. Immediately ready for dispatch
```

---

## Support & Troubleshooting

### Common Issues

**Issue**: Order stuck in "negotiating"
**Fix**: Check if client has responded. Max 5 negotiation rounds.

**Issue**: Can't dispatch - items not ready
**Fix**: Check production status or use "fulfill from stock"

**Issue**: Invoice not generated
**Fix**: Ensure client has acknowledged delivery

**Issue**: Production order not creating
**Fix**: Check if BOM exists for product

---

## Version History

| Version | Date | Changes |
|---------|------|---------|
| 1.0 | 2026-03-21 | Initial documentation |

---

## Related Documentation

- [Combined Delivery Schedule](../app/Domains/Production/Models/CombinedDeliverySchedule.php)
- [Client Order Service](../app/Domains/CRM/Services/ClientOrderService.php)
- [Production Order Service](../app/Domains/Production/Services/ProductionOrderService.php)
