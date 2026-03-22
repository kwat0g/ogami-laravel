# Maintenance Module Updates

## Files to Update:
1. CreateEquipmentPage.tsx - Add firstErrorMessage import and use it in catch blocks
2. EquipmentDetailPage.tsx - Add ConfirmDestructiveDialog, use firstErrorMessage, add delete/archive confirmation
3. CreateWorkOrderPage.tsx - Add firstErrorMessage, better validation, add confirmation for submit
4. WorkOrderDetailPage.tsx - Add ConfirmDestructiveDialog, cancel work order confirmation, use firstErrorMessage

## Hooks needed:
- Add useCancelWorkOrder to useMaintenance.ts
- Add useDeleteEquipment to useMaintenance.ts

## Critical actions that need confirmation:
- Delete equipment (destructive)
- Archive equipment (destructive) 
- Start work order (important)
- Complete work order (important)
- Cancel work order (destructive)
