import { useState } from 'react'
import { useCreateLeaveRequest, useLeaveTypes } from '@/hooks/useLeave'
import type { EmployeeLeaveBalance } from '@/hooks/useLeave'
import { useAuthStore } from '@/stores/authStore'
import { toast } from 'sonner'
import { X, Calendar, AlertTriangle } from 'lucide-react'

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

  const isLwop = selectedBalance?.leave_type_code === 'LWOP'
  const hasInsufficientBalance = selectedBalance != null && !isLwop && selectedBalance.balance <= 0
  
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
    <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div className="bg-white rounded-xl shadow-xl max-w-md w-full">
        <div className="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
          <div className="flex items-center gap-2">
            <Calendar className="h-5 w-5 text-blue-600" />
            <h2 className="text-lg font-semibold text-gray-900">File Leave Request</h2>
          </div>
          <button onClick={onClose} className="text-gray-400 hover:text-gray-600">
            <X className="h-5 w-5" />
          </button>
        </div>
        
        <form onSubmit={handleSubmit} className="p-6 space-y-4">
          {/* Leave Type */}
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">
              Leave Type <span className="text-red-500">*</span>
            </label>
            <select
              value={formData.leave_type_id}
              onChange={(e) => setFormData({ ...formData, leave_type_id: e.target.value })}
              className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none"
              required
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
              <div className="mt-2 flex items-start gap-2 rounded-lg bg-red-50 border border-red-200 px-3 py-2">
                <AlertTriangle className="h-4 w-4 text-red-500 mt-0.5 shrink-0" />
                <p className="text-xs text-red-700">
                  You have <strong>no remaining balance</strong> for{' '}
                  <strong>{selectedBalance?.leave_type_name}</strong>. Filing this request will be rejected.
                </p>
              </div>
            )}

            {/* Show balance when selected and has credits */}
            {selectedBalance && !isLwop && selectedBalance.balance > 0 && (
              <p className="mt-1.5 text-xs text-green-700 font-medium">
                ✓ {selectedBalance.balance} day{selectedBalance.balance !== 1 ? 's' : ''} available
              </p>
            )}
          </div>
          
          {/* Date Range */}
          <div className="grid grid-cols-2 gap-3">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">
                From <span className="text-red-500">*</span>
              </label>
              <input
                type="date"
                value={formData.date_from}
                onChange={(e) => setFormData({ ...formData, date_from: e.target.value })}
                className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none"
                required
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">
                To <span className="text-red-500">*</span>
              </label>
              <input
                type="date"
                value={formData.date_to}
                min={formData.date_from}
                onChange={(e) => setFormData({ ...formData, date_to: e.target.value })}
                className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none"
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
                className="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
              />
              <span className="text-sm text-gray-700">Half Day</span>
            </label>
            
            {formData.is_half_day && (
              <select
                value={formData.half_day_period}
                onChange={(e) => setFormData({ ...formData, half_day_period: e.target.value as 'am' | 'pm' })}
                className="text-sm border border-gray-300 rounded-lg px-2 py-1 focus:ring-2 focus:ring-blue-500 outline-none"
              >
                <option value="am">Morning (AM)</option>
                <option value="pm">Afternoon (PM)</option>
              </select>
            )}
          </div>
          
          {/* Days Summary */}
          <div className="bg-blue-50 border border-blue-100 rounded-lg px-3 py-2">
            <span className="text-sm text-blue-800">
              Total Days: <strong>{formData.is_half_day ? 0.5 : totalDays}</strong>
            </span>
          </div>
          
          {/* Reason */}
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">
              Reason <span className="text-red-500">*</span>
            </label>
            <textarea
              value={formData.reason}
              onChange={(e) => setFormData({ ...formData, reason: e.target.value })}
              placeholder="Explain the reason for leave..."
              className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none resize-none"
              rows={3}
              required
            />
          </div>
          
          {/* Actions */}
          <div className="flex gap-3 pt-2">
            <button
              type="button"
              onClick={onClose}
              className="flex-1 px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 text-sm font-medium"
            >
              Cancel
            </button>
            <button
              type="submit"
              disabled={createMutation.isPending}
              className="flex-1 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50 text-sm font-medium"
            >
              {createMutation.isPending ? 'Filing...' : 'File Request'}
            </button>
          </div>
        </form>
      </div>
    </div>
  )
}
