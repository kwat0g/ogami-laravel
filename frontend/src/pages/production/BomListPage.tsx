import { useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { AlertTriangle, Plus, Pencil, Archive, Calculator } from 'lucide-react'
import { PageHeader } from '@/components/ui/PageHeader'
import { useBoms, useDeleteBom } from '@/hooks/useProduction'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import api from '@/lib/api'
import { useAuthStore } from '@/stores/authStore'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import { toast } from 'sonner'
import { firstErrorMessage } from '@/lib/errorHandler'
import ConfirmDestructiveDialog from '@/components/ui/ConfirmDestructiveDialog'
import { ExportButton } from '@/components/ui/ExportButton'

export default function BomListPage(): React.ReactElement {
  const [page, setPage] = useState(1)
  const [withArchived, setWithArchived] = useState(false)
  const { data, isLoading, isError } = useBoms({ per_page: 20, with_archived: withArchived || undefined })
  const { hasPermission } = useAuthStore()
  const canCreate = hasPermission('production.bom.manage')
  const navigate  = useNavigate()
  const deleteMut = useDeleteBom()
  const qc = useQueryClient()
  const rollupCost = useMutation({
    mutationFn: async (bomUlid: string) => {
      const { data } = await api.post(`/production/boms/${bomUlid}/rollup-cost`)
      return data
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['boms'] })
      toast.success('Cost rollup completed.')
    },
    onError: (err) => toast.error(firstErrorMessage(err, 'Cost rollup failed.')),
  })

  const handleArchive = async (ulid: string) => {
    try {
      await deleteMut.mutateAsync(ulid)
      toast.success('BOM archived successfully.')
    } catch (err) {
      toast.error(firstErrorMessage(err))
    }
  }

  return (
    <div>
      <PageHeader
        title="Bill of Materials"
        actions={
          <div className="flex items-center gap-2">
            <ExportButton
              data={data?.data ?? []}
              columns={[
                { key: 'name', label: 'BOM Name' },
                { key: 'product_item.name', label: 'Product' },
                { key: 'version', label: 'Version' },
                { key: 'is_active', label: 'Active', format: (v: unknown) => v ? 'Yes' : 'No' },
              ]}
              filename="bill-of-materials"
            />
            {canCreate && (
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

      <div className="flex flex-wrap gap-3 mb-5">
        <label className="flex items-center gap-2 text-sm text-neutral-600 cursor-pointer select-none">
          <input type="checkbox" checked={withArchived} onChange={(e) => setWithArchived(e.target.checked)} className="rounded border-neutral-300" />
          <span>Show Archived</span>
        </label>
      </div>

      {isLoading && <SkeletonLoader rows={8} />}
      {isError && (
        <div className="flex items-center gap-2 text-red-600 text-sm">
          <AlertTriangle className="w-4 h-4" /> Failed to load BOMs.
        </div>
      )}

      {!isLoading && !isError && (
        <>
          <div className="bg-white border border-neutral-200 rounded overflow-hidden">
            <table className="min-w-full text-sm">
              <thead className="bg-neutral-50 border-b border-neutral-200">
                <tr>
                  {['Product Item', 'Version', 'Components', 'Std Cost', 'Status', 'Created', ''].map((h) => (
                    <th key={h} className="px-4 py-3 text-left text-xs font-medium text-neutral-600">{h}</th>
                  ))}
                </tr>
              </thead>
              <tbody className="divide-y divide-neutral-100">
                {data?.data?.length === 0 && (
                  <tr>
                    <td colSpan={7} className="px-4 py-8 text-center text-neutral-400 text-sm">No BOMs found.</td>
                  </tr>
                )}
                {data?.data?.map((bom) => (
                  <tr key={bom.id} className="even:bg-neutral-100 hover:bg-neutral-50">
                    <td className="px-4 py-3">
                      <div className="font-mono text-neutral-900 font-medium text-xs">{bom.product_item?.item_code}</div>
                      <div className="text-neutral-700 text-sm">{bom.product_item?.name}</div>
                    </td>
                    <td className="px-4 py-3 text-neutral-500">v{bom.version}</td>
                    <td className="px-4 py-3 text-neutral-500">{bom.components?.length ?? '—'} items</td>
                    <td className="px-4 py-3 font-mono text-xs">
                      <div className="flex flex-col gap-1">
                        <span className={`font-semibold ${bom.standard_cost_centavos > 0 ? 'text-neutral-900' : 'text-neutral-400'}`}>
                          {new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format((bom.standard_cost_centavos ?? 0) / 100)}
                        </span>
                        <div className="flex items-center gap-2">
                          <Link
                            to={`/production/boms/${bom.ulid}/cost-breakdown`}
                            className="text-blue-600 hover:underline text-xs"
                          >
                            View Details
                          </Link>
                          <button
                            onClick={() => rollupCost.mutate(bom.ulid)}
                            disabled={rollupCost.isPending}
                            className="text-neutral-500 hover:text-blue-600 hover:underline text-xs flex items-center gap-1"
                            title="Recalculate standard cost"
                          >
                            <Calculator className="w-3 h-3" /> Recalc
                          </button>
                        </div>
                      </div>
                    </td>
                    <td className="px-4 py-3">
                      {bom.deleted_at && <span className="inline-flex px-2 py-0.5 rounded text-xs font-medium bg-neutral-100 text-neutral-500 mr-1">Archived</span>}
                      {bom.is_active
                        ? <span className="inline-flex px-2 py-0.5 rounded text-xs font-medium bg-neutral-200 text-neutral-800">Active</span>
                        : <span className="inline-flex px-2 py-0.5 rounded text-xs font-medium bg-neutral-100 text-neutral-500">Inactive</span>}
                    </td>
                    <td className="px-4 py-3 text-neutral-400 text-xs">
                      {bom.created_at ? new Date(bom.created_at).toLocaleDateString('en-PH') : '—'}
                    </td>
                    {canCreate && !bom.deleted_at && (
                      <td className="px-4 py-3">
                        <div className="flex items-center gap-3">
                          <button
                            onClick={() => navigate(`/production/boms/${bom.ulid}/edit`)}
                            className="flex items-center gap-1 px-2 py-1 text-xs border border-neutral-200 rounded bg-white text-neutral-600 hover:bg-neutral-50 hover:border-neutral-300 hover:text-neutral-900 font-medium"
                          >
                            <Pencil className="w-3.5 h-3.5" /> Edit
                          </button>
                          <ConfirmDestructiveDialog
                            title="Archive BOM?"
                            description={`This will archive the BOM for "${bom.product_item?.name ?? 'BOM'}". It will move to the Archived section and cannot be used for new production orders.`}
                            confirmWord="ARCHIVE"
                            confirmLabel="Archive"
                            onConfirm={() => handleArchive(bom.ulid)}
                          >
                            <button
                              disabled={deleteMut.isPending}
                              className="flex items-center gap-1 px-2 py-1 text-xs border border-neutral-200 rounded bg-white text-neutral-500 hover:bg-neutral-50 hover:border-neutral-300 hover:text-red-600 font-medium disabled:opacity-40 disabled:cursor-not-allowed"
                            >
                              <Archive className="w-3.5 h-3.5" /> Archive
                            </button>
                          </ConfirmDestructiveDialog>
                        </div>
                      </td>
                    )}
                    {(!canCreate || bom.deleted_at) && <td />}
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          {data && data.meta.last_page > 1 && (
            <div className="flex items-center justify-between mt-4 text-sm text-neutral-600">
              <span>Page {data.meta.current_page} of {data.meta.last_page}</span>
              <div className="flex gap-2">
                <button onClick={() => setPage((p) => Math.max(1, p - 1))} disabled={page === 1} className="px-3 py-1.5 border border-neutral-300 rounded disabled:opacity-40 disabled:cursor-not-allowed hover:bg-neutral-50">Previous</button>
                <button onClick={() => setPage((p) => p + 1)} disabled={page >= data.meta.last_page} className="px-3 py-1.5 border border-neutral-300 rounded disabled:opacity-40 disabled:cursor-not-allowed hover:bg-neutral-50">Next</button>
              </div>
            </div>
          )}
        </>
      )}
    </div>
  )
}
