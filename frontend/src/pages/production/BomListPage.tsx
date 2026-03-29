import { useState, useCallback } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { AlertTriangle, Plus, Eye, RotateCcw, Trash2 } from 'lucide-react'
import { useQuery } from '@tanstack/react-query'
import { toast } from 'sonner'
import { PageHeader } from '@/components/ui/PageHeader'
import SearchInput from '@/components/ui/SearchInput'
import Pagination from '@/components/ui/Pagination'
import { useBoms } from '@/hooks/useProduction'
import { useAuthStore } from '@/stores/authStore'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import { ExportButton } from '@/components/ui/ExportButton'
import ArchiveToggleButton from '@/components/ui/ArchiveToggleButton'
import ArchiveViewBanner from '@/components/ui/ArchiveViewBanner'
import ArchiveEmptyState from '@/components/ui/ArchiveEmptyState'
import ConfirmDestructiveDialog from '@/components/ui/ConfirmDestructiveDialog'
import { firstErrorMessage } from '@/lib/errorHandler'
import api from '@/lib/api'

export default function BomListPage(): React.ReactElement {
  const [_page, setPage] = useState(1)
  const [isArchiveView, setIsArchiveView] = useState(false)
  const [search, setSearch] = useState('')
  const [debouncedSearch, setDebouncedSearch] = useState('')

  const handleSearch = useCallback((val: string) => {
    setDebouncedSearch(val)
    setPage(1)
  }, [])

  const { data, isLoading, isError, refetch } = useBoms({ per_page: 20, ...(debouncedSearch ? { search: debouncedSearch } : {}) })
  const { data: archivedData, isLoading: archivedLoading, refetch: refetchArchived } = useQuery({
    queryKey: ['boms', 'archived', debouncedSearch],
    queryFn: () => api.get('/production/boms-archived', { params: { search: debouncedSearch || undefined, per_page: 20 } }),
    enabled: isArchiveView,
  })

  const currentData = isArchiveView ? (archivedData?.data?.data ?? []) : (data?.data ?? [])
  const currentLoading = isArchiveView ? archivedLoading : isLoading
  const isSuperAdmin = useAuthStore(s => s.user?.roles?.some((r: { name: string }) => r.name === 'super_admin'))
  const { hasPermission } = useAuthStore()
  const canCreate = hasPermission('production.bom.manage')
  const navigate  = useNavigate()

  return (
    <div>
      <PageHeader
        title="Bill of Materials"
        actions={
          <div className="flex items-center gap-2">
            <ExportButton
              data={data?.data ?? []}
              columns={[
                { key: 'product_item.item_code', label: 'Item Code' },
                { key: 'product_item.name', label: 'Product' },
                { key: 'version', label: 'Version' },
                { key: 'is_active', label: 'Active', format: (v: unknown) => v ? 'Yes' : 'No' },
              ]}
              filename="bill-of-materials"
            />
            {!isArchiveView && canCreate && (
              <Link
                to="/production/boms/new"
                className="inline-flex items-center gap-1.5 bg-neutral-900 hover:bg-neutral-800 text-white text-sm font-medium px-4 py-2 rounded transition-colors"
              >
                <Plus className="w-4 h-4" />
                New BOM
              </Link>
            )}
          </div>
        }
      />

      <div className="flex flex-wrap gap-3 mb-5 items-center">
        <SearchInput
          value={search}
          onChange={setSearch}
          onSearch={handleSearch}
          placeholder="Search BOMs..."
          className="w-64"
        />
        <ArchiveToggleButton isArchiveView={isArchiveView} onToggle={() => setIsArchiveView(prev => !prev)} />
      </div>

      {isArchiveView && <ArchiveViewBanner />}

      {currentLoading && <SkeletonLoader rows={8} />}
      {isError && (
        <div className="flex items-center gap-2 text-red-600 text-sm">
          <AlertTriangle className="w-4 h-4" /> Failed to load BOMs.
        </div>
      )}

      {!currentLoading && !isError && (
        <>
          {currentData.length === 0 ? (
            <ArchiveEmptyState isArchiveView={isArchiveView} recordLabel="BOMs" />
          ) : (
            <div className="bg-white border border-neutral-200 rounded overflow-hidden">
              <table className="min-w-full text-sm">
                <thead className="bg-neutral-50 border-b border-neutral-200">
                  <tr>
                    {(isArchiveView
                      ? ['Product Item', 'Version', 'Components', 'Archived On', '']
                      : ['Product Item', 'Version', 'Components', 'Status', 'Created', '']
                    ).map((h) => (
                      <th key={h} className="px-4 py-3 text-left text-xs font-medium text-neutral-600">{h}</th>
                    ))}
                  </tr>
                </thead>
                <tbody className="divide-y divide-neutral-100">
                  {/* eslint-disable-next-line @typescript-eslint/no-explicit-any */}
                  {currentData.map((bom: any) => (
                    <tr
                      key={bom.id}
                      onClick={() => !isArchiveView && navigate(`/production/boms/${bom.ulid}`)}
                      className={`hover:bg-neutral-50 transition-colors ${isArchiveView ? '' : 'cursor-pointer'}`}
                    >
                      <td className="px-4 py-3">
                        <div className="font-mono text-neutral-900 font-medium text-xs">{bom.product_item?.item_code}</div>
                        <div className="text-neutral-700 text-sm">{bom.product_item?.name}</div>
                      </td>
                      <td className="px-4 py-3 text-neutral-500">v{bom.version}</td>
                      <td className="px-4 py-3 text-neutral-500">{bom.components?.length ?? '—'} items</td>
                      {isArchiveView ? (
                        <>
                          <td className="px-4 py-3 text-neutral-400 text-xs">
                            {bom.deleted_at ? new Date(bom.deleted_at).toLocaleDateString('en-PH') : '—'}
                          </td>
                          <td className="px-4 py-3 text-right">
                            <div className="flex items-center justify-end gap-2">
                              <ConfirmDestructiveDialog
                                title="Restore BOM?"
                                description={`Restore this BOM for ${bom.product_item?.name}?`}
                                confirmWord="RESTORE"
                                confirmLabel="Restore"
                                variant="warning"
                                onConfirm={async () => {
                                  try {
                                    await api.post(`/production/boms/${bom.id}/restore`)
                                    toast.success('BOM restored.')
                                    refetch()
                                    refetchArchived()
                                  } catch (err) { toast.error(firstErrorMessage(err)) }
                                }}
                              >
                                <button className="text-xs text-blue-600 hover:underline flex items-center gap-1" onClick={e => e.stopPropagation()}>
                                  <RotateCcw className="w-3 h-3" /> Restore
                                </button>
                              </ConfirmDestructiveDialog>
                              {isSuperAdmin && (
                                <ConfirmDestructiveDialog
                                  title="Permanently Delete BOM?"
                                  description='This action cannot be undone. Type "DELETE" to confirm.'
                                  confirmWord="DELETE"
                                  confirmLabel="Permanently Delete"
                                  onConfirm={async () => {
                                    try {
                                      await api.delete(`/production/boms/${bom.id}/force`)
                                      toast.success('BOM permanently deleted.')
                                      refetchArchived()
                                    } catch (err) { toast.error(firstErrorMessage(err)) }
                                  }}
                                >
                                  <button className="text-xs text-red-600 hover:underline flex items-center gap-1" onClick={e => e.stopPropagation()}>
                                    <Trash2 className="w-3 h-3" /> Delete Forever
                                  </button>
                                </ConfirmDestructiveDialog>
                              )}
                            </div>
                          </td>
                        </>
                      ) : (
                        <>
                          <td className="px-4 py-3">
                            {bom.is_active
                              ? <span className="inline-flex px-2 py-0.5 rounded text-xs font-medium bg-neutral-200 text-neutral-800">Active</span>
                              : <span className="inline-flex px-2 py-0.5 rounded text-xs font-medium bg-neutral-100 text-neutral-500">Inactive</span>}
                          </td>
                          <td className="px-4 py-3 text-neutral-400 text-xs">
                            {bom.created_at ? new Date(bom.created_at).toLocaleDateString('en-PH') : '—'}
                          </td>
                          <td className="px-4 py-3 text-right">
                            <Link
                              to={`/production/boms/${bom.ulid}`}
                              onClick={(e) => e.stopPropagation()}
                              className="inline-flex items-center gap-1 px-2.5 py-1 text-xs border border-neutral-200 rounded bg-white text-neutral-600 hover:bg-neutral-50 hover:border-neutral-300 hover:text-neutral-900 font-medium"
                            >
                              <Eye className="w-3.5 h-3.5" /> View
                            </Link>
                          </td>
                        </>
                      )}
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
          {!isArchiveView && data?.meta && <Pagination meta={data.meta} onPageChange={setPage} />}
        </>
      )}
    </div>
  )
}
