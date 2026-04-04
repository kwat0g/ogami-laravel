import React, { useState, useCallback } from 'react'
import { Link } from 'react-router-dom'
import { AlertTriangle, AlertCircle, RefreshCw, MapPin, List } from 'lucide-react'
import { PageHeader } from '@/components/ui/PageHeader'
import SearchInput from '@/components/ui/SearchInput'
import { useStockBalances, useWarehouseLocations, useStockAdjust } from '@/hooks/useInventory'
import { usePermission } from '@/hooks/usePermission'
import { isHandledApiError } from '@/lib/api'
import { toast } from 'sonner'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import ConfirmDialog from '@/components/ui/ConfirmDialog'
import { ExportButton } from '@/components/ui/ExportButton'
import { firstErrorMessage } from '@/lib/errorHandler'
import type { StockBalance } from '@/types/inventory'

interface AdjustState {
  bal: StockBalance
  mode: 'set' | 'delta'
  newQty: string
  deltaQty: string
  remarks: string
}

export default function StockBalancePage(): React.ReactElement {
  const [search, setSearch]         = useState('')
  const [debouncedSearch, setDebouncedSearch] = useState('')
  const [locationId, setLocationId] = useState<number | ''>('')

  const handleSearch = useCallback((val: string) => {
    setDebouncedSearch(val)
    setPage(1)
  }, [])
  const [lowStock, setLowStock]     = useState(false)
  const [page, setPage]             = useState(1)
  const [adjusting, setAdjusting]   = useState<AdjustState | null>(null)
  const [showConfirm, setShowConfirm] = useState(false)

  const canAdjust = usePermission('inventory.adjustments.create')
  const adjustMut = useStockAdjust()

  const { data: locations } = useWarehouseLocations({ is_active: true })
  const { data, isLoading, isError, refetch } = useStockBalances({
    search: debouncedSearch || undefined,
    location_id: locationId || undefined,
    low_stock: lowStock || undefined,
    page,
    per_page: 25,
  })

  const handleRefresh = async () => {
    await refetch()
    toast.success('Stock balances refreshed.')
  }

  const resolveAdjustedQty = useCallback((state: AdjustState): number | null => {
    const onHand = parseFloat(state.bal.quantity_on_hand)
    if (state.mode === 'set') {
      const qty = parseFloat(state.newQty)
      if (isNaN(qty) || qty < 0) return null
      return qty
    }

    const delta = parseFloat(state.deltaQty)
    if (isNaN(delta) || delta === 0) return null
    const next = onHand + delta
    if (next < 0) return null
    return next
  }, [])

  const validateAdjust = (): { ok: boolean; reason?: string } => {
    if (!adjusting) return { ok: false, reason: 'No selected stock record.' }

    const adjustedQty = resolveAdjustedQty(adjusting)
    if (adjustedQty === null) {
      if (adjusting.mode === 'delta') {
        return { ok: false, reason: 'Enter a valid +/- quantity and ensure resulting stock is not negative.' }
      }
      return { ok: false, reason: 'Enter a valid non-negative target quantity.' }
    }

    if (adjusting.remarks.trim().length < 10) {
      return { ok: false, reason: 'Reason must be at least 10 characters.' }
    }

    return { ok: true }
  }

  const handleAdjustClick = () => {
    const validation = validateAdjust()
    if (!validation.ok) {
      toast.warning(validation.reason ?? 'Please fix adjustment inputs.')
      return
    }
    setShowConfirm(true)
  }

  const handleAdjust = async () => {
    if (!adjusting) return
    const adjustedQty = resolveAdjustedQty(adjusting)
    if (adjustedQty === null) {
      toast.warning('Adjustment input is invalid.')
      return
    }
    try {
      await adjustMut.mutateAsync({
        item_id:      adjusting.bal.item_id,
        location_id:  adjusting.bal.location_id,
        adjusted_qty: adjustedQty,
        remarks:      adjusting.remarks.trim(),
      })
      toast.success('Stock balance adjusted.')
      setAdjusting(null)
      setShowConfirm(false)
    } catch (err) {
      if (isHandledApiError(err)) {
        const handledMsg = (err as { message?: string })?.message
        if (handledMsg) toast.warning(handledMsg)
        return
      }
      toast.error(firstErrorMessage(err, 'Stock adjustment failed.'))
    }
  }

  return (
    <div>
      <PageHeader
        title="Stock Balances"
        actions={
          <div className="flex items-center gap-2">
            <ExportButton
              data={data?.data ?? []}
              columns={[
                { key: 'item.item_code', label: 'Item Code' },
                { key: 'item.name', label: 'Item Name' },
                { key: 'quantity_on_hand', label: 'On Hand' },
                { key: 'quantity_reserved', label: 'Reserved' },
                { key: 'reorder_point', label: 'Reorder Point' },
                { key: 'warehouse_location.name', label: 'Location' },
              ]}
              filename="stock-balances"
            />
            <Link to="/inventory/locations" className="flex items-center gap-2 px-3 py-2 border border-neutral-300 text-neutral-700 text-sm rounded hover:bg-neutral-50">
              <MapPin className="w-4 h-4" />
              Locations
            </Link>
            <Link to="/inventory/ledger" className="flex items-center gap-2 px-3 py-2 border border-neutral-300 text-neutral-700 text-sm rounded hover:bg-neutral-50">
              <List className="w-4 h-4" />
              Item Movements
            </Link>
          </div>
        }
      />

      {/* Filters */}
      <div className="flex flex-wrap items-center gap-3 mb-5">
        <SearchInput
          value={search}
          onChange={setSearch}
          onSearch={handleSearch}
          placeholder="Search item code or name..."
          className="w-52"
        />
        <select
          value={locationId}
          onChange={(e) => { setLocationId(e.target.value ? Number(e.target.value) : ''); setPage(1) }}
          className="text-sm border border-neutral-300 rounded px-3 py-2 focus:outline-none focus:ring-1 focus:ring-neutral-400 bg-white"
        >
          <option value="">All Locations</option>
          {(locations ?? []).map((l) => <option key={l.id} value={l.id}>{l.code} — {l.name}</option>)}
        </select>
        <label className="flex items-center gap-2 text-sm text-neutral-600 cursor-pointer">
          <input
            type="checkbox"
            checked={lowStock}
            onChange={(e) => { setLowStock(e.target.checked); setPage(1) }}
            className="rounded border-neutral-300"
          />
          Low stock only
        </label>
        <button
          onClick={handleRefresh}
          className="flex items-center gap-1.5 px-3 py-2 text-sm text-neutral-600 border border-neutral-300 rounded hover:bg-neutral-50"
          title="Refresh stock balances"
        >
          <RefreshCw className="w-4 h-4" />
          Refresh
        </button>
      </div>

      {isLoading && <SkeletonLoader rows={10} />}
      {isError && (
        <div className="flex items-center gap-2 text-red-600 text-sm">
          <AlertTriangle className="w-4 h-4" /> Failed to load stock balances.
        </div>
      )}

      {!isLoading && !isError && (
        <>
          <div className="bg-white border border-neutral-200 rounded-xl overflow-hidden">
            <table className="min-w-full text-sm">
              <thead className="bg-neutral-50 border-b border-neutral-200">
                <tr>
                  {['Item Code', 'Name', 'Location', 'UOM', 'On Hand', 'Reorder Pt.', '', ''].map((h, i) => (
                    <th key={i} className="px-4 py-3 text-left text-xs font-medium text-neutral-600">{h}</th>
                  ))}
                </tr>
              </thead>
              <tbody className="divide-y divide-neutral-100">
                {data?.data?.length === 0 && (
                  <tr>
                    <td colSpan={8} className="px-4 py-8 text-center text-neutral-400 text-sm">No stock records found.</td>
                  </tr>
                )}
                {data?.data?.map((bal) => {
                  const onHand         = parseFloat(bal.quantity_on_hand)
                  const reorderPt      = parseFloat(bal.item?.reorder_point ?? '0')
                  const isBelowReorder = onHand <= reorderPt
                  const isActiveRow    = adjusting?.bal.item_id === bal.item_id && adjusting?.bal.location_id === bal.location_id

                  return (
                    <React.Fragment key={`${bal.item_id}-${bal.location_id}`}>
                      <tr className={`hover:bg-neutral-50 ${isBelowReorder ? 'bg-red-50/30' : ''} ${isActiveRow ? 'bg-neutral-50' : ''}`}>
                        <td className="px-4 py-3 font-mono text-neutral-900 font-medium">{bal.item?.item_code ?? `#${bal.item_id}`}</td>
                        <td className="px-4 py-3 text-neutral-900">{bal.item?.name ?? '—'}</td>
                        <td className="px-4 py-3 text-neutral-500">{bal.location ? `${bal.location.code} — ${bal.location.name}` : `#${bal.location_id}`}</td>
                        <td className="px-4 py-3 text-neutral-500">{bal.item?.unit_of_measure ?? '—'}</td>
                        <td className={`px-4 py-3 font-semibold tabular-nums ${isBelowReorder ? 'text-red-600' : 'text-neutral-900'}`}>
                          {onHand.toLocaleString('en-PH', { maximumFractionDigits: 4 })}
                        </td>
                        <td className="px-4 py-3 text-neutral-400 tabular-nums">
                          {reorderPt.toLocaleString('en-PH', { maximumFractionDigits: 4 })}
                        </td>
                        <td className="px-4 py-3">
                          {isBelowReorder && (
                            <div className="flex items-center gap-1 text-red-500 text-xs font-semibold">
                              <AlertCircle className="w-3.5 h-3.5" /> Low
                            </div>
                          )}
                        </td>
                        <td className="px-4 py-3 text-right">
                          {canAdjust && (
                            <button
                              onClick={() => isActiveRow
                                ? setAdjusting(null)
                                : setAdjusting({ bal, mode: 'delta', newQty: onHand.toString(), deltaQty: '', remarks: '' })
                              }
                              className="px-3 py-1 text-xs border border-neutral-300 rounded hover:bg-neutral-50 text-neutral-600"
                            >
                              {isActiveRow ? 'Cancel' : 'Adjust'}
                            </button>
                          )}
                        </td>
                      </tr>

                      {/* Inline adjustment panel */}
                      {isActiveRow && canAdjust && (
                        <tr key={`${bal.item_id}-${bal.location_id}-adjust`}>
                          <td colSpan={8} className="px-4 py-4 bg-neutral-50 border-t border-neutral-200">
                            <div className="flex flex-wrap items-start gap-4">
                              <div>
                                <label className="block text-xs font-medium text-neutral-600 mb-1">Adjustment Mode</label>
                                <div className="flex items-center gap-2 mb-2">
                                  <button
                                    type="button"
                                    onClick={() => setAdjusting({ ...adjusting, mode: 'delta' })}
                                    className={`px-3 py-1 text-xs rounded border ${adjusting.mode === 'delta' ? 'bg-neutral-900 text-white border-neutral-900' : 'bg-white text-neutral-600 border-neutral-300 hover:bg-neutral-50'}`}
                                  >
                                    Add / Reduce
                                  </button>
                                  <button
                                    type="button"
                                    onClick={() => setAdjusting({ ...adjusting, mode: 'set', newQty: parseFloat(bal.quantity_on_hand).toString() })}
                                    className={`px-3 py-1 text-xs rounded border ${adjusting.mode === 'set' ? 'bg-neutral-900 text-white border-neutral-900' : 'bg-white text-neutral-600 border-neutral-300 hover:bg-neutral-50'}`}
                                  >
                                    Set Exact Balance
                                  </button>
                                </div>

                                {adjusting.mode === 'delta' ? (
                                  <>
                                    <label className="block text-xs font-medium text-neutral-600 mb-1">
                                      Change Quantity <span className="text-neutral-400">(use + / -)</span>
                                    </label>
                                    <input
                                      type="number"
                                      step="any"
                                      value={adjusting.deltaQty}
                                      onChange={(e) => setAdjusting({ ...adjusting, deltaQty: e.target.value })}
                                      placeholder="e.g. +5 or -2"
                                      className="w-36 text-sm border border-neutral-300 rounded px-3 py-2 focus:outline-none focus:ring-1 focus:ring-neutral-400"
                                    />
                                  </>
                                ) : (
                                  <>
                                <label className="block text-xs font-medium text-neutral-600 mb-1">
                                  New Balance <span className="text-neutral-400">({bal.item?.unit_of_measure})</span>
                                </label>
                                <input
                                  type="number"
                                  min="0"
                                  step="any"
                                  value={adjusting.newQty}
                                  onChange={(e) => setAdjusting({ ...adjusting, newQty: e.target.value })}
                                  className="w-36 text-sm border border-neutral-300 rounded px-3 py-2 focus:outline-none focus:ring-1 focus:ring-neutral-400"
                                />
                                  </>
                                )}

                                {resolveAdjustedQty(adjusting) !== null && (
                                  <p className="text-xs text-neutral-500 mt-1">
                                    {resolveAdjustedQty(adjusting)! >= onHand
                                      ? <span className="text-green-600">+{(resolveAdjustedQty(adjusting)! - onHand).toLocaleString('en-PH', { maximumFractionDigits: 4 })} increase</span>
                                      : <span className="text-red-600">−{(onHand - resolveAdjustedQty(adjusting)!).toLocaleString('en-PH', { maximumFractionDigits: 4 })} decrease</span>
                                    }
                                  </p>
                                )}
                              </div>
                              <div className="flex-1 min-w-60">
                                <label className="block text-xs font-medium text-neutral-600 mb-1">
                                  Reason <span className="text-neutral-400">(min. 10 characters, visible in ledger)</span>
                                </label>
                                <input
                                  type="text"
                                  value={adjusting.remarks}
                                  onChange={(e) => setAdjusting({ ...adjusting, remarks: e.target.value })}
                                  placeholder="e.g. Physical count correction — cycle count March 2026"
                                  className="w-full text-sm border border-neutral-300 rounded px-3 py-2 focus:outline-none focus:ring-1 focus:ring-neutral-400"
                                />
                              </div>
                              <div className="flex items-end gap-2 pb-0.5">
                                <button
                                  onClick={handleAdjustClick}
                                  disabled={adjustMut.isPending}
                                  className="px-4 py-2 text-sm font-medium bg-neutral-900 hover:bg-neutral-800 text-white rounded disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                                >
                                  {adjustMut.isPending ? 'Saving…' : 'Confirm Adjustment'}
                                </button>
                                <button
                                  onClick={() => setAdjusting(null)}
                                  className="px-4 py-2 text-sm font-medium text-neutral-700 bg-white border border-neutral-300 rounded hover:bg-neutral-50 transition-colors"
                                >
                                  Cancel
                                </button>
                              </div>
                            </div>
                          </td>
                        </tr>
                      )}
                    </React.Fragment>
                  )
                })}
              </tbody>
            </table>
          </div>

          {/* Pagination */}
          {data && data.meta && data.meta.last_page > 1 && (
            <div className="flex items-center justify-between mt-4 text-sm text-neutral-600">
              <span>Page {data.meta.current_page} of {data.meta.last_page} · {data.meta.total} records</span>
              <div className="flex gap-2">
                <button
                  onClick={() => setPage((p) => Math.max(1, p - 1))}
                  disabled={page === 1}
                  className="px-3 py-1.5 border border-neutral-300 rounded disabled:opacity-40 disabled:cursor-not-allowed hover:bg-neutral-50"
                >
                  Previous
                </button>
                <button
                  onClick={() => setPage((p) => p + 1)}
                  disabled={page >= data.meta.last_page}
                  className="px-3 py-1.5 border border-neutral-300 rounded disabled:opacity-40 disabled:cursor-not-allowed hover:bg-neutral-50"
                >
                  Next
                </button>
              </div>
            </div>
          )}
        </>
      )}

      {/* Confirmation Modal */}
      {showConfirm && adjusting && (
        <ConfirmDialog
          title="Confirm Stock Adjustment"
          description={
            <div className="space-y-2">
              <p>You are about to adjust stock for <strong>{adjusting.bal.item?.item_code}</strong>:</p>
              <ul className="text-sm text-neutral-700 list-disc pl-4 space-y-1">
                <li><strong>Current:</strong> {parseFloat(adjusting.bal.quantity_on_hand).toLocaleString('en-PH', { maximumFractionDigits: 4 })}</li>
                <li><strong>New:</strong> {(resolveAdjustedQty(adjusting) ?? 0).toLocaleString('en-PH', { maximumFractionDigits: 4 })}</li>
                <li><strong>Location:</strong> {adjusting.bal.location?.code ?? `#${adjusting.bal.location_id}`}</li>
              </ul>
              <p className="text-amber-600 text-xs">This action will affect inventory levels immediately.</p>
            </div>
          }
          confirmLabel="Confirm Adjustment"
          onConfirm={handleAdjust}
        >
          <span />
        </ConfirmDialog>
      )}
    </div>
  )
}
