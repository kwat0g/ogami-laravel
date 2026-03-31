import { useState } from 'react'
import { Truck, Plus, Wrench, XCircle } from 'lucide-react'
import { toast } from 'sonner'
import { useVehicles } from '@/hooks/useDelivery'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import api from '@/lib/api'
import { useAuthStore } from '@/stores/authStore'
import { PageHeader } from '@/components/ui/PageHeader'
import { Card } from '@/components/ui/Card'
import { firstErrorMessage } from '@/lib/errorHandler'

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
}

const STATUS_COLORS: Record<string, string> = {
  active: 'bg-green-100 text-green-700',
  maintenance: 'bg-amber-100 text-amber-700',
  decommissioned: 'bg-neutral-100 text-neutral-400',
}

const VEHICLE_TYPES = ['Truck', 'Van', 'Pickup', 'Motorcycle', 'Trailer', 'Other']

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

  const [code, setCode] = useState(vehicle?.code ?? '')
  const [name, setName] = useState(vehicle?.name ?? '')
  const [type, setType] = useState(vehicle?.type ?? 'Truck')
  const [makeModel, setMakeModel] = useState(vehicle?.make_model ?? '')
  const [plateNumber, setPlateNumber] = useState(vehicle?.plate_number ?? '')
  const [status, setStatus] = useState(vehicle?.status ?? 'active')
  const [notes, setNotes] = useState(vehicle?.notes ?? '')

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
                {VEHICLE_TYPES.map(t => <option key={t} value={t}>{t}</option>)}
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
                <option value="active">Active</option>
                <option value="maintenance">Under Maintenance</option>
                <option value="decommissioned">Decommissioned</option>
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

export default function FleetPage() {
  const { hasPermission } = useAuthStore()
  const canManage = hasPermission('delivery.manage')
  const { data, isLoading } = useVehicles()
  const vehicles: Vehicle[] = data?.data ?? []
  const [showForm, setShowForm] = useState(false)
  const [editVehicle, setEditVehicle] = useState<Vehicle | null>(null)

  const activeCount = vehicles.filter(v => v.status === 'active').length
  const maintenanceCount = vehicles.filter(v => v.status === 'maintenance').length

  return (
    <div className="space-y-6">
      <PageHeader
        title="Fleet Management"
        subtitle={`${activeCount} active, ${maintenanceCount} in maintenance`}
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

      {isLoading ? (
        <p className="text-sm text-neutral-500 mt-4">Loading fleet...</p>
      ) : vehicles.length === 0 ? (
        <Card>
          <div className="px-6 py-12 text-center">
            <Truck className="w-12 h-12 text-neutral-300 mx-auto mb-4" />
            <p className="text-neutral-500 text-sm">No vehicles registered yet.</p>
            {canManage && (
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
                <th className="text-left px-4 py-3 font-medium text-neutral-600 text-xs uppercase">Code</th>
                <th className="text-left px-4 py-3 font-medium text-neutral-600 text-xs uppercase">Name</th>
                <th className="text-left px-4 py-3 font-medium text-neutral-600 text-xs uppercase">Plate</th>
                <th className="text-left px-4 py-3 font-medium text-neutral-600 text-xs uppercase">Type</th>
                <th className="text-left px-4 py-3 font-medium text-neutral-600 text-xs uppercase">Make/Model</th>
                <th className="text-left px-4 py-3 font-medium text-neutral-600 text-xs uppercase">Status</th>
                {canManage && <th className="text-right px-4 py-3 font-medium text-neutral-600 text-xs uppercase">Actions</th>}
              </tr>
            </thead>
            <tbody className="divide-y divide-neutral-100">
              {vehicles.map(v => (
                <tr key={v.id} className="hover:bg-neutral-50">
                  <td className="px-4 py-3 font-mono text-xs text-neutral-700">{v.code}</td>
                  <td className="px-4 py-3 font-medium text-neutral-900">{v.name}</td>
                  <td className="px-4 py-3 text-neutral-700">{v.plate_number}</td>
                  <td className="px-4 py-3 text-neutral-600 capitalize">{v.type}</td>
                  <td className="px-4 py-3 text-neutral-500">{v.make_model ?? '-'}</td>
                  <td className="px-4 py-3">
                    <span className={`px-2 py-0.5 rounded text-xs font-medium ${STATUS_COLORS[v.status] ?? 'bg-neutral-100 text-neutral-600'}`}>
                      {v.status === 'maintenance' ? 'Maintenance' : v.status}
                    </span>
                  </td>
                  {canManage && (
                    <td className="px-4 py-3 text-right">
                      <button
                        onClick={() => { setEditVehicle(v); setShowForm(true) }}
                        className="text-xs text-neutral-600 hover:text-neutral-900 underline"
                      >
                        Edit
                      </button>
                    </td>
                  )}
                </tr>
              ))}
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
