import { toast } from 'sonner'
import { useRecurringTemplates, useToggleRecurringTemplate, useDeleteRecurringTemplate, type RecurringJournalTemplate } from '@/hooks/useAccounting'
import { Power, Trash2 } from 'lucide-react'
import { useAuthStore } from '@/stores/authStore'

export default function RecurringTemplatesPage(): React.ReactElement {
  const { data: templates, isLoading } = useRecurringTemplates()
  const toggle = useToggleRecurringTemplate()
  const remove = useDeleteRecurringTemplate()

  const list: RecurringJournalTemplate[] = templates ?? []
  const canManage = useAuthStore((s) => s.hasPermission('journal_entries.create'))

  async function handleToggle(id: string) {
    try {
      const result = await toggle.mutateAsync(id)
      toast.success(`Template ${result.is_active ? 'activated' : 'deactivated'}.`)
    } catch {
      toast.error('Toggle failed.')
    }
  }

  async function handleDelete(id: string) {
    if (!confirm('Delete this recurring template?')) return
    try {
      await remove.mutateAsync(id)
      toast.success('Template deleted.')
    } catch {
      toast.error('Delete failed.')
    }
  }

  if (isLoading) return <p className="text-sm text-neutral-500 mt-4">Loading…</p>

  return (
    <div className="max-w-5xl mx-auto">
      <div className="mb-4">
        <h1 className="text-2xl font-bold text-neutral-900">Recurring Journal Templates</h1>
        <p className="text-sm text-neutral-500">Manage templates that auto-generate journal entries on schedule.</p>
      </div>

      {list.length === 0 ? (
        <div className="bg-white border border-neutral-200 rounded-lg px-6 py-12 text-center">
          <p className="text-neutral-500 text-sm">No recurring templates defined.</p>
        </div>
      ) : (
        <div className="bg-white border border-neutral-200 rounded-lg overflow-hidden">
          <table className="w-full text-sm">
            <thead className="bg-neutral-50 border-b border-neutral-200">
              <tr>
                {['Description', 'Frequency', 'Next Run', 'Status', 'Actions'].map((h) => (
                  <th key={h} className="text-left px-4 py-2 text-xs font-medium text-neutral-600 uppercase">{h}</th>
                ))}
              </tr>
            </thead>
            <tbody>
              {list.map((t) => (
                <tr key={t.id} className="border-b border-neutral-100 last:border-0 hover:bg-neutral-50">
                  <td className="px-4 py-3 text-neutral-800 font-medium">{t.description}</td>
                  <td className="px-4 py-3 text-neutral-600 capitalize">{t.frequency}</td>
                  <td className="px-4 py-3 text-neutral-600">{t.next_run_date}</td>
                  <td className="px-4 py-3">
                    <span className={`px-2 py-0.5 rounded text-xs font-medium ${t.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-neutral-100 text-neutral-500'}`}>
                      {t.is_active ? 'Active' : 'Inactive'}
                    </span>
                  </td>
                  <td className="px-4 py-3 flex items-center gap-2">
                    {canManage && (
                      <>
                        <button onClick={() => handleToggle(t.ulid)} title={t.is_active ? 'Deactivate' : 'Activate'}
                          className="text-neutral-500 hover:text-neutral-800">
                          <Power className="w-4 h-4" />
                        </button>
                        <button onClick={() => handleDelete(t.ulid)} title="Delete"
                          className="text-neutral-400 hover:text-red-600">
                          <Trash2 className="w-4 h-4" />
                        </button>
                      </>
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </div>
  )
}
