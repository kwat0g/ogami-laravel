import { useCallback } from 'react'
import { useForm, UseFormProps, FieldValues, DefaultValues } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { toast } from 'sonner'
import { parseApiError, firstErrorMessage } from '@/lib/errorHandler'

interface UseValidatedFormOptions<T extends FieldValues> extends Omit<UseFormProps<T>, 'resolver'> {
  schema: z.ZodSchema<T>
  onSubmit: (data: T) => Promise<void>
  successMessage?: string
  errorMessage?: string
  resetOnSuccess?: boolean
}

/**
 * useValidatedForm - A wrapper around react-hook-form with Zod validation,
 * toast notifications, and proper error handling.
 * 
 * Usage:
 * ```tsx
 * const form = useValidatedForm({
 *   schema: employeeSchema,
 *   defaultValues: { first_name: '', ... },
 *   onSubmit: async (data) => {
 *     await createEmployee(data)
 *   },
 *   successMessage: 'Employee created successfully',
 *   errorMessage: 'Failed to create employee',
 * })
 * ```
 */
export function useValidatedForm<T extends FieldValues>({
  schema,
  onSubmit,
  successMessage = 'Saved successfully',
  errorMessage = 'Failed to save',
  resetOnSuccess = false,
  ...formOptions
}: UseValidatedFormOptions<T>) {
  const form = useForm<T>({
    ...formOptions,
    resolver: zodResolver(schema),
  })

  const handleSubmit = useCallback(
    async (data: T) => {
      try {
        await onSubmit(data)
        toast.success(successMessage)
        if (resetOnSuccess) {
          form.reset()
        }
      } catch (err) {
        const parsed = parseApiError(err)
        
        // Show field-specific errors in form
        if (Object.keys(parsed.fieldErrors).length > 0) {
          Object.entries(parsed.fieldErrors).forEach(([field, messages]) => {
            form.setError(field as keyof T, {
              type: 'manual',
              message: messages[0],
            })
          })
        }
        
        // Show toast with first error
        const message = firstErrorMessage(err)
        toast.error(`${errorMessage}: ${message}`)
        
        throw err
      }
    },
    [onSubmit, successMessage, errorMessage, resetOnSuccess, form]
  )

  return {
    ...form,
    handleSubmit: form.handleSubmit(handleSubmit),
  }
}

/**
 * useDeleteConfirmation - Hook for handling delete operations with confirmation
 * 
 * Usage:
 * ```tsx
 * const handleDelete = useDeleteConfirmation({
 *   itemName: 'employee',
 *   onDelete: async () => {
 *     await deleteEmployee(id)
 *   },
 *   onSuccess: () => {
 *     navigate('/employees')
 *   },
 * })
 * ```
 */
interface UseDeleteConfirmationOptions {
  itemName: string
  itemDisplayName?: string
  onDelete: () => Promise<void>
  onSuccess?: () => void
  successMessage?: string
}

export function useDeleteConfirmation({
  itemName,
  itemDisplayName,
  onDelete,
  onSuccess,
  successMessage,
}: UseDeleteConfirmationOptions) {
  return useCallback(async () => {
    try {
      await onDelete()
      toast.success(successMessage ?? `${itemDisplayName ?? itemName} deleted successfully`)
      onSuccess?.()
    } catch (err) {
      const message = firstErrorMessage(err)
      toast.error(`Failed to delete ${itemName}: ${message}`)
      throw err
    }
  }, [itemName, itemDisplayName, onDelete, onSuccess, successMessage])
}

/**
 * useActionWithToast - Hook for any action with toast notifications
 * 
 * Usage:
 * ```tsx
 * const approveLeave = useActionWithToast({
 *   action: async () => {
 *     await approveLeaveRequest(id)
 *   },
 *   successMessage: 'Leave request approved',
 *   errorMessage: 'Failed to approve leave request',
 * })
 * ```
 */
interface UseActionWithToastOptions {
  action: () => Promise<void>
  successMessage: string
  errorMessage: string
  onSuccess?: () => void
}

export function useActionWithToast({
  action,
  successMessage,
  errorMessage,
  onSuccess,
}: UseActionWithToastOptions) {
  return useCallback(async () => {
    try {
      await action()
      toast.success(successMessage)
      onSuccess?.()
    } catch (err) {
      const message = firstErrorMessage(err)
      toast.error(`${errorMessage}: ${message}`)
      throw err
    }
  }, [action, successMessage, errorMessage, onSuccess])
}
