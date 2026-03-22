# Validation and Confirmation Implementation Guide

## Overview

This guide documents the standardized approach for form validation, toast notifications, and confirmation modals across the Ogami ERP frontend.

## Core Principles

1. **Client-side validation first** - Validate before submitting to API
2. **Clear error messages** - Show field-specific errors inline and summary in toast
3. **Confirmation for destructive actions** - Always confirm delete, void, archive, etc.
4. **Success feedback** - Always show toast on successful operations
5. **Error recovery** - Keep form open on error so user can fix

## Quick Reference

### 1. Form Validation with Zod + React Hook Form

```tsx
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { toast } from 'sonner'
import { firstErrorMessage } from '@/lib/errorHandler'

const schema = z.object({
  name: z.string().trim().min(2, 'Name must be at least 2 characters'),
  email: z.string().trim().email('Please enter a valid email'),
})

function MyForm() {
  const form = useForm({
    resolver: zodResolver(schema),
    defaultValues: { name: '', email: '' },
  })

  const onSubmit = async (data) => {
    try {
      await api.post('/items', data)
      toast.success('Item created successfully')
    } catch (err) {
      const message = firstErrorMessage(err)
      toast.error(`Failed to create item: ${message}`)
    }
  }

  return (
    <form onSubmit={form.handleSubmit(onSubmit)}>
      {/* ... */}
    </form>
  )
}
```

### 2. Using the useValidatedForm Hook

```tsx
import { useValidatedForm } from '@/hooks/useValidatedForm'

const form = useValidatedForm({
  schema: customerSchema,
  defaultValues: { name: '', email: '' },
  onSubmit: async (data) => {
    await createCustomer(data)
  },
  successMessage: 'Customer created successfully',
  errorMessage: 'Failed to create customer',
  resetOnSuccess: true,
})

// Use form.handleSubmit for the onSubmit handler
<form onSubmit={form.handleSubmit}>
```

### 3. Confirmation Modal for Destructive Actions

```tsx
import ConfirmDestructiveDialog from '@/components/ui/ConfirmDestructiveDialog'
import { firstErrorMessage } from '@/lib/errorHandler'

function DeleteButton({ id }) {
  const deleteItem = async () => {
    try {
      await api.delete(`/items/${id}`)
      toast.success('Item deleted successfully')
    } catch (err) {
      const message = firstErrorMessage(err)
      toast.error(`Failed to delete item: ${message}`)
      throw err // Re-throw so dialog knows it failed
    }
  }

  return (
    <ConfirmDestructiveDialog
      title="Delete Item?"
      description="This will permanently delete this item. This action cannot be undone."
      confirmWord="DELETE"
      confirmLabel="Delete Item"
      onConfirm={deleteItem}
    >
      <button>Delete</button>
    </ConfirmDestructiveDialog>
  )
}
```

### 4. Simple Confirmation Modal

```tsx
import ConfirmDialog from '@/components/ui/ConfirmDialog'

<ConfirmDialog
  title="Approve Leave Request?"
  description="This will approve the leave and deduct from balance."
  confirmLabel="Approve"
  onConfirm={handleApprove}
>
  <button>Approve</button>
</ConfirmDialog>
```

### 5. Using Action Categories (Recommended)

```tsx
import { useActionConfirmation } from '@/hooks/useActionConfirmation'

function ArchiveButton({ customer }) {
  const archiveAction = useActionConfirmation({
    actionKey: 'customer.archive',
    onExecute: async () => {
      await archiveCustomer(customer.id)
    },
    onSuccess: () => {
      refetch()
    },
  })

  return (
    <ConfirmDestructiveDialog
      title={archiveAction.config?.title}
      description={archiveAction.config?.description}
      confirmWord={archiveAction.config?.confirmWord}
      confirmLabel={archiveAction.config?.confirmLabel}
      onConfirm={archiveAction.execute}
    >
      <button>Archive</button>
    </ConfirmDestructiveDialog>
  )
}
```

## Action Categories

### Destructive Actions (require typing confirmation word)

Defined in `lib/actionCategories.ts`:

- `employee.delete` - Delete employee record
- `employee.terminate` - Terminate employee
- `payroll.void` - Void payroll run
- `journal_entry.delete` - Delete journal entry
- `journal_entry.reverse` - Reverse journal entry
- `vendor.delete` - Delete vendor
- `customer.delete` - Delete customer
- `invoice.delete` - Delete invoice
- `invoice.write_off` - Write off invoice
- `item.delete` - Delete inventory item
- `user.delete` - Delete user account
- `backup.delete` - Delete backup

### Confirm Actions (simple confirmation, no typing)

- `employee.activate` / `employee.suspend`
- `leave.approve` / `leave.reject`
- `loan.approve`
- `payroll.submit` / `payroll.approve` / `payroll.publish`
- `journal_entry.post` / `journal_entry.submit`
- `invoice.approve` / `invoice.cancel`
- `vendor.archive`
- `po.approve` / `po.cancel`
- `pr.approve`
- `mrq.approve`
- `work_order.release` / `work_order.complete`

## Toast Notification Guidelines

### Success Messages

```tsx
// Create operations
toast.success('Customer created successfully')
toast.success(`Customer "${name}" has been created successfully`) // With name

// Update operations
toast.success('Customer updated successfully')
toast.success(`Customer "${name}" has been updated successfully`)

// Delete/Archive operations
toast.success('Customer archived successfully')
toast.success(`Customer "${name}" has been archived successfully`)

// Approval operations
toast.success('Leave request approved')
toast.success('Payroll submitted for approval')

// Batch operations
toast.success('5 items processed successfully')
```

### Error Messages

```tsx
// Using firstErrorMessage helper (recommended)
import { firstErrorMessage } from '@/lib/errorHandler'

catch (err) {
  const message = firstErrorMessage(err)
  toast.error(`Failed to create customer: ${message}`)
}

// Specific error types
toast.error('Validation failed: Please check the highlighted fields')
toast.error('Permission denied: You do not have access to this resource')
toast.error('Network error: Please check your connection and try again')
```

## Validation Patterns

### Field-level Validation (inline)

```tsx
<label>
  <span>Email {errors.email && <span className="text-red-500">*</span>}</span>
  <input
    className={errors.email ? 'border-red-500' : 'border-neutral-300'}
    {...register('email')}
  />
  {errors.email && (
    <p className="text-xs text-red-600 mt-1">{errors.email.message}</p>
  )}
</label>
```

### Form-level Validation (before submit)

```tsx
const submit = async (e: React.FormEvent) => {
  e.preventDefault()
  
  // Custom validation
  if (!form.name.trim()) {
    setErrors({ name: 'Name is required' })
    toast.error('Please fix the validation errors before submitting')
    return
  }
  
  // Proceed with submission
  // ...
}
```

## Best Practices

### 1. Always Handle Loading States

```tsx
const [isLoading, setIsLoading] = useState(false)

const handleSubmit = async () => {
  setIsLoading(true)
  try {
    await api.post('/items', data)
  } finally {
    setIsLoading(false)
  }
}

<button disabled={isLoading}>
  {isLoading ? 'Saving…' : 'Save'}
</button>
```

### 2. Clear Field Errors on Input

```tsx
const handleChange = (field: string, value: string) => {
  setForm(prev => ({ ...prev, [field]: value }))
  // Clear error when user starts typing
  if (errors[field]) {
    setErrors(prev => ({ ...prev, [field]: '' }))
  }
}
```

### 3. Don't Close Modal on Error

```tsx
const submit = async () => {
  try {
    await api.post('/items', data)
    toast.success('Created successfully')
    onClose() // Close on success
  } catch (err) {
    const message = firstErrorMessage(err)
    toast.error(`Failed: ${message}`)
    // Don't close - let user fix the error
  }
}
```

### 4. Use Consistent Button Labels

| Action | Label |
|--------|-------|
| Create | "Create [Item]" |
| Update | "Update [Item]" |
| Delete | "Delete [Item]" |
| Save | "Save" / "Save Changes" |
| Cancel | "Cancel" |
| Loading | "Saving…" / "Loading…" |

### 5. Refresh Data After Mutations

```tsx
const { refetch } = useCustomers()

const handleDelete = async () => {
  try {
    await deleteCustomer(id)
    toast.success('Deleted successfully')
    refetch() // Refresh the list
  } catch (err) {
    toast.error('Failed to delete')
  }
}
```

## Migration Checklist

When updating existing pages:

- [ ] Add Zod schema with validation messages
- [ ] Use react-hook-form with zodResolver
- [ ] Add inline field error display
- [ ] Wrap submissions in try/catch
- [ ] Add toast.success on success
- [ ] Add toast.error with firstErrorMessage on failure
- [ ] Add ConfirmDestructiveDialog for delete/archive/void actions
- [ ] Add ConfirmDialog for approve/reject/submit actions
- [ ] Handle loading states (disable buttons during submission)
- [ ] Clear field errors when user edits field
- [ ] Don't close modal on error
- [ ] Refresh data after successful mutation

## Example Complete Implementation

See `pages/ar/CustomersPage.tsx` for a complete example with:
- Zod schema validation
- Inline field errors
- Toast notifications
- Confirmation modal for archive
- Loading states
- Error handling
- Data refresh after mutation
