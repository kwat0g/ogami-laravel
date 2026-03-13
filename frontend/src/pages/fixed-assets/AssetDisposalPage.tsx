import { useState } from 'react'
import { Trash2 } from 'lucide-react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import api from '@/lib/api'
import { useAuthStore } from '@/stores/authStore'
import { PageHeader } from '@/components/ui/PageHeader'
import { Card, CardHeader, CardBody } from '@/components/ui/Card'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import EmptyState from '@/components/ui/EmptyState'

interface FixedAsset {
  id: number
  ulid: string
  asset_code: string
  name: string
  book_value: number
  status: string
}

function useDisposableAssets() {
  return useQuery({
    queryKey: ['fixed-assets', { status: 'active' }],
    queryFn: async () => {
      const res = await api.get<{ data: FixedAsset[] }>('/fixed-assets', { params: { status: 'active' } })
      return res.data.data
    },
  })
}

function useDisposeAsset() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (data: { assetUlid: string; method: string; proceeds: number; reason: string }) => {
      return api.post(`/fixed-assets/${data.assetUlid}/dispose`, {
        disposal_method: data.method,
        proceeds_centavos: Math.round(data.proceeds * 100),
        reason: data.reason,
      })
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['fixed-assets'] })
      toast.success('Asset disposed successfully')
    },
    onError: () => toast.error('Failed to dispose asset'),
  })
}

export default function AssetDisposalPage(): React.ReactElement {
  const { data: assets = [], isLoading } = useDisposableAssets()
  const disposeMut = useDisposeAsset()
  const [selected, setSelected] = useState<FixedAsset | null>(null)
  const [method, setMethod] = useState('sale')
  const [proceeds, setProceeds] = useState('')
  const [reason, setReason] = useState('')
  const canManage = useAuthStore((s) => s.hasPermission('fixed_assets.manage'))

  const handleDispose = (): void => {
    if (!selected) return
    disposeMut.mutate({
      assetUlid: selected.ulid,
      method,
      proceeds: parseFloat(proceeds) || 0,
      reason,
    }, {
      onSuccess: () => {
        setSelected(null)
        setProceeds('')
        setReason('')
      },
    })
  }

  if (!canManage) {
    return (
      <div className="max-w-3xl mx-auto space-y-4">
        <PageHeader
          title="Asset Disposal"
          icon={<Trash2 className="w-5 h-5 text-red-600" />}
        />
        <div className="bg-white border border-neutral-200 rounded-lg p-6 text-sm text-neutral-600">
          You do not have permission to dispose fixed assets.
        </div>
      </div>
    )
  }

  return (
    <div className="max-w-5xl mx-auto space-y-6">
      <PageHeader
        title="Asset Disposal"
        icon={<Trash2 className="w-5 h-5 text-red-600" />}
      />

      <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
        {/* Asset Selector */}
        <Card>
          <CardHeader>Select Asset to Dispose</CardHeader>
          <CardBody>
            {isLoading ? (
              <SkeletonLoader rows={4} />
            ) : assets.length === 0 ? (
              <EmptyState title="No active assets" description="There are no active assets available for disposal." />
            ) : (
              <div className="space-y-2 max-h-96 overflow-y-auto">
                {assets.map((a) => (
                  <button
                    key={a.id}
                    onClick={() => setSelected(a)}
                    className={`w-full text-left p-3 rounded-lg border transition-all ${
                      selected?.id === a.id
                        ? 'border-red-400 bg-red-50 ring-1 ring-red-400'
                        : 'border-neutral-200 hover:border-neutral-300 hover:bg-neutral-50'
                    }`}
                  >
                    <div className="font-mono text-xs text-neutral-400">{a.asset_code}</div>
                    <div className="text-sm font-medium text-neutral-800">{a.name}</div>
                    <div className="text-xs text-neutral-500">Book Value: <span className="font-mono">₱{(a.book_value / 100).toLocaleString(undefined, { minimumFractionDigits: 2 })}</span></div>
                  </button>
                ))}
              </div>
            )}
          </CardBody>
        </Card>

        {/* Disposal Form */}
        <Card>
          <CardHeader>Disposal Details</CardHeader>
          <CardBody>
            {selected ? (
              <div className="space-y-4">
                <div className="p-3 bg-neutral-50 rounded-lg border border-neutral-100">
                  <div className="text-xs text-neutral-500 uppercase tracking-wide">Selected Asset</div>
                  <div className="font-medium text-neutral-900">{selected.asset_code} — {selected.name}</div>
                  <div className="text-sm text-neutral-600">Current Book Value: <span className="font-mono font-medium">₱{(selected.book_value / 100).toLocaleString(undefined, { minimumFractionDigits: 2 })}</span></div>
                </div>

                <div>
                  <label className="block text-xs font-medium text-neutral-600 mb-1">Disposal Method</label>
                  <select
                    value={method}
                    onChange={(e) => setMethod(e.target.value)}
                    className="w-full border border-neutral-300 rounded-lg px-3 py-2 text-sm focus:ring-1 focus:ring-neutral-400 outline-none"
                  >
                    <option value="sale">Sale</option>
                    <option value="scrap">Scrap</option>
                    <option value="write_off">Write-Off</option>
                    <option value="donation">Donation</option>
                  </select>
                </div>

                {method === 'sale' && (
                  <div>
                    <label className="block text-xs font-medium text-neutral-600 mb-1">Sale Proceeds (₱)</label>
                    <input
                      type="number"
                      step="0.01"
                      value={proceeds}
                      onChange={(e) => setProceeds(e.target.value)}
                      className="w-full border border-neutral-300 rounded-lg px-3 py-2 text-sm focus:ring-1 focus:ring-neutral-400 outline-none"
                      placeholder="0.00"
                    />
                  </div>
                )}

                <div>
                  <label className="block text-xs font-medium text-neutral-600 mb-1">Reason</label>
                  <textarea
                    value={reason}
                    onChange={(e) => setReason(e.target.value)}
                    rows={3}
                    className="w-full border border-neutral-300 rounded-lg px-3 py-2 text-sm focus:ring-1 focus:ring-neutral-400 outline-none resize-none"
                    placeholder="Reason for disposal…"
                  />
                </div>

                <button
                  onClick={handleDispose}
                  disabled={disposeMut.isPending || !reason.trim()}
                  className="w-full btn-danger py-2.5"
                >
                  {disposeMut.isPending ? 'Disposing…' : 'Confirm Disposal'}
                </button>
              </div>
            ) : (
              <div className="py-12 text-center">
                <p className="text-sm text-neutral-400">Select an asset from the list to begin disposal.</p>
              </div>
            )}
          </CardBody>
        </Card>
      </div>
    </div>
  )
}
