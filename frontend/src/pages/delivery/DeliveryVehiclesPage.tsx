import { useState, useMemo, useEffect } from 'react'
import { Truck, Plus, Search, MoreHorizontal, Pencil, Power, Wrench, XCircle, MapPin, Package } from 'lucide-react'
import { toast } from 'sonner'
import { useVehicles } from '@/hooks/useDelivery'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import api from '@/lib/api'
import { useAuthStore } from '@/stores/authStore'
import { PageHeader } from '@/components/ui/PageHeader'
import { Card } from '@/components/ui/Card'
import { firstErrorMessage } from '@/lib/errorHandler'
import { Link } from 'react-router-dom'

interface CurrentDelivery {
  ulid: string
  dr_reference: string
  status: string
  direction: string
  driver_name: string | null
  receipt_date: string | null
}

interface Vehicle {
  id: number
  ulid: string
  code: string
  name: string
  type: string
  make_model: string | null
  plate_number: string
  status: string
  notes: string | null
  availability: 'available' | 'in_delivery'
  active_deliveries_count: number
  completed_deliveries_count: number
  total_deliveries_count: number
  current_delivery: CurrentDelivery | null
}

const VEHICLE_TYPES = [
  { value: 'truck', label: 'Truck' },
  { value: 'van', label: 'Van' },
  { value: 'pickup', label: 'Pickup' },
  { value: 'motorcycle', label: 'Motorcycle' },
  { value: 'trailer', label: 'Trailer' },
  { value: 'other', label: 'Other' },
]

const ALL_STATUSES = [
  { value: 'active', label: 'Active', color: 'bg-green-100 text-green-700' },
  { value: 'inactive', label: 'Inactive', color: 'bg-neutral-100 text-neutral-500' },
  { value: 'maintenance', label: 'Maintenance', color: 'bg-amber-100 text-amber-700' },
  { value: 'decommissioned', label: 'Decommissioned', color: 'bg-red-50 text-red-400' },
]

const AVAILABILITY_BADGE: Record<string, { label: string; color: string }> = {
  available: { label: 'Available', color: 'bg-green-50 text-green-600 border border-green-200' },
  in_delivery: { label: 'In Delivery', color: 'bg-blue-50 text-blue-600 border border-blue-200' },
}

const DR_STATUS_COLORS: Record<string, string> = {
  confirmed: 'text-amber-600',
  dispatched: 'text-blue-600',
  in_transit: 'text-indigo-600',
  partially_delivered: 'text-purple-600',
}

function statusColor(status: string): string {
  return ALL_STATUSES.find(s => s.value === status)?.color ?? 'bg-neutral-100 text-neutral-600'
}

function statusLabel(status: string): string {
  return ALL_STATUSES.find(s => s.value === status)?.label ?? status
}

// ── Vehicle Form Modal ─────────────────────────────────────────────────────
function VehicleFormModal({
  open,
  onClose,
  vehicle,
}: {
  open: boolean
  onClose: () => void
  vehicle?: Vehicle | null
}) {
  const qc = useQueryClient()
  const isEdit = !!vehicle

  const [code, setCode] = useState('')
  const [name, setName] = useState('')
  const [type, setType] = useState('truck')
  const [makeModel, setMakeModel] = useState('')
  const [plateNumber, setPlateNumber] = useState('')
  const [status, setStatus] = useState('active')
  const [notes, setNotes] = useState('')

  // Reset form state when vehicle prop changes (fixes stale data bug)
  useEffect(() => {
    if (open) {
      setCode(vehicle?.code ?? '')
      setName(vehicle?.name ?? '')
      setType(vehicle?.type ?? 'truck')
      setMakeModel(vehicle?.make_model ?? '')
      setPlateNumber(vehicle?.plate_number ?? '')
      setStatus(vehicle?.status ?? 'active')
      setNotes(vehicle?.notes ?? '')
    }
  }, [open, vehicle])

  const mutation = useMutation({
    mutationFn: (data: Record<string, unknown>) =>
      isEdit
        ? api.patch(`/delivery/vehicles/${vehicle.id}`, data).then(r => r.data)
        : api.post('/delivery/vehicles', data).then(r => r.data),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['vehicles'] })
      toast.success(isEdit ? 'Vehicle updated' : 'Vehicle added')
      onClose()
    },
    onError: (err) => toast.error(firstErrorMessage(err)),
  })

  const handleSubmit = () => {
    if (!code.trim() || !name.trim() || !plateNumber.trim()) {
      toast.error('Code, name, and plate number are required')
      return
    }
    mutation.mutate({
      code: code.trim(),
      name: name.trim(),
      type,
      make_model: makeModel.trim() || null,
      plate_number: plateNumber.trim(),
      status,
      notes: notes.trim() || null,
    })
  }

  if (!open) return null

  return (
    <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div className="bg-white rounded-xl w-full max-w-md shadow-xl border border-neutral-200">
        <div className="p-5 border-b border-neutral-100">
          <h2 className="text-lg font-semibold text-neutral-900 flex items-center gap-2">
            <Truck className="h-5 w-5 text-blue-600" />
            {isEdit ? 'Edit Vehicle' : 'Add Vehicle'}
          </h2>
        </div>

        <div className="p-5 space-y-3">
          <div className="grid grid-cols-2 gap-3">
            <div>
              <label className="block text-xs font-medium text-neutral-600 mb-1">Code *</label>
              <input
                type="text"
                value={code}
                onChange={e => setCode(e.target.value)}
                placeholder="e.g. TRK-001"
                disabled={isEdit}
                className="w-full border border-neutral-200 rounded-lg px-3 py-2 text-sm disabled:bg-neutral-50"
              />
            </div>
            <div>
              <label className="block text-xs font-medium text-neutral-600 mb-1">Plate Number *</label>
              <input
                type="text"
                value={plateNumber}
                onChange={e => setPlateNumber(e.target.value)}
                placeholder="e.g. ABC 1234"
                className="w-full border border-neutral-200 rounded-lg px-3 py-2 text-sm"
              />
            </div>
          </div>

          <div>
            <label className="block text-xs font-medium text-neutral-600 mb-1">Name *</label>
            <input
              type="text"
              value={name}
              onChange={e => setName(e.target.value)}
              placeholder="e.g. Delivery Truck 1"
              className="w-full border border-neutral-200 rounded-lg px-3 py-2 text-sm"
            />
          </div>

          <div className="grid grid-cols-2 gap-3">
            <div>
              <label className="block text-xs font-medium text-neutral-600 mb-1">Type</label>
              <select
                value={type}
                onChange={e => setType(e.target.value)}
                className="w-full border border-neutral-200 rounded-lg px-3 py-2 text-sm bg-white"
              >
                {VEHICLE_TYPES.map(t => <option key={t.value} value={t.value}>{t.label}</option>)}
              </select>
            </div>
            <div>
              <label className="block text-xs font-medium text-neutral-600 mb-1">Make / Model</label>
              <input
                type="text"
                value={makeModel}
                onChange={e => setMakeModel(e.target.value)}
                placeholder="e.g. Isuzu Elf"
                className="w-full border border-neutral-200 rounded-lg px-3 py-2 text-sm"
              />
            </div>
          </div>

          {isEdit && (
            <div>
              <label className="block text-xs font-medium text-neutral-600 mb-1">Status</label>
              <select
                value={status}
                onChange={e => setStatus(e.target.value)}
                className="w-full border border-neutral-200 rounded-lg px-3 py-2 text-sm bg-white"
              >
                {ALL_STATUSES.map(s => <option key={s.value} value={s.value}>{s.label}</option>)}
              </select>
            </div>
          )}

          <div>
            <label className="block text-xs font-medium text-neutral-600 mb-1">Notes</label>
            <textarea
              value={notes}
              onChange={e => setNotes(e.target.value)}
              rows={2}
              placeholder="Optional notes..."
              className="w-full border border-neutral-200 rounded-lg px-3 py-2 text-sm"
            />
          </div>
        </div>

        <div className="p-4 border-t border-neutral-100 flex gap-3">
          <button
            onClick={onClose}
            className="flex-1 py-2.5 border border-neutral-200 text-neutral-700 font-medium rounded-lg hover:bg-neutral-50 text-sm"
          >
            Cancel
          </button>
          <button
            onClick={handleSubmit}
            disabled={mutation.isPending}
            className="flex-1 py-2.5 bg-neutral-900 text-white font-medium rounded-lg hover:bg-neutral-800 text-sm disabled:opacity-50"
          >
            {mutation.isPending ? 'Saving...' : isEdit ? 'Update' : 'Add Vehicle'}
          </button>
        </div>
      </div>
    </div>
  )
}

// ── Status Filter Tabs ─────────────────────────────────────────────────────
const FILTER_TABS = [
  { value: 'all', label: 'All' },
  { value: 'active', label: 'Active' },
  { value: 'available', label: 'Available' },
  { value: 'in_delivery', label: 'In Delivery' },
  { value: 'maintenance', label: 'Maintenance' },
  { value: 'inactive', label: 'Inactive' },
  { value: 'decommissioned', label: 'Decommissioned' },
]

// ── Quick Status Action Menu ───────────────────────────────────────────────
function VehicleRowActions({
  vehicle,
  onEdit,
}: {
  vehicle: Vehicle
  onEdit: () => void
}) {
  const [menuOpen, setMenuOpen] = useState(false)
  const qc = useQueryClient()

  const statusMutation = useMutation({
    mutationFn: ({ id, status }: { id: number; status: string }) =>
      api.patch(`/delivery/vehicles/${id}`, { status }).then(r => r.data),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['vehicles'] })
      toast.success('Vehicle status updated')
    },
    onError: (err) => toast.error(firstErrorMessage(err)),
  })

  const quickActions = [
    { label: 'Edit', icon: Pencil, onClick: () => { onEdit(); setMenuOpen(false) } },
    ...(vehicle.status !== 'active' ? [{ label: 'Mark Active', icon: Power, onClick: () => { statusMutation.mutate({ id: vehicle.id, status: 'active' }); setMenuOpen(false) } }] : []),
    ...(vehicle.status !== 'maintenance' ? [{ label: 'Mark Maintenance', icon: Wrench, onClick: () => { statusMutation.mutate({ id: vehicle.id, status: 'maintenance' }); setMenuOpen(false) } }] : []),
    ...(vehicle.status !== 'inactive' ? [{ label: 'Mark Inactive', icon: XCircle, onClick: () => { statusMutation.mutate({ id: vehicle.id, status: 'inactive' }); setMenuOpen(false) } }] : []),
    ...(vehicle.status !== 'decommissioned' ? [{ label: 'Decommission', icon: XCircle, onClick: () => { statusMutation.mutate({ id: vehicle.id, status: 'decommissioned' }); setMenuOpen(false) } }] : []),
  ]

  return (
    <div className="relative">
      <button
        onClick={() => setMenuOpen(!menuOpen)}
        className="p-1.5 rounded hover:bg-neutral-100 text-neutral-500 hover:text-neutral-700"
      >
        <MoreHorizontal className="h-4 w-4" />
      </button>
      {menuOpen && (
        <>
          <div className="fixed inset-0 z-40" onClick={() => setMenuOpen(false)} />
          <div className="absolute right-0 top-8 z-50 bg-white border border-neutral-200 rounded-lg shadow-lg py-1 w-48">
            {quickActions.map(a => (
              <button
                key={a.label}
                onClick={a.onClick}
                disabled={statusMutation.isPending}
                className="w-full text-left px-3 py-2 text-sm hover:bg-neutral-50 flex items-center gap-2 text-neutral-700 disabled:opacity-50"
              >
                <a.icon className="h-3.5 w-3.5" />
                {a.label}
              </button>
            ))}
          </div>
        </>
      )}
    </div>
  )
}

// ── Main Page ──────────────────────────────────────────────────────────────
export default function DeliveryVehiclesPage() {
  const { hasPermission } = useAuthStore()
  const canManage = hasPermission('delivery.manage')
  const { data, isLoading } = useVehicles()
  const vehicles: Vehicle[] = (data?.data ?? []) as Vehicle[]
  const [showForm, setShowForm] = useState(false)
  const [editVehicle, setEditVehicle] = useState<Vehicle | null>(null)
  const [statusFilter, setStatusFilter] = useState('all')
  const [search, setSearch] = useState('')

  // Filter + search
  const filtered = useMemo(() => {
    let list = vehicles
    if (statusFilter === 'available') {
      list = list.filter(v => v.availability === 'available' && v.status === 'active')
    } else if (statusFilter === 'in_delivery') {
      list = list.filter(v => v.availability === 'in_delivery')
    } else if (statusFilter !== 'all') {
      list = list.filter(v => v.status === statusFilter)
    }
    if (search.trim()) {
      const q = search.toLowerCase()
      list = list.filter(v =>
        v.name.toLowerCase().includes(q) ||
        v.code.toLowerCase().includes(q) ||
        v.plate_number.toLowerCase().includes(q) ||
        (v.make_model ?? '').toLowerCase().includes(q)
      )
    }
    return list
  }, [vehicles, statusFilter, search])

  // Count per filter for badges
  const counts = useMemo(() => {
    const c: Record<string, number> = { all: vehicles.length }
    for (const v of vehicles) {
      c[v.status] = (c[v.status] ?? 0) + 1
    }
    c['available'] = vehicles.filter(v => v.availability === 'available' && v.status === 'active').length
    c['in_delivery'] = vehicles.filter(v => v.availability === 'in_delivery').length
    return c
  }, [vehicles])

  // Summary stats
  const totalActive = counts['active'] ?? 0
  const inDelivery = counts['in_delivery'] ?? 0
  const inMaintenance = counts['maintenance'] ?? 0
  const available = counts['available'] ?? 0

  return (
    <div className="space-y-6">
      <PageHeader
        title="Delivery Vehicles"
        subtitle={`${totalActive} active -- ${available} available, ${inDelivery} in delivery, ${inMaintenance} in maintenance`}
        icon={<Truck className="w-5 h-5 text-neutral-600" />}
        actions={
          canManage ? (
            <button
              onClick={() => { setEditVehicle(null); setShowForm(true) }}
              className="inline-flex items-center gap-1.5 text-sm bg-neutral-900 text-white rounded px-4 py-2 font-medium hover:bg-neutral-800"
            >
              <Plus className="w-4 h-4" /> Add Vehicle
            </button>
          ) : undefined
        }
      />

      {/* Summary Cards */}
      {!isLoading && vehicles.length > 0 && (
        <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
          <div className="bg-white border border-neutral-200 rounded-lg p-4">
            <p className="text-2xl font-bold text-neutral-900">{totalActive}</p>
            <p className="text-xs text-neutral-500 mt-0.5">Active Vehicles</p>
          </div>
          <div className="bg-white border border-green-200 rounded-lg p-4">
            <p className="text-2xl font-bold text-green-700">{available}</p>
            <p className="text-xs text-green-600 mt-0.5">Available Now</p>
          </div>
          <div className="bg-white border border-blue-200 rounded-lg p-4">
            <p className="text-2xl font-bold text-blue-700">{inDelivery}</p>
            <p className="text-xs text-blue-600 mt-0.5">In Delivery</p>
          </div>
          <div className="bg-white border border-amber-200 rounded-lg p-4">
            <p className="text-2xl font-bold text-amber-700">{inMaintenance}</p>
            <p className="text-xs text-amber-600 mt-0.5">Maintenance</p>
          </div>
        </div>
      )}

      {/* Filter tabs + Search */}
      <div className="flex flex-col sm:flex-row gap-3 items-start sm:items-center justify-between">
        <div className="flex gap-1 flex-wrap">
          {FILTER_TABS.map(tab => (
            <button
              key={tab.value}
              onClick={() => setStatusFilter(tab.value)}
              className={`px-3 py-1.5 text-xs font-medium rounded-full transition-colors ${
                statusFilter === tab.value
                  ? 'bg-neutral-900 text-white'
                  : 'bg-neutral-100 text-neutral-600 hover:bg-neutral-200'
              }`}
            >
              {tab.label}
              {(counts[tab.value] ?? 0) > 0 && (
                <span className="ml-1.5 text-[10px] opacity-70">
                  {counts[tab.value]}
                </span>
              )}
            </button>
          ))}
        </div>
        <div className="relative w-full sm:w-64">
          <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-neutral-400" />
          <input
            type="text"
            value={search}
            onChange={e => setSearch(e.target.value)}
            placeholder="Search name, code, plate..."
            className="w-full pl-9 pr-3 py-2 border border-neutral-200 rounded-lg text-sm focus:ring-2 focus:ring-neutral-300 focus:border-neutral-300"
          />
        </div>
      </div>

      {isLoading ? (
        <p className="text-sm text-neutral-500 mt-4">Loading vehicles...</p>
      ) : filtered.length === 0 ? (
        <Card>
          <div className="px-6 py-12 text-center">
            <Truck className="w-12 h-12 text-neutral-300 mx-auto mb-4" />
            <p className="text-neutral-500 text-sm">
              {vehicles.length === 0
                ? 'No vehicles registered yet.'
                : 'No vehicles match your filters.'}
            </p>
            {canManage && vehicles.length === 0 && (
              <button
                onClick={() => setShowForm(true)}
                className="mt-3 text-sm text-blue-600 hover:text-blue-700 font-medium"
              >
                Add your first vehicle
              </button>
            )}
          </div>
        </Card>
      ) : (
        <div className="bg-white border border-neutral-200 rounded-xl overflow-hidden">
          <table className="w-full text-sm">
            <thead className="bg-neutral-50 border-b border-neutral-200">
              <tr>
                <th className="text-left px-4 py-3 font-medium text-neutral-600 text-xs uppercase">Vehicle</th>
                <th className="text-left px-4 py-3 font-medium text-neutral-600 text-xs uppercase">Plate</th>
                <th className="text-left px-4 py-3 font-medium text-neutral-600 text-xs uppercase">Type</th>
                <th className="text-left px-4 py-3 font-medium text-neutral-600 text-xs uppercase">Status</th>
                <th className="text-left px-4 py-3 font-medium text-neutral-600 text-xs uppercase">Availability</th>
                <th className="text-left px-4 py-3 font-medium text-neutral-600 text-xs uppercase">Deliveries</th>
                {canManage && <th className="text-right px-4 py-3 font-medium text-neutral-600 text-xs uppercase">Actions</th>}
              </tr>
            </thead>
            <tbody className="divide-y divide-neutral-100">
              {filtered.map(v => {
                const avail = AVAILABILITY_BADGE[v.availability] ?? AVAILABILITY_BADGE.available
                return (
                  <tr key={v.id} className="hover:bg-neutral-50">
                    {/* Vehicle name + code + make/model */}
                    <td className="px-4 py-3">
                      <div className="font-medium text-neutral-900">{v.name}</div>
                      <div className="text-xs text-neutral-500 flex gap-2">
                        <span className="font-mono">{v.code}</span>
                        {v.make_model && <span>{v.make_model}</span>}
                      </div>
                    </td>
                    <td className="px-4 py-3 text-neutral-700">{v.plate_number}</td>
                    <td className="px-4 py-3 text-neutral-600 capitalize">{v.type}</td>
                    <td className="px-4 py-3">
                      <span className={`px-2 py-0.5 rounded text-xs font-medium ${statusColor(v.status)}`}>
                        {statusLabel(v.status)}
                      </span>
                    </td>
                    {/* Availability + current delivery */}
                    <td className="px-4 py-3">
                      <span className={`px-2 py-0.5 rounded text-xs font-medium ${avail.color}`}>
                        {avail.label}
                      </span>
                      {v.current_delivery && (
                        <Link
                          to={`/delivery/receipts/${v.current_delivery.ulid}`}
                          className="flex items-center gap-1 mt-1 text-xs hover:underline"
                        >
                          <MapPin className="h-3 w-3 text-blue-500" />
                          <span className={DR_STATUS_COLORS[v.current_delivery.status] ?? 'text-neutral-600'}>
                            {v.current_delivery.dr_reference} ({v.current_delivery.status.replace('_', ' ')})
                          </span>
                        </Link>
                      )}
                    </td>
                    {/* Delivery counts */}
                    <td className="px-4 py-3">
                      <div className="flex items-center gap-3 text-xs">
                        <span className="flex items-center gap-1" title="Active deliveries">
                          <Package className="h-3 w-3 text-blue-500" />
                          <span className="text-blue-700 font-medium">{v.active_deliveries_count ?? 0}</span>
                        </span>
                        <span className="text-neutral-400" title="Completed deliveries">
                          {v.completed_deliveries_count ?? 0} done
                        </span>
                        <span className="text-neutral-300" title="Total deliveries">
                          / {v.total_deliveries_count ?? 0} total
                        </span>
                      </div>
                    </td>
                    {canManage && (
                      <td className="px-4 py-3 text-right">
                        <VehicleRowActions
                          vehicle={v}
                          onEdit={() => { setEditVehicle(v); setShowForm(true) }}
                        />
                      </td>
                    )}
                  </tr>
                )
              })}
            </tbody>
          </table>
        </div>
      )}

      <VehicleFormModal
        open={showForm}
        onClose={() => { setShowForm(false); setEditVehicle(null) }}
        vehicle={editVehicle}
      />
    </div>
  )
}
