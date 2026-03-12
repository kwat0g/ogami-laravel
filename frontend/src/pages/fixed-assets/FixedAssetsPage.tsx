import { useState } from 'react'
import { toast } from 'sonner'
import { Plus, Eye, Package } from 'lucide-react'
import { useFixedAssets, useFixedAssetCategories, useDepreciatePeriod } from '@/hooks/useFixedAssets'
import { useFiscalPeriods } from '@/hooks/useAccounting'
import type { FixedAsset } from '@/types/fixed_assets'
import { Link } from 'react-router-dom'
import { useAuthStore } from '@/stores/authStore'
import { PageHeader } from '@/components/ui/PageHeader'
import { Card } from '@/components/ui/Card'
import EmptyState from '@/components/ui/EmptyState'
import StatusBadge from '@/components/ui/StatusBadge'
import SkeletonLoader from '@/components/ui/SkeletonLoader'

export default function FixedAssetsPage(): React.ReactElement {
  const [categoryFilter, setCategoryFilter] = useState<number | undefined>()
  const [showDepreciate, setShowDepreciate] = useState(false)
  const [depPeriod, setDepPeriod] = useState(0)

  const { data, isLoading } = useFixedAssets({ category_id: categoryFilter })
  const { data: categories } = useFixedAssetCategories()
  const { data: periodsData } = useFiscalPeriods('open')
  const depreciate = useDepreciatePeriod()

  const assets: FixedAsset[] = data?.data ?? []
  const periods = periodsData?.data ?? []
  const canManage = useAuthStore((s) => s.hasPermission('chart_of_accounts.manage'))

  // Calculate totals
  const totalCost = assets.reduce((sum, a) => sum + a.acquisition_cost_centavos, 0)
  const totalBookValue = assets.reduce((sum, a) => sum + a.book_value_centavos, 0)
  const totalDepreciation = totalCost - totalBookValue

  async function handleDepreciate(e: React.FormEvent) {
    e.preventDefault()
    try {
      const result = await depreciate.mutateAsync({ fiscal_period_id: depPeriod })
      toast.success(`Depreciation complete: ${result.processed} processed, ${result.skipped} skipped.`)
      setShowDepreciate(false)
    } catch {
      toast.error('Depreciation failed.')
    }
  }

  return (
    <div className="max-w-7xl mx-auto space-y-6">
      <PageHeader
        title="Fixed Assets"
        subtitle="Manage fixed asset register, depreciation, and disposal."
        icon={<Package className="w-5 h-5 text-neutral-600" />}
        actions={
          <div className="flex items-center gap-2">
            {canManage && (
              <button onClick={() => setShowDepreciate(true)}
                className="btn-secondary">
                Run Depreciation
              </button>
            )}
            <Link to="/fixed-assets/categories"
              className="btn-ghost">
              Categories
            </Link>
            {canManage && (
              <Link to="/fixed-assets/new"
                className="btn-primary">
                <Plus className="w-3.5 h-3.5" /> Add Asset
              </Link>
            )}
          </div>
        }
      />

      {/* Depreciation modal */}
      {showDepreciate && (
        <Card className="mb-6">
          <form onSubmit={handleDepreciate} className="flex items-end gap-4">
            <div className="flex-1">
              <label className="block text-xs font-medium text-neutral-600 mb-1">Fiscal Period</label>
              <select value={depPeriod} onChange={(e) => setDepPeriod(Number(e.target.value))} required
                className="w-full border border-neutral-300 rounded px-3 py-2 text-sm focus:ring-1 focus:ring-neutral-400 outline-none">
                <option value={0}>Select period…</option>
                {periods.map((p) => <option key={p.id} value={p.id}>{p.name}</option>)}
              </select>
            </div>
            <button type="submit" disabled={depreciate.isPending || !depPeriod}
              className="btn-primary">
              {depreciate.isPending ? 'Processing…' : 'Run'}
            </button>
            <button type="button" onClick={() => setShowDepreciate(false)} className="btn-ghost">Cancel</button>
          </form>
        </Card>
      )}

      {/* Filter */}
      <div className="flex items-center gap-4">
        <select value={categoryFilter ?? ''} onChange={(e) => setCategoryFilter(e.target.value ? Number(e.target.value) : undefined)}
          className="border border-neutral-300 rounded px-3 py-1.5 text-sm focus:ring-1 focus:ring-neutral-400 outline-none">
          <option value="">All categories</option>
          {(categories ?? []).map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
        </select>
      </div>

      {/* Summary Stats */}
      {!isLoading && assets.length > 0 && (
        <div className="grid grid-cols-3 gap-4">
          <div className="bg-emerald-50 border border-emerald-200 rounded-xl p-4">
            <p className="text-xs font-medium text-emerald-600 uppercase tracking-wide">Total Acquisition Cost</p>
            <p className="text-xl font-bold text-emerald-700 font-mono mt-1">
              ₱{(totalCost / 100).toLocaleString(undefined, { minimumFractionDigits: 2 })}
            </p>
          </div>
          <div className="bg-blue-50 border border-blue-200 rounded-xl p-4">
            <p className="text-xs font-medium text-blue-600 uppercase tracking-wide">Total Book Value</p>
            <p className="text-xl font-bold text-blue-700 font-mono mt-1">
              ₱{(totalBookValue / 100).toLocaleString(undefined, { minimumFractionDigits: 2 })}
            </p>
          </div>
          <div className="bg-amber-50 border border-amber-200 rounded-xl p-4">
            <p className="text-xs font-medium text-amber-600 uppercase tracking-wide">Accumulated Depreciation</p>
            <p className="text-xl font-bold text-amber-700 font-mono mt-1">
              ₱{(totalDepreciation / 100).toLocaleString(undefined, { minimumFractionDigits: 2 })}
            </p>
          </div>
        </div>
      )}

      {isLoading ? (
        <SkeletonLoader rows={8} />
      ) : assets.length === 0 ? (
        <EmptyState
          title="No fixed assets registered"
          description="Add your first fixed asset to start tracking depreciation."
          action={canManage && (
            <Link to="/fixed-assets/new" className="btn-primary">
              <Plus className="w-3.5 h-3.5" /> Add Asset
            </Link>
          )}
        />
      ) : (
        <Card>
          <table className="w-full text-sm">
            <thead className="bg-neutral-50 border-b border-neutral-200">
              <tr>
                {['Asset Code', 'Name', 'Category', 'Cost', 'NBV', 'Status', ''].map((h) => (
                  <th key={h} className="text-left px-4 py-3 text-xs font-semibold text-neutral-500 uppercase tracking-wider">{h}</th>
                ))}
              </tr>
            </thead>
            <tbody className="divide-y divide-neutral-100">
              {assets.map((a) => (
                <tr key={a.id} className="even:bg-neutral-50 hover:bg-neutral-50 transition-colors">
                  <td className="px-4 py-3 font-mono text-xs text-neutral-500">{a.ulid.slice(0, 8)}</td>
                  <td className="px-4 py-3 font-medium text-neutral-900">{a.name}</td>
                  <td className="px-4 py-3 text-neutral-600">{a.category?.name ?? '—'}</td>
                  <td className="px-4 py-3 font-mono">
                    <span className="text-emerald-700 font-semibold">
                      ₱{(a.acquisition_cost_centavos / 100).toLocaleString(undefined, { minimumFractionDigits: 2 })}
                    </span>
                  </td>
                  <td className="px-4 py-3 font-mono">
                    <span className="text-blue-700 font-semibold">
                      ₱{(a.book_value_centavos / 100).toLocaleString(undefined, { minimumFractionDigits: 2 })}
                    </span>
                  </td>
                  <td className="px-4 py-3">
                    <StatusBadge status={a.status}>{a.status}</StatusBadge>
                  </td>
                  <td className="px-4 py-3">
                    <Link to={`/fixed-assets/${a.ulid}`} className="icon-btn p-1.5">
                      <Eye className="w-4 h-4" />
                    </Link>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </Card>
      )}
    </div>
  )
}
