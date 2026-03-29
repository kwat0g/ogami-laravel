import { useState, useCallback } from 'react'
import { toast } from 'sonner'
import { firstErrorMessage } from '@/lib/errorHandler'
import { getActionCategory, isDestructive, ActionCategory } from '@/lib/actionCategories'

interface UseActionConfirmationResult {
  /** Whether the confirmation dialog is open */
  isOpen: boolean
  /** Open the confirmation dialog */
  open: () => void
  /** Close the confirmation dialog */
  close: () => void
  /** Execute the action (call this after confirmation) */
  execute: () => Promise<void>
  /** The action category config */
  config: ActionCategory | null
  /** Whether this action requires typing a confirmation word */
  isDestructive: boolean
  /** Loading state */
  isLoading: boolean
}

interface UseActionConfirmationOptions {
  /** The action key (e.g., 'employee.delete', 'payroll.void') */
  actionKey: string
  /** The actual action to perform */
  onExecute: () => Promise<void>
  /** Called after successful execution */
  onSuccess?: () => void
  /** Override the default success message */
  successMessage?: string
  /** Override the default error message */
  errorMessage?: string
}

/**
 * useActionConfirmation - Hook for actions that need confirmation
 * 
 * Automatically determines if confirmation is needed based on actionKey,
 * and provides the appropriate dialog state and execution handler.
 * 
 * Usage with Destructive Dialog:
 * ```tsx
 * const deleteAction = useActionConfirmation({
 *   actionKey: 'employee.delete',
 *   onExecute: async () => { await deleteEmployee(id) },
 *   onSuccess: () => { navigate('/employees') },
 * })
 * 
 * // In render:
 * <ConfirmDestructiveDialog
 *   title={deleteAction.config?.title ?? 'Delete?'}
 *   description={deleteAction.config?.description ?? ''}
 *   confirmWord={deleteAction.config?.confirmWord}
 *   confirmLabel={deleteAction.config?.confirmLabel}
 *   onConfirm={deleteAction.execute}
 * >
 *   <button>Delete</button>
 * </ConfirmDestructiveDialog>
 * ```
 * 
 * Usage with Simple Confirm Dialog:
 * ```tsx
 * const approveAction = useActionConfirmation({
 *   actionKey: 'leave.approve',
 *   onExecute: async () => { await approveLeave(id) },
 *   onSuccess: () => { refetch() },
 * })
 * 
 * <ConfirmDialog
 *   title={approveAction.config?.title ?? 'Confirm?'}
 *   description={approveAction.config?.description ?? ''}
 *   confirmLabel={approveAction.config?.confirmLabel}
 *   onConfirm={approveAction.execute}
 * >
 *   <button>Approve</button>
 * </ConfirmDialog>
 * ```
 */
export function useActionConfirmation({
  actionKey,
  onExecute,
  onSuccess,
  successMessage,
  errorMessage,
}: UseActionConfirmationOptions): UseActionConfirmationResult {
  const [isOpen, setIsOpen] = useState(false)
  const [isLoading, setIsLoading] = useState(false)
  
  const config = getActionCategory(actionKey)
  const destructive = isDestructive(actionKey)
  
  const open = useCallback(() => {
    // If no confirmation needed, execute immediately
    if (!config || config.type === 'none') {
      execute()
      return
    }
    setIsOpen(true)
  }, [config])
  
  const close = useCallback(() => {
    setIsOpen(false)
  }, [])
  
  const execute = useCallback(async () => {
    setIsLoading(true)
    try {
      await onExecute()
      toast.success(successMessage ?? config?.title.replace('?', '') ?? 'Action completed')
      onSuccess?.()
      setIsOpen(false)
    } catch (err) {
      const message = firstErrorMessage(err)
      toast.error(`${errorMessage ?? config?.title.replace('?', '') ?? 'Action failed'}: ${message}`)
      throw err
    } finally {
      setIsLoading(false)
    }
  }, [onExecute, onSuccess, successMessage, errorMessage, config])
  
  return {
    isOpen,
    open,
    close,
    execute,
    config,
    isDestructive: destructive,
    isLoading,
  }
}

/**
 * useImmediateAction - For actions that don't need confirmation but need toast handling
 * 
 * Usage:
 * ```tsx
 * const saveAction = useImmediateAction({
 *   action: async () => { await saveData(data) },
 *   successMessage: 'Data saved successfully',
 *   errorMessage: 'Failed to save data',
 * })
 * 
 * <button onClick={saveAction.execute}>Save</button>
 * ```
 */
interface UseImmediateActionOptions {
  action: () => Promise<void>
  successMessage: string
  errorMessage: string
  onSuccess?: () => void
}

export function useImmediateAction({
  action,
  successMessage,
  errorMessage,
  onSuccess,
}: UseImmediateActionOptions) {
  const [isLoading, setIsLoading] = useState(false)
  
  const execute = useCallback(async () => {
    setIsLoading(true)
    try {
      await action()
      toast.success(successMessage)
      onSuccess?.()
    } catch (err) {
      const message = firstErrorMessage(err)
      toast.error(`${errorMessage}: ${message}`)
      throw err
    } finally {
      setIsLoading(false)
    }
  }, [action, successMessage, errorMessage, onSuccess])
  
  return { execute, isLoading }
}
