import { useMemo, useState } from 'react'
import { useCreateOvertimeRequest } from '@/hooks/useAttendance'
import { useAuthStore } from '@/stores/authStore'
import { toast } from 'sonner'
import { X, Clock, ArrowRight, Timer, Zap } from 'lucide-react'

interface FileOvertimeModalProps {
  isOpen: boolean
  onClose: () => void
  onSuccess?: () => void
}

const OT_SHIFT_END = '17:00' // regular shift ends 5 PM

const QUICK_DURATIONS = [
  { label: '30 min', minutes: 30 },
  { label: '1 hr',   minutes: 60 },
  { label: '1.5 hr', minutes: 90 },
  { label: '2 hrs',  minutes: 120 },
  { label: '3 hrs',  minutes: 180 },
  { label: '4 hrs',  minutes: 240 },
]

function toMinutes(time: string): number {
  const [h, m] = time.split(':').map(Number)
  return (h ?? 0) * 60 + (m ?? 0)
}

function minutesToTime(totalMinutes: number): string {
  const h = Math.floor(totalMinutes / 60) % 24
  const m = totalMinutes % 60
  return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}`
}

function formatDuration(minutes: number): string {
  if (minutes <= 0) return '—'
  const h = Math.floor(minutes / 60)
  const m = minutes % 60
  const parts: string[] = []
  if (h > 0) parts.push(`${h}h`)
  if (m > 0) parts.push(`${m}m`)
  return parts.join(' ')
}

function fmt12(time: string): string {
  if (!time) return ''
  const [h, m] = time.split(':').map(Number)
  const ampm = (h ?? 0) >= 12 ? 'PM' : 'AM'
  const hour = (h ?? 0) % 12 || 12
  return `${hour}:${String(m ?? 0).padStart(2, '0')} ${ampm}`
}

export default function FileOvertimeModal({ isOpen, onClose, onSuccess }: FileOvertimeModalProps) {
  const { user } = useAuthStore()
  const employeeId = user?.employee_id as number | undefined

  const [formData, setFormData] = useState({
    work_date: new Date().toISOString().slice(0, 10),
    ot_start_time: OT_SHIFT_END,
    ot_end_time: '',
    reason: '',
  })

  const createMutation = useCreateOvertimeRequest()

  const durationMinutes = useMemo(() => {
    if (!formData.ot_start_time || !formData.ot_end_time) return 0
    return toMinutes(formData.ot_end_time) - toMinutes(formData.ot_start_time)
  }, [formData.ot_start_time, formData.ot_end_time])

  // Which quick-pick is currently active (exact match)
  const activePreset = useMemo(() => {
    if (!formData.ot_start_time || !formData.ot_end_time) return null
    return QUICK_DURATIONS.find(p => p.minutes === durationMinutes) ?? null
  }, [formData.ot_start_time, formData.ot_end_time, durationMinutes])

  if (!isOpen) return null

  const applyPreset = (minutes: number) => {
    const end = minutesToTime(toMinutes(formData.ot_start_time) + minutes)
    setFormData(f => ({ ...f, ot_end_time: end }))
  }

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()

    if (!employeeId) {
      toast.error('No employee profile linked to your account')
      return
    }

    if (durationMinutes <= 0) {
      toast.error('OT end time must be after the start time')
      return
    }

    if (durationMinutes > 480) {
      toast.error('Overtime cannot exceed 8 hours (480 minutes) per day')
      return
    }

    try {
      await createMutation.mutateAsync({
        employee_id: employeeId,
        work_date: formData.work_date,
        ot_start_time: formData.ot_start_time,
        ot_end_time: formData.ot_end_time,
        reason: formData.reason,
      })

      toast.success('Overtime request filed successfully')
      setFormData({
        work_date: new Date().toISOString().slice(0, 10),
        ot_start_time: OT_SHIFT_END,
        ot_end_time: '',
        reason: '',
      })
      onSuccess?.()
      onClose()
    } catch (error: unknown) {
      const err = error as { response?: { data?: { message?: string } } }
      toast.error(err.response?.data?.message || 'Failed to file overtime request')
    }
  }

  return (
    <div className="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50 p-4">
      <div className="bg-white rounded-2xl shadow-xl max-w-md w-full">
        <div className="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
          <div className="flex items-center gap-2">
            <Clock className="h-5 w-5 text-blue-600" />
            <h2 className="text-lg font-semibold text-gray-900">File Overtime Request</h2>
          </div>
          <button onClick={onClose} className="text-gray-400 hover:text-gray-600">
            <X className="h-5 w-5" />
          </button>
        </div>

        <form onSubmit={handleSubmit} className="p-6 space-y-4">
          {/* Work Date */}
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">
              Work Date <span className="text-red-500">*</span>
            </label>
            <input
              type="date"
              value={formData.work_date}
              onChange={(e) => setFormData({ ...formData, work_date: e.target.value })}
              className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none"
              required
            />
          </div>

          {/* OT Time Range */}
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-0.5">
              Overtime Hours <span className="text-red-500">*</span>
            </label>
            <p className="text-xs text-gray-400 mb-3">
              Regular shift ends at <span className="font-medium text-gray-500">5:00 PM</span>. Adjust if your OT started at a different time.
            </p>

            {/* Start / End time row */}
            <div className="flex items-end gap-2 mb-3">
              <div className="flex-1">
                <div className="text-xs font-medium text-gray-500 mb-1">OT started at</div>
                <input
                  type="time"
                  value={formData.ot_start_time}
                  onChange={(e) => setFormData({ ...formData, ot_start_time: e.target.value, ot_end_time: '' })}
                  className="w-full border border-blue-300 bg-blue-50 text-blue-900 rounded-lg px-3 py-2 text-sm font-medium focus:ring-2 focus:ring-blue-500 outline-none"
                  required
                />
              </div>
              <div className="pb-2 text-gray-400">
                <ArrowRight className="h-4 w-4" />
              </div>
              <div className="flex-1">
                <div className="text-xs font-medium text-gray-500 mb-1">OT ended at</div>
                <input
                  type="time"
                  value={formData.ot_end_time}
                  onChange={(e) => setFormData({ ...formData, ot_end_time: e.target.value })}
                  className={`w-full border rounded-lg px-3 py-2 text-sm font-medium focus:ring-2 focus:ring-blue-500 outline-none transition-colors ${
                    formData.ot_end_time && durationMinutes > 0
                      ? 'border-blue-300 bg-blue-50 text-blue-900'
                      : formData.ot_end_time && durationMinutes <= 0
                      ? 'border-red-300 bg-red-50 text-red-900'
                      : 'border-gray-300 text-gray-700'
                  }`}
                  required
                />
              </div>
            </div>

            {/* Quick-pick presets */}
            <div className="bg-gray-50 rounded-xl p-3 border border-gray-200">
              <div className="flex items-center gap-1.5 mb-2 text-xs font-medium text-gray-500">
                <Zap className="h-3 w-3" /> Quick pick — how long did you work OT?
              </div>
              <div className="grid grid-cols-3 gap-1.5">
                {QUICK_DURATIONS.map((p) => {
                  const endTime = minutesToTime(toMinutes(formData.ot_start_time) + p.minutes)
                  const isActive = activePreset?.minutes === p.minutes
                  return (
                    <button
                      key={p.minutes}
                      type="button"
                      onClick={() => applyPreset(p.minutes)}
                      className={`rounded-lg px-2 py-2 text-xs font-medium text-center transition-all border ${
                        isActive
                          ? 'bg-blue-600 text-white border-blue-600 shadow-sm'
                          : 'bg-white text-gray-700 border-gray-200 hover:border-blue-400 hover:text-blue-600'
                      }`}
                    >
                      <div className="font-semibold">{p.label}</div>
                      <div className={`text-[10px] mt-0.5 ${isActive ? 'text-blue-200' : 'text-gray-400'}`}>
                        until {fmt12(endTime)}
                      </div>
                    </button>
                  )
                })}
              </div>
            </div>

            {/* Duration summary */}
            <div className="mt-2">
              {formData.ot_start_time && formData.ot_end_time ? (
                durationMinutes > 0 ? (
                  <div className="flex items-center gap-2 bg-blue-50 border border-blue-200 rounded-lg px-3 py-2">
                    <Timer className="h-4 w-4 text-blue-500 flex-shrink-0" />
                    <span className="text-sm text-blue-700">
                      {fmt12(formData.ot_start_time)} → {fmt12(formData.ot_end_time)}
                      {' · '}<span className="font-semibold">{formatDuration(durationMinutes)}</span>
                    </span>
                    {durationMinutes > 480 && (
                      <span className="ml-auto text-xs text-red-500 font-medium">Exceeds 8h limit</span>
                    )}
                  </div>
                ) : (
                  <div className="flex items-center gap-2 bg-red-50 border border-red-200 rounded-lg px-3 py-2">
                    <X className="h-4 w-4 text-red-500 flex-shrink-0" />
                    <span className="text-sm text-red-600">End time must be after start time</span>
                  </div>
                )
              ) : null}
            </div>
          </div>

          {/* Reason */}
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">
              Reason <span className="text-red-500">*</span>
            </label>
            <textarea
              value={formData.reason}
              onChange={(e) => setFormData({ ...formData, reason: e.target.value })}
              placeholder="Explain the reason for overtime..."
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
