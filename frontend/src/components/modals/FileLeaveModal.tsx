import { useState } from 'react'
import { useCreateLeaveRequest, useLeaveTypes } from '@/hooks/useLeave'
import type { EmployeeLeaveBalance } from '@/hooks/useLeave'
import { useAuthStore } from '@/stores/authStore'
import { toast } from 'sonner'
import { X, AlertTriangle } from 'lucide-react'

type BalanceItem = EmployeeLeaveBalance['balances'][number]

interface FileLeaveModalProps {
  isOpen: boolean
  onClose: () => void
  onSuccess?: () => void
  balances?: BalanceItem[]
}

export default function FileLeaveModal({ isOpen, onClose, onSuccess, balances = [] }: FileLeaveModalProps) {
  const { user } = useAuthStore()
  const employeeId = user?.employee_id as number | undefined
  
  const [formData, setFormData] = useState({
    leave_type_id: '',
    date_from: new Date().toISOString().slice(0, 10),
    date_to: new Date().toISOString().slice(0, 10),
    is_half_day: false,
    half_day_period: 'am' as 'am' | 'pm',
    reason: '',
  })
  
  const { data: leaveTypes } = useLeaveTypes()
  const createMutation = useCreateLeaveRequest()

  // Find balance for currently selected leave type
  const selectedBalance: BalanceItem | undefined = formData.leave_type_id
    ? balances.find((b) => b.leave_type_id === parseInt(formData.leave_type_id))
    : undefined

  // Detect OTH (Others) — discretionary, no fixed balance
  const selectedLeaveType = (leaveTypes ?? []).find((t) => t.id === parseInt(formData.leave_type_id))
  const isOth = selectedLeaveType?.code === 'OTH'
  const hasInsufficientBalance = selectedBalance != null && !isOth && selectedBalance.balance <= 0
  
  if (!isOpen) return null
  
  const calculateDays = () => {
    const from = new Date(formData.date_from)
    const to = new Date(formData.date_to)
    const diffTime = to.getTime() - from.getTime()
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1
    return diffDays > 0 ? diffDays : 0
  }
  
  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    
    if (!employeeId) {
      toast.error('No employee profile linked to your account')
      return
    }
    
    if (!formData.leave_type_id) {
      toast.error('Please select a leave type')
      return
    }
    
    const days = calculateDays()
    if (days <= 0) {
      toast.error('End date must be on or after start date')
      return
    }
    
    try {
      const payload: Record<string, unknown> = {
        employee_id: employeeId,
        leave_type_id: parseInt(formData.leave_type_id),
        date_from: formData.date_from,
        date_to: formData.date_to,
        total_days: formData.is_half_day ? 0.5 : days,
        is_half_day: formData.is_half_day,
        reason: formData.reason,
      }
      
      if (formData.is_half_day) {
        payload.half_day_period = formData.half_day_period
      }
      
      await createMutation.mutateAsync(payload)
      
      toast.success('Leave request filed successfully')
      setFormData({
        leave_type_id: '',
        date_from: new Date().toISOString().slice(0, 10),
        date_to: new Date().toISOString().slice(0, 10),
        is_half_day: false,
        half_day_period: 'am',
        reason: '',
      })
      onSuccess?.()
      onClose()
    } catch (error: unknown) {
      const err = error as { response?: { data?: { message?: string } } }
      toast.error(err.response?.data?.message || 'Failed to file leave request')
    }
  }
  
  const totalDays = calculateDays()
  
  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50">
      <div className="bg-white rounded-lg shadow-lg max-w-md w-full max-h-[90vh] overflow-y-auto border border-neutral-200">
        <div className="px-4 py-3 border-b border-neutral-200 flex items-center justify-between">
          <h2 className="text-base font-semibold text-neutral-900">File Leave Request</h2>
          <button onClick={onClose} className="text-neutral-400 hover:text-neutral-600">
            <X className="h-4 w-4" />
          </button>
        </div>
        
        <form onSubmit={handleSubmit} className="p-4 space-y-3">
          {/* Leave Type */}
          <div>
            <label className="block text-sm font-medium text-neutral-700 mb-1">
              Leave Type <span className="text-red-500">*</span>
            </label>
            <select
              value={formData.leave_type_id}
              onChange={(e) => setFormData({ ...formData, leave_type_id: e.target.value })}
              className="w-full border border-neutral-300 rounded px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-neutral-400"
              required
              size={leaveTypes && leaveTypes.length > 10 ? 1 : undefined}
            >
              <option value="">Select leave type...</option>
              {(leaveTypes ?? []).map((type) => {
                const bal = balances.find((b) => b.leave_type_id === type.id)
                const isLwopType = type.code === 'LWOP'
                const label = bal && !isLwopType
                  ? `${type.name} (${bal.balance} day${bal.balance !== 1 ? 's' : ''} left)`
                  : type.name
                return (
                  <option key={type.id} value={type.id}>{label}</option>
                )
              })}
            </select>

            {/* Inline balance warning */}
            {hasInsufficientBalance && (
              <div className="mt-2 flex items-start gap-2 rounded bg-red-50 border border-red-200 px-3 py-2">
                <AlertTriangle className="h-4 w-4 text-red-500 mt-0.5 shrink-0" />
                <p className="text-xs text-red-700">
                  You have <strong>no remaining balance</strong> for{' '}
                  <strong>{selectedBalance?.leave_type_name}</strong>. Filing this request will be rejected.
                </p>
              </div>
            )}

            {/* OTH hint */}
            {isOth && (
              <div className="mt-2 flex items-start gap-2 rounded bg-amber-50 border border-amber-200 px-3 py-2">
                <AlertTriangle className="h-4 w-4 text-amber-500 mt-0.5 shrink-0" />
                <p className="text-xs text-amber-700">
                  Please <strong>specify the type of leave</strong> in the Reason field below
                  (e.g., "Graduation Ceremony", "Court Appearance", "Emergency Travel").
                  The GA Officer will determine if it is with or without pay.
                </p>
              </div>
            )}

            {/* Show balance when selected and has credits */}
            {selectedBalance && !isOth && selectedBalance.balance > 0 && (
              <p className="mt-1.5 text-xs text-green-700 font-medium">
                {selectedBalance.balance} day{selectedBalance.balance !== 1 ? 's' : ''} available
              </p>
            )}
          </div>
          
          {/* Date Range */}
          <div className="grid grid-cols-2 gap-3">
            <div>
              <label className="block text-sm font-medium text-neutral-700 mb-1">
                From <span className="text-red-500">*</span>
              </label>
              <input
                type="date"
                value={formData.date_from}
                onChange={(e) => setFormData({ ...formData, date_from: e.target.value })}
                className="w-full border border-neutral-300 rounded px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-neutral-400"
                required
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-neutral-700 mb-1">
                To <span className="text-red-500">*</span>
              </label>
              <input
                type="date"
                value={formData.date_to}
                min={formData.date_from}
                onChange={(e) => setFormData({ ...formData, date_to: e.target.value })}
                className="w-full border border-neutral-300 rounded px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-neutral-400"
                required
              />
            </div>
          </div>
          
          {/* Half Day Option */}
          <div className="flex items-center gap-3">
            <label className="flex items-center gap-2 cursor-pointer">
              <input
                type="checkbox"
                checked={formData.is_half_day}
                onChange={(e) => setFormData({ ...formData, is_half_day: e.target.checked })}
                className="rounded border-neutral-300 text-neutral-600 focus:ring-neutral-400"
              />
              <span className="text-sm text-neutral-700">Half Day</span>
            </label>
            
            {formData.is_half_day && (
              <select
                value={formData.half_day_period}
                onChange={(e) => setFormData({ ...formData, half_day_period: e.target.value as 'am' | 'pm' })}
                className="text-sm border border-neutral-300 rounded px-2 py-1 focus:outline-none focus:ring-1 focus:ring-neutral-400"
              >
                <option value="am">Morning (AM)</option>
                <option value="pm">Afternoon (PM)</option>
              </select>
            )}
          </div>
          
          {/* Days Summary */}
          <div className="bg-neutral-50 border border-neutral-200 rounded px-3 py-2">
            <span className="text-sm text-neutral-700">
              Total Days: <strong>{formData.is_half_day ? 0.5 : totalDays}</strong>
            </span>
          </div>
          
          {/* Reason */}
          <div>
            <label className="block text-sm font-medium text-neutral-700 mb-1">
              Reason <span className="text-red-500">*</span>
            </label>
            <textarea
              value={formData.reason}
              onChange={(e) => setFormData({ ...formData, reason: e.target.value })}
              placeholder={isOth
                ? "Specify your leave type and reason (e.g., 'Graduation Ceremony — attending sibling's graduation')"
                : "Explain the reason for leave..."}
              className="w-full border border-neutral-300 rounded px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-neutral-400 resize-none"
              rows={2}
              required
            />
          </div>
          
          {/* Actions */}
          <div className="flex gap-3 pt-2">
            <button
              type="button"
              onClick={onClose}
              className="flex-1 px-4 py-1.5 border border-neutral-300 text-neutral-700 rounded hover:bg-neutral-50 text-sm font-medium"
            >
              Cancel
            </button>
            <button
              type="submit"
              disabled={createMutation.isPending}
              className="flex-1 px-4 py-1.5 bg-neutral-900 text-white rounded hover:bg-neutral-800 disabled:opacity-50 text-sm font-medium"
            >
              {createMutation.isPending ? 'Filing...' : 'File Request'}
            </button>
          </div>
        </form>
      </div>
    </div>
  )
}
