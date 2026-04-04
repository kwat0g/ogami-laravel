import { useState } from 'react'
import { PageHeader } from '@/components/ui/PageHeader'
import { useBirFilings, useScheduleBirFiling, useMarkBirFiled, useMarkBirAmended, useBirFilingOverdue } from '@/hooks/useTax'
import { toast } from 'sonner'

export default function BirFilingListPage() {
  const [filters, setFilters] = useState<{ status?: string; fiscal_year?: number }>({})
  const { data: filings, isLoading } = useBirFilings(filters)
  const { data: overdue } = useBirFilingOverdue()
  const scheduleMut = useScheduleBirFiling()
  const [showSchedule, setShowSchedule] = useState(false)
  const [schedForm, setSchedForm] = useState({ form_type: '1601C', period: '', due_date: '', fiscal_year: new Date().getFullYear() })

  const statusColors: Record<string, string> = {
    pending: 'bg-yellow-100 text-yellow-700',
    filed: 'bg-green-100 text-green-700',
    late: 'bg-red-100 text-red-700',
    amended: 'bg-blue-100 text-blue-700',
    cancelled: 'bg-neutral-100 text-neutral-500',
  }

  const formTypes = ['1601C', '0619E', '1601EQ', '2550M', '2550Q', '1702Q', '1702RT', '2307']

  const handleSchedule = async (e: React.FormEvent) => {
    e.preventDefault()
    try {
      await scheduleMut.mutateAsync({
        form_type: schedForm.form_type,
        period: schedForm.period,
        due_date: schedForm.due_date,
        fiscal_year: schedForm.fiscal_year,
      })
      toast.success('BIR filing scheduled.')
      setShowSchedule(false)
  }

  return (
    <div className="p-6 space-y-6">
      <div className="flex items-center justify-between">
        <PageHeader title="BIR Filing Tracker" />
        <button onClick={() => setShowSchedule(!showSchedule)} className="px-4 py-2 bg-blue-600 text-white text-sm rounded hover:bg-blue-700">
          {showSchedule ? 'Cancel' : '+ Schedule Filing'}
        </button>
      </div>

      {/* Overdue Alert */}
      {overdue && overdue.length > 0 && (
        <div className="bg-red-50 border border-red-200 rounded-lg p-4">
          <p className="text-sm font-medium text-red-800">
            {overdue.length} overdue filing{overdue.length > 1 ? 's' : ''} -- action required
          </p>
        </div>
      )}

      {/* Filters */}
      <div className="flex gap-3">
        <select onChange={e => setFilters({...filters, status: e.target.value || undefined})} className="border rounded px-3 py-2 text-sm">
          <option value="">All Statuses</option>
          <option value="pending">Pending</option>
          <option value="filed">Filed</option>
          <option value="late">Late</option>
          <option value="amended">Amended</option>
        </select>
        <select onChange={e => setFilters({...filters, fiscal_year: e.target.value ? Number(e.target.value) : undefined})} className="border rounded px-3 py-2 text-sm">
          <option value="">All Years</option>
          {[2026, 2025, 2024].map(y => <option key={y} value={y}>{y}</option>)}
        </select>
      </div>

      {showSchedule && (
        <form onSubmit={handleSchedule} className="bg-white dark:bg-neutral-800 rounded-lg border p-5 grid grid-cols-2 gap-4">
          <div>
            <label className="block text-sm font-medium mb-1">BIR Form *</label>
            <select value={schedForm.form_type} onChange={e => setSchedForm({...schedForm, form_type: e.target.value})} className="w-full border rounded px-3 py-2 text-sm">
              {formTypes.map(f => <option key={f} value={f}>{f}</option>)}
            </select>
          </div>
          <div>
            <label className="block text-sm font-medium mb-1">Period *</label>
            <input value={schedForm.period} onChange={e => setSchedForm({...schedForm, period: e.target.value})} required className="w-full border rounded px-3 py-2 text-sm" placeholder="2026-01 or Q1-2026" />
          </div>
          <div>
            <label className="block text-sm font-medium mb-1">Due Date *</label>
            <input type="date" value={schedForm.due_date} onChange={e => setSchedForm({...schedForm, due_date: e.target.value})} required className="w-full border rounded px-3 py-2 text-sm" />
          </div>
          <div className="flex items-end">
            <button type="submit" disabled={scheduleMut.isPending} className="px-4 py-2 bg-green-600 text-white text-sm rounded hover:bg-green-700 disabled:opacity-50">
              {scheduleMut.isPending ? 'Scheduling...' : 'Schedule'}
            </button>
          </div>
        </form>
      )}

      {isLoading ? (
        <div className="animate-pulse space-y-3">{[1,2,3].map(i => <div key={i} className="h-12 bg-neutral-200 rounded" />)}</div>
      ) : (
        <div className="bg-white dark:bg-neutral-800 rounded-lg border overflow-hidden">
          <table className="w-full text-sm">
            <thead className="bg-neutral-50 dark:bg-neutral-700">
              <tr>
                <th className="text-left px-4 py-3 font-medium">Form</th>
                <th className="text-left px-4 py-3 font-medium">Period</th>
                <th className="text-left px-4 py-3 font-medium">Due Date</th>
                <th className="text-left px-4 py-3 font-medium">Filed Date</th>
                <th className="text-center px-4 py-3 font-medium">Status</th>
                <th className="text-center px-4 py-3 font-medium">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y">
              {(filings ?? []).map((f: { id: number; form_type: string; period: string; due_date: string; filed_date?: string; status: string }) => (
                <tr key={f.id} className="hover:bg-neutral-50 dark:hover:bg-neutral-700/50">
                  <td className="px-4 py-3 font-mono font-medium">{f.form_type}</td>
                  <td className="px-4 py-3">{f.period}</td>
                  <td className="px-4 py-3">{f.due_date}</td>
                  <td className="px-4 py-3">{f.filed_date ?? '-'}</td>
                  <td className="px-4 py-3 text-center">
                    <span className={`px-2 py-0.5 rounded-full text-xs ${statusColors[f.status] ?? 'bg-neutral-100'}`}>
                      {f.status}
                    </span>
                  </td>
                  <td className="px-4 py-3 text-center space-x-2">
                    {f.status === 'pending' && <MarkFiledButton id={f.id} />}
                    {f.status === 'filed' && <MarkAmendedButton id={f.id} />}
                  </td>
                </tr>
              ))}
              {(!filings || filings.length === 0) && (
                <tr><td colSpan={6} className="px-4 py-8 text-center text-neutral-500">No BIR filings scheduled. Add one to start tracking.</td></tr>
              )}
            </tbody>
          </table>
        </div>
      )}
    </div>
  )
}

function MarkFiledButton({ id }: { id: number }) {
  const markFiled = useMarkBirFiled(id)
  return (
    <button
      disabled={markFiled.isPending}
      className="text-xs text-green-600 hover:underline disabled:opacity-50"
    >
      Mark Filed
    </button>
  )
}

function MarkAmendedButton({ id }: { id: number }) {
  const markAmended = useMarkBirAmended(id)
  return (
    <button
      disabled={markAmended.isPending}
      className="text-xs text-blue-600 hover:underline disabled:opacity-50"
    >
      Amend
    </button>
  )
}
