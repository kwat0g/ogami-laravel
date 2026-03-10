import { useState } from 'react'
import { toast } from 'sonner'
import {
  useShifts,
  useCreateShift,
  useUpdateShift,
  useDeleteShift,
} from '@/hooks/useAttendance'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import { PageHeader } from '@/components/ui/PageHeader'
import type { ShiftSchedule } from '@/types/hr'

interface ShiftForm {
  id?: number
  name: string
  description: string
  start_time: string
  end_time: string
  break_minutes: number
  work_days_arr: number[]   // UI-only: parsed from work_days string
  is_night_shift: boolean
  is_flexible: boolean
  grace_period_minutes: number
  is_active: boolean
}

const DAYS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']
const STD_WORK_DAYS = [1, 2, 3, 4, 5] // Mon-Fri

const emptyForm = (): ShiftForm => ({
  name: '',
  description: '',
  start_time: '08:00',
  end_time: '17:00',
  break_minutes: 60,
  work_days_arr: STD_WORK_DAYS,
  is_night_shift: false,
  is_flexible: false,
  grace_period_minutes: 0,
  is_active: true,
})

function shiftFromExisting(s: ShiftSchedule): ShiftForm {
  return {
    id: s.id,
    name: s.name,
    description: s.description ?? '',
    start_time: s.start_time,
    end_time: s.end_time,
    break_minutes: s.break_minutes,
    work_days_arr: s.work_days ? s.work_days.split(',').map(Number).filter(Boolean) : STD_WORK_DAYS,
    is_night_shift: s.is_night_shift,
    is_flexible: s.is_flexible,
    grace_period_minutes: s.grace_period_minutes,
    is_active: s.is_active,
  }
}

export default function ShiftsPage() {
  const { data, isLoading, isError } = useShifts()
  const create = useCreateShift()
  const upd    = useUpdateShift()
  const remove = useDeleteShift()

  const [form, setForm]         = useState<ShiftForm | null>(null)
  const [formError, setFormError] = useState<string | null>(null)

  const rows = data?.data ?? []

  const _openCreate = () => { setForm(emptyForm()); setFormError(null) }
  const openEdit   = (s: ShiftSchedule) => { setForm(shiftFromExisting(s)); setFormError(null) }
  const closeForm  = () => setForm(null)

  const set = <K extends keyof ShiftForm>(field: K, value: ShiftForm[K]) =>
    setForm((f) => f ? { ...f, [field]: value } : f)

  const toggleDay = (day: number) => {
    if (!form) return
    const days = form.work_days_arr.includes(day)
      ? form.work_days_arr.filter((d) => d !== day)
      : [...form.work_days_arr, day].sort((a, b) => a - b)
    set('work_days_arr', days)
  }

  const handleSave = () => {
    if (!form) return
    setFormError(null)
    if (!form.name.trim()) { setFormError('Shift name is required.'); return }
    if (form.id) {
      const { work_days_arr, ...rest } = form
      upd.mutate({ ...rest, id: form.id, work_days: work_days_arr.join(',') }, { onSuccess: () => { toast.success('Shift updated.'); closeForm() }, onError: () => { toast.error('Failed to update shift.'); setFormError('Update failed.') } })
    } else {
      const { id: _id, work_days_arr, is_night_shift: _is_night_shift, ...rest } = form
      create.mutate({ ...rest, work_days: work_days_arr.join(','), description: rest.description || null }, { onSuccess: () => { toast.success('Shift created.'); closeForm() }, onError: () => { toast.error('Failed to create shift.'); setFormError('Create failed.') } })
    }
  }

  if (isLoading) return <SkeletonLoader rows={6} />
  if (isError)   return <div className="text-red-600 text-sm mt-4">Failed to load shifts.</div>

  return (
    <div>
      <PageHeader title="Shift Schedules" />

      {/* Cards */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        {rows.length === 0 && (
          <div className="col-span-3 bg-white border border-neutral-200 rounded-lg p-8 text-center text-neutral-400">No shifts configured yet.</div>
        )}
        {rows.map((shift) => (
          <div key={shift.id} className="bg-white border border-neutral-200 rounded-lg p-5">
            <div className="flex items-start justify-between mb-3">
              <div>
                <h3 className="font-semibold text-neutral-900">{shift.name}</h3>
                <p className="text-sm text-neutral-500">{shift.start_time} – {shift.end_time}</p>
              </div>
              <span className={`inline-flex items-center rounded px-2 py-0.5 text-xs font-medium ${shift.is_active ? 'bg-green-100 text-green-700' : 'bg-neutral-100 text-neutral-500'}`}>
                {shift.is_active ? 'Active' : 'Inactive'}
              </span>
            </div>

            <div className="flex gap-1 mb-3">
              {DAYS.map((d, i) => {
                const activeDays = shift.work_days ? shift.work_days.split(',').map(Number) : []
                return (
                  <span key={d} className={`w-6 h-6 flex items-center justify-center text-xs rounded font-medium
                    ${activeDays.includes(i) ? 'bg-neutral-800 text-white' : 'bg-neutral-100 text-neutral-400'}`}>
                    {d[0]}
                  </span>
                )
              })}
            </div>

            <div className="flex flex-wrap gap-2 text-xs text-neutral-500 mb-4">
              {shift.break_minutes > 0 && <span>Break: {shift.break_minutes} min</span>}
              {shift.grace_period_minutes > 0 && <span>Grace: {shift.grace_period_minutes} min</span>}
              {shift.is_night_shift && <span className="text-purple-600">Night Shift</span>}
              {shift.is_flexible && <span className="text-teal-600">Flexible</span>}
            </div>

            <div className="flex gap-3">
              <button onClick={() => openEdit(shift)} className="text-xs text-neutral-600 hover:underline">Edit</button>
              <button onClick={() => confirm('Delete this shift?') && remove.mutate(shift.id, { onSuccess: () => toast.success('Shift deleted.'), onError: () => toast.error('Failed to delete shift.') })} disabled={remove.isPending} className="text-xs text-red-500 hover:underline disabled:opacity-50 disabled:cursor-not-allowed">Delete</button>
            </div>
          </div>
        ))}
      </div>

      {/* Modal */}
      {form !== null && (
        <div className="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4">
          <div className="bg-white rounded-lg border border-neutral-200 p-6 w-full max-w-lg max-h-screen overflow-y-auto">
            <h2 className="text-lg font-semibold text-neutral-900 mb-4">{form.id ? 'Edit Shift' : 'New Shift'}</h2>
            {formError && <div className="text-red-600 text-sm mb-3 bg-red-50 rounded px-3 py-2">{formError}</div>}
            <div className="space-y-4">
              <div>
                <label className="block text-sm font-medium text-neutral-700 mb-1">Shift Name</label>
                <input value={form.name} onChange={(e) => set('name', e.target.value)}
                  className="w-full border border-neutral-300 rounded px-3 py-2 text-sm outline-none focus:ring-1 focus:ring-neutral-400" />
              </div>

              <div>
                <label className="block text-sm font-medium text-neutral-700 mb-1">Description <span className="text-neutral-400">(optional)</span></label>
                <input value={form.description} onChange={(e) => set('description', e.target.value)}
                  className="w-full border border-neutral-300 rounded px-3 py-2 text-sm outline-none focus:ring-1 focus:ring-neutral-400" />
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-sm font-medium text-neutral-700 mb-1">Start Time</label>
                  <input type="time" value={form.start_time} onChange={(e) => set('start_time', e.target.value)}
                    className="w-full border border-neutral-300 rounded px-3 py-2 text-sm outline-none focus:ring-1 focus:ring-neutral-400" />
                </div>
                <div>
                  <label className="block text-sm font-medium text-neutral-700 mb-1">End Time</label>
                  <input type="time" value={form.end_time} onChange={(e) => set('end_time', e.target.value)}
                    className="w-full border border-neutral-300 rounded px-3 py-2 text-sm outline-none focus:ring-1 focus:ring-neutral-400" />
                </div>
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-sm font-medium text-neutral-700 mb-1">Break (minutes)</label>
                  <input type="number" min="0" value={form.break_minutes} onChange={(e) => set('break_minutes', Number(e.target.value))}
                    className="w-full border border-neutral-300 rounded px-3 py-2 text-sm outline-none focus:ring-1 focus:ring-neutral-400" />
                </div>
                <div>
                  <label className="block text-sm font-medium text-neutral-700 mb-1">Grace Period (minutes)</label>
                  <input type="number" min="0" value={form.grace_period_minutes} onChange={(e) => set('grace_period_minutes', Number(e.target.value))}
                    className="w-full border border-neutral-300 rounded px-3 py-2 text-sm outline-none focus:ring-1 focus:ring-neutral-400" />
                </div>
              </div>

              {/* Work days */}
              <div>
                <label className="block text-sm font-medium text-neutral-700 mb-2">Work Days</label>
                <div className="flex gap-2">
                  {DAYS.map((d, i) => (
                    <button
                      key={d}
                      type="button"
                      onClick={() => toggleDay(i)}
                      className={`w-8 h-8 rounded text-xs font-medium transition-colors
                        ${form.work_days_arr.includes(i) ? 'bg-neutral-800 text-white' : 'bg-neutral-100 text-neutral-500 hover:bg-neutral-200'}`}
                    >
                      {d[0]}
                    </button>
                  ))}
                </div>
              </div>

              <div className="flex gap-6">
                <label className="flex items-center gap-2 text-sm text-neutral-700 cursor-pointer">
                  <input type="checkbox" checked={form.is_night_shift} onChange={(e) => set('is_night_shift', e.target.checked)} className="rounded" />
                  Night Shift
                </label>
                <label className="flex items-center gap-2 text-sm text-neutral-700 cursor-pointer">
                  <input type="checkbox" checked={form.is_flexible} onChange={(e) => set('is_flexible', e.target.checked)} className="rounded" />
                  Flexible Hours
                </label>
                <label className="flex items-center gap-2 text-sm text-neutral-700 cursor-pointer">
                  <input type="checkbox" checked={form.is_active} onChange={(e) => set('is_active', e.target.checked)} className="rounded" />
                  Active
                </label>
              </div>
            </div>
            <div className="flex justify-end gap-3 mt-5">
              <button onClick={closeForm} className="px-4 py-2 text-sm text-neutral-600 hover:bg-neutral-100 rounded">Cancel</button>
              <button onClick={handleSave} disabled={create.isPending || upd.isPending}
                className="px-4 py-2 text-sm bg-neutral-900 hover:bg-neutral-800 text-white rounded disabled:opacity-50 disabled:cursor-not-allowed">
                {form.id ? 'Save Changes' : 'Create Shift'}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}
