import { useState, useCallback } from 'react'
import { ShieldCheck } from 'lucide-react'
import { toast } from 'sonner'
import { useCapaActions, useCompleteCapaAction } from '@/hooks/useQC'
import { useAuthStore } from '@/stores/authStore'
import { PERMISSIONS } from '@/lib/permissions'
import { firstErrorMessage } from '@/lib/errorHandler'
import type { CapaAction, CapaStatus } from '@/types/qc'
import ConfirmDialog from '@/components/ui/ConfirmDialog'
import StatusBadge from '@/components/ui/StatusBadge'
import { Card, CardBody } from '@/components/ui/Card'
import { PageHeader } from '@/components/ui/PageHeader'
import SearchInput from '@/components/ui/SearchInput'
import SkeletonLoader from '@/components/ui/SkeletonLoader'

const TABS: Array<{ label: string; value: CapaStatus | 'all' }> = [
  { label: 'All',         value: 'all' },
  { label: 'Open',        value: 'open' },
  { label: 'In Progress', value: 'in_progress' },
  { label: 'Completed',   value: 'completed' },
  { label: 'Verified',    value: 'verified' },
]

export default function CapaListPage() {
  const [tab, setTab] = useState<CapaStatus | 'all'>('all')
  const [confirmCapa, setConfirmCapa] = useState<CapaAction | null>(null)
  const [verifyCapa, setVerifyCapa] = useState<CapaAction | null>(null)
  const [search, setSearch] = useState('')
  const [debouncedSearch, setDebouncedSearch] = useState('')
  const completeMut = useCompleteCapaAction()
  const { hasPermission } = useAuthStore()
  const canManage = hasPermission(PERMISSIONS.qc.ncr.create)

  const handleSearch = useCallback((val: string) => {
    setDebouncedSearch(val)
  }, [])

  const { data, isLoading } = useCapaActions({
    ...(tab !== 'all' ? { status: tab } : {}),
    ...(debouncedSearch ? { search: debouncedSearch } : {}),
  })

  const capas = data?.data ?? []

  const handleComplete = async () => {
    if (!confirmCapa) return
    try {
      await completeMut.mutateAsync(confirmCapa.id)
      toast.success('CAPA marked as completed.')
      setConfirmCapa(null)
    } catch (err: unknown) {
      toast.error(firstErrorMessage(err))
    }
  }

  const handleVerify = async () => {
    if (!verifyCapa) return
    try {
      await completeMut.mutateAsync(verifyCapa.id)
      toast.success('CAPA verified successfully.')
      setVerifyCapa(null)
    } catch (err: unknown) {
      toast.error(firstErrorMessage(err))
    }
  }

  return (
    <div>
      <PageHeader title="CAPA Actions" />

      <SearchInput
        value={search}
        onChange={setSearch}
        onSearch={handleSearch}
        placeholder="Search CAPA actions..."
        className="max-w-sm mb-4"
      />

      {/* Tabs */}
      <div className="flex gap-1 mb-5 border-b border-neutral-200">
        {TABS.map(t => (
          <button
            key={t.value}
            onClick={() => setTab(t.value)}
            className={`px-4 py-2.5 text-sm font-medium border-b-2 transition-colors ${
              tab === t.value
                ? 'border-neutral-800 text-neutral-800'
                : 'border-transparent text-neutral-500 hover:text-neutral-700'
            }`}
          >
            {t.label}
          </button>
        ))}
      </div>

      {isLoading && <SkeletonLoader rows={5} />}

      {!isLoading && capas.length === 0 && (
        <Card>
          <CardBody className="py-12 text-center">
            <ShieldCheck className="w-12 h-12 text-neutral-300 mx-auto mb-3" />
            <p className="text-neutral-500">No CAPA actions found.</p>
          </CardBody>
        </Card>
      )}

      {!isLoading && capas.length > 0 && (
        <Card>
          <CardBody className="p-0">
            <table className="min-w-full text-sm">
              <thead className="bg-neutral-50 border-b border-neutral-200">
                <tr>
                  <th className="px-4 py-3 text-left text-xs font-medium text-neutral-600">Type</th>
                  <th className="px-4 py-3 text-left text-xs font-medium text-neutral-600">Description</th>
                  <th className="px-4 py-3 text-left text-xs font-medium text-neutral-600">Source</th>
                  <th className="px-4 py-3 text-left text-xs font-medium text-neutral-600">Due Date</th>
                  <th className="px-4 py-3 text-left text-xs font-medium text-neutral-600">Status</th>
                  <th className="px-4 py-3 text-left text-xs font-medium text-neutral-600">Assigned To</th>
                  <th className="px-4 py-3 text-left text-xs font-medium text-neutral-600"></th>
                </tr>
              </thead>
              <tbody className="divide-y divide-neutral-100">
                {capas.map((capa) => (
                  <tr key={capa.id} className="hover:bg-neutral-50/50 transition-colors">
                    <td className="px-4 py-3 font-medium text-neutral-900 capitalize">
                      {capa.type}
                    </td>
                    <td className="px-4 py-3 text-neutral-700 max-w-xs truncate">
                      {capa.description}
                    </td>
                    <td className="px-4 py-3 text-neutral-500 font-mono text-xs">
                      {capa.ncr_reference
                        ? capa.ncr_reference
                        : capa.audit_reference
                          ? capa.audit_reference
                          : '—'}
                    </td>
                    <td className="px-4 py-3 text-neutral-600">{capa.due_date ?? '—'}</td>
                    <td className="px-4 py-3">
                      <StatusBadge status={capa.status}>
                        {capa.status?.replace('_', ' ') || 'Unknown'}
                      </StatusBadge>
                    </td>
                    <td className="px-4 py-3 text-neutral-600">
                      {capa.assigned_to?.name ?? <span className="text-neutral-400">Unassigned</span>}
                    </td>
                    <td className="px-4 py-3">
                      {capa.status === 'open' && canManage && (
                        <ConfirmDialog
                          title="Complete CAPA?"
                          description={`Mark this CAPA action as completed?\n\n"${capa.description}"\n\nThis indicates the action has been implemented.`}
                          confirmLabel="Complete"
                          onConfirm={handleComplete}
                        >
                          <button
                            onClick={() => setConfirmCapa(capa)}
                            className="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium border border-neutral-200 rounded bg-white text-neutral-700 hover:bg-neutral-50 hover:border-neutral-300 transition-colors"
                          >
                            Complete
                          </button>
                        </ConfirmDialog>
                      )}
                      {capa.status === 'completed' && canManage && (
                        <ConfirmDialog
                          title="Verify CAPA?"
                          description={`Verify this completed CAPA action?\n\n"${capa.description}"\n\nVerification confirms the action was effective.`}
                          confirmLabel="Verify"
                          onConfirm={handleVerify}
                        >
                          <button
                            onClick={() => setVerifyCapa(capa)}
                            className="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium border border-emerald-200 rounded bg-emerald-50 text-emerald-700 hover:bg-emerald-100 hover:border-emerald-300 transition-colors"
                          >
                            Verify
                          </button>
                        </ConfirmDialog>
                      )}
                      {(capa.status === 'in_progress') && canManage && (
                        <button
                          onClick={() => setConfirmCapa(capa)}
                          className="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium border border-neutral-200 rounded bg-white text-neutral-700 hover:bg-neutral-50 hover:border-neutral-300 transition-colors"
                        >
                          Complete
                        </button>
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </CardBody>
        </Card>
      )}

      {/* Complete CAPA Confirmation Dialog (fallback for in_progress) */}
      {confirmCapa && confirmCapa.status !== 'open' && (
        <ConfirmDialog
          title="Complete CAPA?"
          description={`Mark this CAPA action as completed?\n\n"${confirmCapa.description}"`}
          confirmLabel="Complete"
          onConfirm={handleComplete}
        >
          <span />
        </ConfirmDialog>
      )}

      {/* Complete CAPA Confirmation Dialog for open status */}
      {confirmCapa && confirmCapa.status === 'open' && (
        <ConfirmDialog
          title="Complete CAPA?"
          description={`Mark this CAPA as completed?\n\n"${confirmCapa.description}"`}
          confirmLabel="Complete"
          onCancel={() => setConfirmCapa(null)}
          onConfirm={handleComplete}
          isLoading={completeMut.isPending}
        />
      )}

      {/* Verify CAPA Confirmation Dialog */}
      {verifyCapa && (
        <ConfirmDialog
          title="Verify CAPA?"
          description={`Verify this CAPA action?\n\n"${verifyCapa.description}"\n\nThis confirms the action was effective and properly implemented.`}
          confirmLabel="Verify"
          onCancel={() => setVerifyCapa(null)}
          onConfirm={handleVerify}
          isLoading={completeMut.isPending}
        />
      )}
    </div>
  )
}
