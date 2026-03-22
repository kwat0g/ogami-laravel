# Fix Client Order and Delivery Schedule Errors

## Errors to Fix

### 1. Client Order 500 Error
**File**: `app/Http/Controllers/CRM/ClientOrderController.php:61`
**Issue**: Nested relationship loading `items.deliverySchedule.deliverySchedule` causing errors
**Fix**: Remove the problematic nested loading, keep simpler relationship loading

### 2. Fulfill 422 Error Message
**File**: `frontend/src/pages/production/DeliveryScheduleDetailPage.tsx:202-209`
**Issue**: Generic error message "Failed to fulfill from stock" instead of specific reason
**Fix**: Extract error message from API response and show specific toast with available/required stock info

### 3. Remove ULID from Schedule Page
**File**: `frontend/src/pages/production/DeliveryScheduleDetailPage.tsx:438-441`
**Issue**: ULID displayed in details section is unnecessary for users
**Fix**: Remove the ULID field from the Details card

## Changes Needed

### Backend (ClientOrderController.php)
```php
// Line 61-68: Simplify relationship loading
return response()->json($order->load([
    'items.itemMaster',
    'customer',
    'activities.user',
    'deliverySchedule',
    'deliverySchedules.deliverySchedule',
]));
```

### Frontend (DeliveryScheduleDetailPage.tsx)
1. **Line 202-210**: Update error handling
```typescript
const handleFulfillFromStock = async () => {
  try {
    await fulfillMutation.mutateAsync()
    toast.success('Order fulfilled from stock successfully')
    setShowFulfillConfirm(false)
  } catch (error: any) {
    // Extract specific error message from API response
    const errorMessage = error?.response?.data?.message || error?.message || 'Failed to fulfill from stock'
    if (errorMessage.includes('Insufficient stock')) {
      toast.error(errorMessage)
    } else {
      toast.error('Failed to fulfill from stock: ' + errorMessage)
    }
  }
}
```

2. **Line 438-441**: Remove ULID section
Remove the entire ULID div block from the Details card.

## Implementation Steps
1. Fix ClientOrderController relationship loading
2. Update frontend error handling for fulfill
3. Remove ULID display from schedule page
4. Test the fixes
