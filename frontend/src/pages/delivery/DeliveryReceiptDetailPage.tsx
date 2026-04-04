import { useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { Truck, PenTool, User, Package, Clock, CheckCircle, FileDown } from 'lucide-react';
import { toast } from 'sonner';
import MultiPhotoUpload from '@/components/ui/MultiPhotoUpload';
import { downloadFile } from '@/lib/api';
import { deliveryApiPaths } from '@/lib/deliveryApiPaths';

import {
  useDeliveryReceipt,
  useConfirmDeliveryReceipt,
  useMarkDispatched,
  useMarkDelivered,
  usePrepareShipment,
  useRecordPod,
  useVehicles,
} from '@/hooks/useDelivery';
// Driver is a text input -- not linked to employee records because
// drivers may be third-party contractors or temporary staff not in HR system
import { useAuthStore } from '@/stores/authStore';
import SkeletonLoader from '@/components/ui/SkeletonLoader';
import ConfirmDialog from '@/components/ui/ConfirmDialog';
import PageHeader from '@/components/ui/PageHeader';
import StatusBadge from '@/components/ui/StatusBadge';
import { Card, CardHeader, CardBody } from '@/components/ui/Card';
import { InfoRow, InfoList } from '@/components/ui/InfoRow';
import StatusTimeline from '@/components/ui/StatusTimeline';
import ChainRecordTimeline from '@/components/ui/ChainRecordTimeline';
import { getDeliveryReceiptSteps, isRejectedStatus } from '@/lib/workflowSteps';
import type { DrDirection } from '@/types/delivery';

const DIRECTION_COLORS: Record<DrDirection, string> = {
  inbound: 'bg-neutral-100 text-neutral-700',
  outbound: 'bg-neutral-100 text-neutral-700',
};

function formatStatusLabel(status?: string): string {
  if (!status) return '-'

  return status
    .replace(/([a-z])([A-Z])/g, '$1 $2')
    .replace(/_/g, ' ')
    .replace(/\b\w/g, c => c.toUpperCase())
}

// ── Prepare Shipment Modal ──────────────────────────────────────────────────
function PrepareShipmentModal({
  open,
  onClose,
  onSubmit,
  isLoading,
  defaultEstimatedDelivery,
}: {
  open: boolean
  onClose: () => void
  onSubmit: (data: {
    vehicle_id?: number
    driver_name?: string
    carrier?: string
    tracking_number?: string
    estimated_arrival?: string
    notes?: string
  }) => void
  isLoading: boolean
  defaultEstimatedDelivery?: string
}) {
  const [vehicleId, setVehicleId] = useState<number>(0)
  const [driverName, setDriverName] = useState('')
  const [carrier, setCarrier] = useState('Company Fleet')
  const [estimatedArrival, setEstimatedArrival] = useState(defaultEstimatedDelivery ?? '')
  const [notes, setNotes] = useState('')
  const [dispatchPhotos, setDispatchPhotos] = useState<string[]>([])

  const { data: vehiclesData } = useVehicles()
  const vehicles = vehiclesData?.data ?? []

  if (!open) return null

  return (
    <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div className="bg-white rounded-xl w-full max-w-lg shadow-xl border border-neutral-200 max-h-[90vh] overflow-y-auto">
        <div className="p-6 border-b border-neutral-100">
          <h2 className="text-lg font-semibold text-neutral-900 flex items-center gap-2">
            <Truck className="h-5 w-5 text-blue-600" />
            Prepare Shipment
          </h2>
          <p className="text-sm text-neutral-500 mt-1">
            Assign vehicle, driver, and delivery details before dispatching.
          </p>
        </div>

        <div className="p-6 space-y-4">
          {/* Vehicle Selection */}
          <div>
            <label className="block text-sm font-medium text-neutral-700 mb-1">
              <Truck className="h-3.5 w-3.5 inline mr-1" />
              Vehicle
            </label>
            <select
              value={vehicleId || ''}
              onChange={e => setVehicleId(Number(e.target.value))}
              className="w-full border border-neutral-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white"
            >
              <option value="">-- Select Vehicle --</option>
              {vehicles
                .filter((v: any) => !v.status || v.status === 'active')
                .map((v: any) => {
                  const inDelivery = v.availability === 'in_delivery'
                  const activeCount = v.active_deliveries_count ?? 0
                  return (
                    <option key={v.id} value={v.id}>
                      {v.name} ({v.plate_number}) -- {v.type}{inDelivery ? ` [IN DELIVERY - ${activeCount} active]` : ''}
                    </option>
                  )
                })}
            </select>
            {vehicles.length === 0 && (
              <p className="text-xs text-amber-600 mt-1">
                No active vehicles found.{' '}
                <a href="/delivery/vehicles" className="underline hover:text-amber-700">Add vehicles in Delivery Vehicles</a>.
              </p>
            )}
            {vehicleId > 0 && vehicles.find((v: any) => v.id === vehicleId)?.availability === 'in_delivery' && (
              <p className="text-xs text-amber-600 mt-1 flex items-center gap-1">
                <span className="inline-block w-1.5 h-1.5 rounded-full bg-amber-500" />
                This vehicle is currently on delivery. The new shipment will be queued until it returns.
              </p>
            )}
          </div>

          {/* Driver Name */}
          <div>
            <label className="block text-sm font-medium text-neutral-700 mb-1">
              <User className="h-3.5 w-3.5 inline mr-1" />
              Driver Name
            </label>
            <input
              type="text"
              value={driverName}
              onChange={e => setDriverName(e.target.value)}
              placeholder="e.g. Juan dela Cruz"
              className="w-full border border-neutral-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            />
            <p className="text-xs text-neutral-400 mt-1">Can be an employee or third-party driver</p>
          </div>

          <div className="grid grid-cols-2 gap-4">
            {/* Carrier */}
            <div>
              <label className="block text-sm font-medium text-neutral-700 mb-1">Carrier</label>
              <select
                value={carrier}
                onChange={e => setCarrier(e.target.value)}
                className="w-full border border-neutral-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white"
              >
                <option value="Company Fleet">Company Fleet</option>
                <option value="Third-Party Logistics">Third-Party Logistics</option>
                <option value="Client Pickup">Client Pickup</option>
              </select>
            </div>

            {/* Estimated Delivery Date -- auto-filled from client order, read-only */}
            <div>
              <label className="block text-sm font-medium text-neutral-700 mb-1">Estimated Delivery</label>
              <input
                type="date"
                value={estimatedArrival}
                disabled
                className="w-full border border-neutral-200 rounded-lg px-3 py-2 text-sm bg-neutral-50 text-neutral-600"
              />
              <p className="text-xs text-neutral-400 mt-0.5">Based on client requested delivery date</p>
            </div>
          </div>

          {/* Dispatch Photos (max 3) */}
          <MultiPhotoUpload
            photos={dispatchPhotos}
            onChange={setDispatchPhotos}
            maxPhotos={3}
            label="Dispatch Photos"
          />

          {/* Notes */}
          <div>
            <label className="block text-sm font-medium text-neutral-700 mb-1">Notes</label>
            <textarea
              value={notes}
              onChange={e => setNotes(e.target.value)}
              rows={2}
              placeholder="Any special delivery instructions..."
              className="w-full border border-neutral-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            />
          </div>
        </div>

        <div className="p-4 border-t border-neutral-100 flex gap-3">
          <button
            onClick={onClose}
            disabled={isLoading}
            className="flex-1 py-2.5 border border-neutral-200 text-neutral-700 font-medium rounded-lg hover:bg-neutral-50 transition-colors text-sm"
          >
            Cancel
          </button>
          <button
            onClick={() => onSubmit({
              vehicle_id: vehicleId || undefined,
              driver_name: driverName || undefined,
              carrier: carrier || undefined,
              estimated_arrival: estimatedArrival || undefined,
              notes: notes || undefined,
            })}
            disabled={isLoading || !vehicleId}
            className="flex-1 py-2.5 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 transition-colors text-sm disabled:opacity-50"
          >
            {isLoading ? 'Creating...' : 'Create Shipment'}
          </button>
        </div>
      </div>
    </div>
  )
}

// ── POD Summary ──────────────────────────────────────────────────────────────
function PodSummaryCard({
  podData,
}: {
  podData?: {
    pod_receiver_name?: string
    pod_photo_urls?: string[]
    pod_recorded_at?: string
    pod_notes?: string
  }
}) {
  return (
    <Card className="mb-5 border-green-200 bg-green-50/30">
      <CardHeader>
        <span className="flex items-center gap-2 text-green-700">
          <CheckCircle className="h-4 w-4" />
          Proof of Delivery Recorded
        </span>
      </CardHeader>
      <CardBody>
        <InfoList columns={2}>
          <InfoRow label="Receiver" value={podData?.pod_receiver_name ?? '-'} />
          <InfoRow label="Recorded At" value={podData?.pod_recorded_at ? new Date(podData.pod_recorded_at).toLocaleString('en-PH') : '-'} />
          {podData?.pod_notes && <InfoRow label="Notes" value={podData.pod_notes} />}
        </InfoList>
        {(podData?.pod_photo_urls ?? []).length > 0 && (
          <div className="mt-4">
            <p className="text-xs font-medium text-neutral-600 mb-2">POD Photos</p>
            <div className="grid grid-cols-2 sm:grid-cols-3 gap-2">
              {(podData?.pod_photo_urls ?? []).map((photoUrl, index) => (
                <a
                  key={`${photoUrl}-${index}`}
                  href={photoUrl}
                  target="_blank"
                  rel="noreferrer"
                  className="block"
                >
                  <img
                    src={photoUrl}
                    alt={`POD ${index + 1}`}
                    className="h-28 w-full object-cover rounded border border-green-200"
                  />
                </a>
              ))}
            </div>
          </div>
        )}
      </CardBody>
    </Card>
  )
}

// ── POD + Deliver Modal ─────────────────────────────────────────────────────
function PodDeliverModal({
  drUlid,
  drStatus,
  open,
  onClose,
  hasPod,
  podData,
  onMarkDelivered,
  isMarkingDelivered,
}: {
  drUlid: string
  drStatus: string
  open: boolean
  onClose: () => void
  hasPod: boolean
  podData?: {
    pod_receiver_name?: string
    pod_photo_urls?: string[]
    pod_recorded_at?: string
    pod_notes?: string
  }
  onMarkDelivered: () => Promise<void>
  isMarkingDelivered: boolean
}) {
  const recordPod = useRecordPod()
  const [receiverName, setReceiverName] = useState('')
  const [deliveryNotes, setDeliveryNotes] = useState('')
  const [podPhotos, setPodPhotos] = useState<string[]>([])

  if (!open) return null

  const handleSubmitPod = async () => {
    if (hasPod) {
      try {
        await onMarkDelivered()
        onClose()
      } catch {
        // Global mutation onError handler shows toast
      }

      return
    }

    if (!receiverName.trim()) {
      return
    }

    if (podPhotos.length === 0) {
      return
    }

    if (!['dispatched', 'in_transit'].includes(drStatus)) {
      return
    }

    try {
      await recordPod.mutateAsync({
        ulid: drUlid,
        payload: {
          receiver_name: receiverName,
          photos_base64: podPhotos.length > 0 ? podPhotos : undefined,
          delivery_notes: deliveryNotes || undefined,
        },
      })

      await onMarkDelivered()

      toast.success('Proof of Delivery recorded and marked as delivered')
      onClose()
    } catch {
      // Global mutation onError handler shows toast
    }
  }

  return (
    <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div className="bg-white rounded-xl w-full max-w-3xl shadow-xl border border-neutral-200 max-h-[90vh] overflow-y-auto">
        <div className="p-6 border-b border-neutral-100">
          <h2 className="text-lg font-semibold text-neutral-900 flex items-center gap-2">
            <PenTool className="h-5 w-5 text-amber-600" />
            Mark Delivery as Completed
          </h2>
          <p className="text-sm text-neutral-500 mt-1">
            {hasPod
              ? 'Proof of Delivery already exists. You can now finalize this delivery.'
              : 'Record proof of delivery using receiver details and photo evidence.'}
          </p>
        </div>

        <div className="p-6 space-y-4">
          {hasPod ? (
            <InfoList columns={2}>
              <InfoRow label="Receiver" value={podData?.pod_receiver_name ?? '-'} />
              <InfoRow label="Recorded At" value={podData?.pod_recorded_at ? new Date(podData.pod_recorded_at).toLocaleString('en-PH') : '-'} />
            </InfoList>
          ) : (
            <>
              <div className="grid sm:grid-cols-1 gap-4">
                <div>
                  <label className="block text-sm font-medium text-neutral-700 mb-1">
                    <User className="h-3.5 w-3.5 inline mr-1" />
                    Receiver Name <span className="text-red-500">*</span>
                  </label>
                  <input
                    type="text"
                    value={receiverName}
                    onChange={e => setReceiverName(e.target.value)}
                    placeholder="Who received the goods?"
                    className="w-full border border-neutral-200 rounded-lg px-3 py-2 text-sm"
                  />
                </div>
              </div>

              <p className="text-xs text-neutral-500">
                Evidence policy: receiver name and at least one delivery photo are required.
              </p>

              <MultiPhotoUpload
                photos={podPhotos}
                onChange={setPodPhotos}
                maxPhotos={3}
                label="Delivery Photos"
              />

              <div>
                <label className="block text-sm font-medium text-neutral-700 mb-1">Delivery Notes</label>
                <textarea
                  value={deliveryNotes}
                  onChange={e => setDeliveryNotes(e.target.value)}
                  rows={2}
                  placeholder="Any observations about the delivery..."
                  className="w-full border border-neutral-200 rounded-lg px-3 py-2 text-sm"
                />
              </div>
            </>
          )}
        </div>

        <div className="p-4 border-t border-neutral-100 flex gap-3">
          <button
            onClick={onClose}
            disabled={recordPod.isPending || isMarkingDelivered}
            className="flex-1 py-2.5 border border-neutral-200 text-neutral-700 font-medium rounded-lg hover:bg-neutral-50 transition-colors text-sm"
          >
            Cancel
          </button>
          <button
            onClick={handleSubmitPod}
            disabled={recordPod.isPending || isMarkingDelivered || (!hasPod && !receiverName.trim())}
            className="flex-1 py-2.5 bg-amber-600 text-white font-medium rounded-lg hover:bg-amber-700 transition-colors text-sm disabled:opacity-50 flex items-center justify-center gap-2"
          >
            <PenTool className="h-4 w-4" />
            {recordPod.isPending || isMarkingDelivered
              ? 'Processing...'
              : hasPod
                ? 'Mark Delivered'
                : 'Submit POD & Mark Delivered'}
          </button>
        </div>
      </div>
    </div>
  )
}

// ── Main Page ───────────────────────────────────────────────────────────────
export default function DeliveryReceiptDetailPage(): React.ReactElement {
  const { ulid } = useParams<{ ulid: string }>();
  const navigate = useNavigate();
  const { hasPermission } = useAuthStore();
  const { data, isLoading, isError } = useDeliveryReceipt(ulid ?? '');
  const confirmMut = useConfirmDeliveryReceipt();
  const dispatchMut = useMarkDispatched();
  const deliverMut = useMarkDelivered();
  const prepareShipmentMut = usePrepareShipment();
  const [confirmOpen, setConfirmOpen] = useState(false);
  const [dispatchOpen, setDispatchOpen] = useState(false);
  const [podDeliverOpen, setPodDeliverOpen] = useState(false);
  const [shipmentOpen, setShipmentOpen] = useState(false);
  const canManage = hasPermission('delivery.manage');

  if (isLoading) return <SkeletonLoader rows={6} />;

  if (isError || !data?.data) {
    return (
      <div className="text-sm text-red-600 mt-4">
        Delivery receipt not found or you do not have access.{' '}
        <button onClick={() => navigate('/delivery/receipts')} className="underline text-neutral-600">
          Back to list
        </button>
      </div>
    );
  }

  const dr = data.data;
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  const drAny = dr as any;
  const hasShipment = (dr.shipments ?? []).length > 0;
  const hasPod = !!drAny.pod_receiver_name;

  const handleConfirm = async () => {
    try {
      await confirmMut.mutateAsync(dr.ulid);
      toast.success('Delivery receipt confirmed.');
      setConfirmOpen(false);
    } catch {
      // Global mutation onError handler shows toast
    }
  };

  const handlePrepareShipment = async (shipmentData: {
    vehicle_id?: number
    driver_name?: string
    carrier?: string
    tracking_number?: string
    estimated_arrival?: string
    notes?: string
  }) => {
    try {
      await prepareShipmentMut.mutateAsync({ ulid: dr.ulid, payload: shipmentData });
      toast.success('Shipment prepared successfully. Ready to dispatch.');
      setShipmentOpen(false);
    } catch {
      // Error toast is handled by the global mutation onError handler in main.tsx.
    }
  };

  const handleDispatch = async () => {
    try {
      await dispatchMut.mutateAsync(dr.ulid);
      toast.success('Delivery dispatched. Shipment is now in transit.');
      setDispatchOpen(false);
    } catch {
      // Global mutation onError handler shows toast
    }
  };

  const handleDeliver = async () => {
    try {
      await deliverMut.mutateAsync(dr.ulid);
      toast.success('Delivery marked as delivered.');
      setPodDeliverOpen(false);
    } catch {
      // Global mutation onError handler shows toast
    }
  };

  const handleExportPdf = async () => {
    try {
      await downloadFile(deliveryApiPaths.receiptPdf(dr.ulid), `DR-${dr.dr_reference}.pdf`, 'application/pdf');
      toast.success('PDF exported successfully.');
    } catch {
    }
  };

  const statusBadges = (
    <div className="flex items-center gap-2">
      <span className={`inline-flex items-center px-2.5 py-0.5 rounded text-xs font-medium ${DIRECTION_COLORS[dr.direction]}`}>
        {dr.direction}
      </span>
      <StatusBadge status={dr.status}>{dr.status?.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase())}</StatusBadge>
    </div>
  );

  const activeShipment = (dr.shipments ?? []).find(
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    (s: any) => s.status !== 'cancelled'
  );

  return (
    <div className="max-w-7xl mx-auto">
      <PageHeader
        backTo="/delivery/receipts"
        title={dr.dr_reference}
        subtitle={dr.receipt_date}
        icon={<Truck className="w-5 h-5" />}
        status={statusBadges}
        actions={
          <div className="flex items-center gap-2">
            {dr.status === 'draft' && canManage && (
              <button type="button" onClick={() => setConfirmOpen(true)} className="px-4 py-2 text-sm bg-neutral-900 text-white rounded hover:bg-neutral-800">
                Confirm Receipt
              </button>
            )}
            {dr.status === 'confirmed' && canManage && !hasShipment && (
              <button type="button" onClick={() => setShipmentOpen(true)} className="px-4 py-2 text-sm bg-blue-600 text-white rounded hover:bg-blue-700 flex items-center gap-1.5">
                <Package className="h-4 w-4" />
                Prepare Shipment
              </button>
            )}
            {dr.status === 'confirmed' && canManage && hasShipment && (
              <button type="button" onClick={() => setDispatchOpen(true)} className="px-4 py-2 text-sm bg-blue-600 text-white rounded hover:bg-blue-700 flex items-center gap-1.5">
                <Truck className="h-4 w-4" />
                Dispatch
              </button>
            )}
            {dr.status === 'dispatched' && (
              <button
                type="button"
                onClick={handleExportPdf}
                className="px-4 py-2 text-sm bg-white text-neutral-800 border border-neutral-300 rounded hover:bg-neutral-50 flex items-center gap-1.5"
              >
                <FileDown className="h-4 w-4" />
                Export PDF
              </button>
            )}
            {(dr.status === 'dispatched' || dr.status === 'in_transit' || dr.status === 'partially_delivered') && canManage && (
              <button
                type="button"
                onClick={() => setPodDeliverOpen(true)}
                className="px-4 py-2 text-sm rounded flex items-center gap-1.5 bg-green-600 text-white hover:bg-green-700"
              >
                <CheckCircle className="h-4 w-4" />
                Mark Delivered
              </button>
            )}
          </div>
        }
      />

      {/* Workflow Timeline */}
      <div className="bg-white border border-neutral-200 rounded p-4 mb-5">
        <StatusTimeline
          steps={getDeliveryReceiptSteps(dr)}
          currentStatus={dr.status}
          direction="horizontal"
          isRejected={isRejectedStatus(dr.status)}
        />
      </div>

      <div className="grid lg:grid-cols-12 gap-5">
        <div className="lg:col-span-8 space-y-5">
          {/* Shipment Details Card (if shipment exists) */}
          {activeShipment && (
            <Card className="border-blue-200 bg-blue-50/30">
              <CardHeader>
                <span className="flex items-center gap-2 text-blue-700">
                  <Truck className="h-4 w-4" />
                  Shipment Details
                </span>
              </CardHeader>
              <CardBody>
                <InfoList columns={2}>
                  <InfoRow label="Carrier" value={activeShipment.carrier ?? 'Company Fleet'} />
                  <InfoRow label="Tracking" value={activeShipment.tracking_number ?? '-'} />
                  <InfoRow label="Driver" value={drAny.driver_name ?? '-'} />
                  <InfoRow label="Vehicle" value={drAny.vehicle?.plate_number ?? drAny.vehicle?.name ?? '-'} />
                  <InfoRow label="Status" value={
                    <span className={`inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-medium ${
                      activeShipment.status === 'delivered' ? 'bg-green-100 text-green-700' :
                      activeShipment.status === 'in_transit' ? 'bg-blue-100 text-blue-700' :
                      'bg-neutral-100 text-neutral-600'
                    }`}>
                      {activeShipment.status === 'in_transit' && <Clock className="h-3 w-3" />}
                      {activeShipment.status === 'delivered' && <CheckCircle className="h-3 w-3" />}
                      {formatStatusLabel(activeShipment.status)}
                    </span>
                  } />
                  <InfoRow label="ETA" value={activeShipment.estimated_arrival ? new Date(activeShipment.estimated_arrival).toLocaleDateString('en-PH') : '-'} />
                  {activeShipment.shipped_at && (
                    <InfoRow label="Dispatched At" value={new Date(activeShipment.shipped_at).toLocaleString('en-PH')} />
                  )}
                  {activeShipment.actual_arrival && (
                    <InfoRow label="Delivered At" value={new Date(activeShipment.actual_arrival).toLocaleDateString('en-PH')} />
                  )}
                </InfoList>
              </CardBody>
            </Card>
          )}

          {/* POD Summary (when delivered) */}
          {dr.status === 'delivered' && hasPod && (
            <PodSummaryCard podData={drAny} />
          )}

          {/* Line Items */}
          <Card>
            <CardHeader>Line Items</CardHeader>
            <CardBody className="p-0">
              <table className="w-full text-sm">
                <thead className="bg-neutral-50 text-xs text-neutral-500 uppercase tracking-wide">
                  <tr>
                    <th className="px-4 py-3 text-left">Item</th>
                    <th className="px-4 py-3 text-right">Qty Expected</th>
                    <th className="px-4 py-3 text-right">Qty Received</th>
                    <th className="px-4 py-3 text-left">UoM</th>
                    <th className="px-4 py-3 text-left">Lot / Batch</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-neutral-100">
                  {(dr.items ?? []).length === 0 ? (
                    <tr>
                      <td colSpan={5} className="px-4 py-8 text-center text-neutral-400">No line items.</td>
                    </tr>
                  ) : (
                    // eslint-disable-next-line @typescript-eslint/no-explicit-any
                    (dr.items ?? []).map((item: any) => (
                      <tr key={item.id} className="hover:bg-neutral-50">
                        <td className="px-4 py-3 text-neutral-900">{item.item_name ?? `Item #${item.item_master_id}`}</td>
                        <td className="px-4 py-3 text-right text-neutral-700">{item.quantity_expected}</td>
                        <td className="px-4 py-3 text-right font-medium text-neutral-900">{item.quantity_received}</td>
                        <td className="px-4 py-3 text-neutral-500">{item.unit_of_measure ?? '-'}</td>
                        <td className="px-4 py-3 text-neutral-500 font-mono text-xs">{item.lot_batch_number ?? '-'}</td>
                      </tr>
                    ))
                  )}
                </tbody>
              </table>
            </CardBody>
          </Card>
        </div>

        <div className="lg:col-span-4 space-y-4">
          {/* Compact Receipt Details */}
          <Card>
            <CardHeader>Receipt Details</CardHeader>
            <CardBody>
              <InfoList columns={1}>
                <InfoRow label="Vendor" value={dr.vendor?.name ?? '-'} />
                <InfoRow label="Customer" value={dr.customer?.name ?? '-'} />
                <InfoRow label="Received By" value={dr.received_by?.name ?? '-'} />
                <InfoRow label="Receipt Date" value={dr.receipt_date ?? '-'} />
                <InfoRow label="Remarks" value={dr.remarks ?? '-'} />
              </InfoList>
            </CardBody>
          </Card>

          {/* Compact Document Chain */}
          <Card>
            <CardHeader>Document Chain</CardHeader>
            <CardBody className="text-sm">
              <ChainRecordTimeline documentType="delivery_receipt" documentId={dr.id} />
            </CardBody>
          </Card>
        </div>
      </div>

      {/* Modals */}
      <ConfirmDialog
        open={confirmOpen}
        onClose={() => setConfirmOpen(false)}
        onConfirm={handleConfirm}
        title="Confirm delivery receipt?"
        description="This will mark the delivery receipt as confirmed and process inventory movements. This action cannot be undone."
        confirmLabel="Confirm Receipt"
        variant="warning"
        loading={confirmMut.isPending}
      />

      <PrepareShipmentModal
        open={shipmentOpen}
        onClose={() => setShipmentOpen(false)}
        onSubmit={handlePrepareShipment}
        isLoading={prepareShipmentMut.isPending}
        defaultEstimatedDelivery={drAny.delivery_schedule?.target_delivery_date || drAny.receipt_date || ''}
      />

      <ConfirmDialog
        open={dispatchOpen}
        onClose={() => setDispatchOpen(false)}
        onConfirm={handleDispatch}
        title="Dispatch goods?"
        description={`Dispatch this delivery with ${activeShipment?.carrier ?? 'Company Fleet'}${activeShipment?.tracking_number ? ` (Tracking: ${activeShipment.tracking_number})` : ''}? The shipment will be marked as in transit and the customer will be notified.`}
        confirmLabel="Dispatch"
        variant="warning"
        loading={dispatchMut.isPending}
      />

      <PodDeliverModal
        open={podDeliverOpen}
        onClose={() => setPodDeliverOpen(false)}
        drUlid={dr.ulid}
        drStatus={dr.status}
        hasPod={hasPod}
        podData={drAny}
        onMarkDelivered={handleDeliver}
        isMarkingDelivered={deliverMut.isPending}
      />
    </div>
  );
}
