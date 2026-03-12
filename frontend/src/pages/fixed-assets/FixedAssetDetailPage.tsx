import { useState } from 'react'
import { useParams, useNavigate } from 'react-router-dom'
import { toast } from 'sonner'
import { Package } from 'lucide-react'
import { useFixedAsset, useDisposeAsset } from '@/hooks/useFixedAssets'
import { useAuthStore } from '@/stores/authStore'
import { PageHeader } from '@/components/ui/PageHeader'
import { Card, CardHeader, CardBody } from '@/components/ui/Card'
import StatusBadge from '@/components/ui/StatusBadge'
import SkeletonLoader from '@/components/ui/SkeletonLoader'

export default function FixedAssetDetailPage(): React.ReactElement {
  const { ulid } = useParams<{ ulid: string }>()
  const navigate = useNavigate()
  const { data: asset, isLoading, isError } = useFixedAsset(ulid ?? null)
  const dispose = useDisposeAsset(ulid ?? '')
  const [showDispose, setShowDispose] = useState(false)
  const [dispForm, setDispForm] = useState({ disposal_date: '', disposal_method: 'scrap', sale_price_centavos: 0, notes: '' })
  const canManage = useAuthStore((s) => s.hasPermission('chart_of_accounts.manage'))

  if (isLoading) return <SkeletonLoader rows={6} />
  if (isError || !asset) return (
    <div className="max-w-4xl mx-auto py-16 text-center">
      <p className="text-red-600">Asset not found.</p>
      <button onClick={() => navigate('/fixed-assets')} className="btn-secondary mt-4">
        Back to Register
      </button>
    </div>
  )

  async function handleDispose(e: React.FormEvent) {
    e.preventDefault()
    try {
      await dispose.mutateAsync({
        disposal_date: dispForm.disposal_date,
        disposal_method: dispForm.disposal_method,
        sale_price_centavos: Math.round(dispForm.sale_price_centavos * 100),
        notes: dispForm.notes || undefined,
      })
      toast.success('Asset disposed.')
      setShowDispose(false)
    } catch {
      toast.error('Disposal failed.')
    }
  }

  const fmt = (centavos: number) => `₱${(centavos / 100).toLocaleString(undefined, { minimumFractionDigits: 2 })}`
  
  // Calculate depreciation
  const accumulatedDepreciation = asset.acquisition_cost_centavos - asset.book_value_centavos
  const depreciationPercent = asset.acquisition_cost_centavos > 0 
    ? Math.round((accumulatedDepreciation / asset.acquisition_cost_centavos) * 100) 
    : 0

  return (
    <div className="max-w-4xl mx-auto space-y-6">
      <PageHeader
        title={`${asset.name}`}
        subtitle={`Asset Code: ${asset.ulid.slice(0, 8)}`}
        backTo="/fixed-assets"
        icon={<Package className="w-5 h-5 text-neutral-600" />}
        status={<StatusBadge status={asset.status}>{asset.status}</StatusBadge>}
      />

      {/* Value Summary Cards */}
      <div className="grid grid-cols-3 gap-4">
        <div className="bg-emerald-50 border border-emerald-200 rounded-xl p-4">
          <p className="text-xs font-medium text-emerald-600 uppercase tracking-wide">Acquisition Cost</p>
          <p className="text-xl font-bold text-emerald-700 font-mono mt-1">{fmt(asset.acquisition_cost_centavos)}</p>
        </div>
        <div className="bg-blue-50 border border-blue-200 rounded-xl p-4">
          <p className="text-xs font-medium text-blue-600 uppercase tracking-wide">Current Book Value</p>
          <p className="text-xl font-bold text-blue-700 font-mono mt-1">{fmt(asset.book_value_centavos)}</p>
        </div>
        <div className="bg-amber-50 border border-amber-200 rounded-xl p-4">
          <p className="text-xs font-medium text-amber-600 uppercase tracking-wide">Accumulated Depreciation</p>
          <p className="text-xl font-bold text-amber-700 font-mono mt-1">{fmt(accumulatedDepreciation)}</p>
          <p className="text-xs text-amber-600 mt-1">{depreciationPercent}% depreciated</p>
        </div>
      </div>

      <Card>
        <CardHeader>Asset Details</CardHeader>
        <CardBody>
          <div className="grid grid-cols-2 gap-4 text-sm">
            <div><span className="text-neutral-500">Category:</span> <span className="text-neutral-800 ml-1 font-medium">{asset.category?.name ?? '—'}</span></div>
            <div><span className="text-neutral-500">Location:</span> <span className="text-neutral-800 ml-1 font-medium">{asset.location ?? '—'}</span></div>
            <div><span className="text-neutral-500">Acquisition Date:</span> <span className="text-neutral-800 ml-1 font-medium">{asset.acquisition_date}</span></div>
            <div><span className="text-neutral-500">Depreciation Method:</span> <span className="text-neutral-800 ml-1 font-medium capitalize">{asset.depreciation_method?.replace(/_/g, ' ') ?? '—'}</span></div>
            <div><span className="text-neutral-500">Useful Life:</span> <span className="text-neutral-800 ml-1 font-medium">{asset.useful_life_years ?? '—'} years</span></div>
            <div><span className="text-neutral-500">Residual Value:</span> <span className="text-neutral-800 ml-1 font-mono font-medium">{fmt(asset.residual_value_centavos)}</span></div>
          </div>
        </CardBody>
      </Card>

      {asset.status === 'active' && canManage && (
        <Card>
          <CardHeader>Asset Disposal</CardHeader>
          <CardBody>
            {!showDispose ? (
              <button onClick={() => setShowDispose(true)}
                className="btn-danger">
                Dispose Asset
              </button>
            ) : (
              <form onSubmit={handleDispose} className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-xs font-medium text-neutral-600 mb-1">Disposal Date</label>
                  <input type="date" value={dispForm.disposal_date} onChange={(e) => setDispForm(f => ({ ...f, disposal_date: e.target.value }))} required
                    className="w-full border border-neutral-300 rounded px-3 py-2 text-sm focus:ring-1 focus:ring-neutral-400 outline-none" />
                </div>
                <div>
                  <label className="block text-xs font-medium text-neutral-600 mb-1">Method</label>
                  <select value={dispForm.disposal_method} onChange={(e) => setDispForm(f => ({ ...f, disposal_method: e.target.value }))}
                    className="w-full border border-neutral-300 rounded px-3 py-2 text-sm focus:ring-1 focus:ring-neutral-400 outline-none">
                    <option value="sale">Sale</option>
                    <option value="scrap">Scrap</option>
                    <option value="donation">Donation</option>
                    <option value="write_off">Write Off</option>
                  </select>
                </div>
                <div>
                  <label className="block text-xs font-medium text-neutral-600 mb-1">Sale Price (₱)</label>
                  <input type="number" step="0.01" min={0} value={dispForm.sale_price_centavos}
                    onChange={(e) => setDispForm(f => ({ ...f, sale_price_centavos: Number(e.target.value) }))}
                    className="w-full border border-neutral-300 rounded px-3 py-2 text-sm focus:ring-1 focus:ring-neutral-400 outline-none" />
                </div>
                <div>
                  <label className="block text-xs font-medium text-neutral-600 mb-1">Notes</label>
                  <input value={dispForm.notes} onChange={(e) => setDispForm(f => ({ ...f, notes: e.target.value }))}
                    className="w-full border border-neutral-300 rounded px-3 py-2 text-sm focus:ring-1 focus:ring-neutral-400 outline-none" />
                </div>
                <div className="col-span-2 flex gap-2">
                  <button type="submit" disabled={dispose.isPending}
                    className="btn-danger">
                    {dispose.isPending ? 'Processing…' : 'Confirm Disposal'}
                  </button>
                  <button type="button" onClick={() => setShowDispose(false)} className="btn-ghost">Cancel</button>
                </div>
              </form>
            )}
          </CardBody>
        </Card>
      )}

      {asset.disposal && (
        <Card className="border-red-200">
          <CardHeader>
            <span className="text-red-700 flex items-center gap-2">
              <span className="w-2 h-2 rounded-full bg-red-500" />
              Disposal Information
            </span>
          </CardHeader>
          <CardBody>
            <div className="grid grid-cols-2 gap-4 text-sm">
              <div><span className="text-neutral-500">Disposal Date:</span> <span className="ml-1 font-medium text-neutral-900">{asset.disposal.disposal_date}</span></div>
              <div><span className="text-neutral-500">Method:</span> <span className="ml-1 font-medium capitalize text-neutral-900">{asset.disposal.disposal_method?.replace(/_/g, ' ') ?? '—'}</span></div>
              <div className="col-span-2">
                <span className="text-neutral-500">Proceeds:</span>{' '}
                <span className="ml-1 font-mono font-bold text-emerald-700 text-lg">{fmt(asset.disposal.proceeds_centavos ?? 0)}</span>
                {asset.disposal.proceeds_centavos !== undefined && asset.book_value_centavos !== undefined && (
                  <span className={`ml-3 text-sm font-medium ${
                    (asset.disposal.proceeds_centavos - asset.book_value_centavos) >= 0 
                      ? 'text-emerald-600' 
                      : 'text-red-600'
                  }`}>
                    {(asset.disposal.proceeds_centavos - asset.book_value_centavos) >= 0 ? '▲' : '▼'} {' '}
                    {fmt(Math.abs(asset.disposal.proceeds_centavos - asset.book_value_centavos))} {' '}
                    {(asset.disposal.proceeds_centavos - asset.book_value_centavos) >= 0 ? 'Gain' : 'Loss'}
                  </span>
                )}
              </div>
            </div>
          </CardBody>
        </Card>
      )}
    </div>
  )
}
