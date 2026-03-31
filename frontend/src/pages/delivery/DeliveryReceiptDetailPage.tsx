import { useState, useRef } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { Truck, MapPin, Camera, PenTool, User, Package, Clock, CheckCircle } from 'lucide-react';
import { toast } from 'sonner';
import { firstErrorMessage } from '@/lib/errorHandler'
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
  const [dispatchPhoto, setDispatchPhoto] = useState<string | null>(null)
  const [showPhotoPreview, setShowPhotoPreview] = useState(false)
  const photoInputRef = useRef<HTMLInputElement>(null)

  const { data: vehiclesData } = useVehicles()
  const vehicles = vehiclesData?.data ?? []

  const handlePhotoUpload = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0]
    if (!file) return
    const reader = new FileReader()
    reader.onload = () => setDispatchPhoto(reader.result as string)
    reader.readAsDataURL(file)
  }

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
                  return (
                    <option key={v.id} value={v.id} disabled={inDelivery}>
                      {v.name} ({v.plate_number}) -- {v.type}{inDelivery ? ' [IN DELIVERY]' : ''}
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
            {vehicles.length > 0 && vehicles.filter((v: any) => v.status === 'active' && v.availability !== 'in_delivery').length === 0 && (
              <p className="text-xs text-amber-600 mt-1">
                All active vehicles are currently in delivery.
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

          {/* Dispatch Photo */}
          <div>
            <label className="block text-sm font-medium text-neutral-700 mb-1">
              <Camera className="h-3.5 w-3.5 inline mr-1" />
              Dispatch Photo
            </label>
            <input
              ref={photoInputRef}
              type="file"
              accept="image/*"
              capture="environment"
              onChange={handlePhotoUpload}
              className="hidden"
            />
            <button
              type="button"
              onClick={() => photoInputRef.current?.click()}
              className="w-full border border-dashed border-neutral-300 rounded-lg px-3 py-3 text-sm text-neutral-600 hover:border-blue-400 hover:text-blue-600 transition-colors flex items-center justify-center gap-2"
            >
              <Camera className="h-4 w-4" />
              {dispatchPhoto ? 'Photo captured -- click to replace' : 'Take photo of loaded goods (optional)'}
            </button>
            {dispatchPhoto && (
              <div className="mt-2 flex items-center gap-2">
                <button
                  type="button"
                  onClick={() => setShowPhotoPreview(true)}
                  className="text-xs text-blue-600 hover:text-blue-700 underline"
                >View Photo</button>
                <span className="text-xs text-green-600">Photo attached</span>
                <button
                  type="button"
                  onClick={() => setDispatchPhoto(null)}
                  className="text-xs text-red-500 hover:text-red-600"
                >Remove</button>
              </div>
            )}

            {/* Photo lightbox */}
            {showPhotoPreview && dispatchPhoto && (
              <div className="fixed inset-0 bg-black/80 flex items-center justify-center z-[60] p-4" onClick={() => setShowPhotoPreview(false)}>
                <div className="relative max-w-2xl max-h-[80vh]">
                  <img src={dispatchPhoto} alt="Dispatch photo" className="max-w-full max-h-[80vh] object-contain rounded-lg" />
                  <button
                    type="button"
                    onClick={() => setShowPhotoPreview(false)}
                    className="absolute top-2 right-2 bg-white/90 text-neutral-800 rounded-full w-8 h-8 flex items-center justify-center text-lg font-bold hover:bg-white"
                  >x</button>
                </div>
              </div>
            )}
          </div>

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

// ── Proof of Delivery Form ──────────────────────────────────────────────────
function PodCaptureSection({
  drUlid,
  hasPod,
  podData,
}: {
  drUlid: string
  hasPod: boolean
  podData?: {
    pod_receiver_name?: string
    pod_receiver_designation?: string
    pod_recorded_at?: string
    pod_notes?: string
    pod_latitude?: number
    pod_longitude?: number
  }
}) {
  const recordPod = useRecordPod()
  const [receiverName, setReceiverName] = useState('')
  const [receiverDesignation, setReceiverDesignation] = useState('')
  const [deliveryNotes, setDeliveryNotes] = useState('')
  const [gpsCoords, setGpsCoords] = useState<{ lat: number; lng: number } | null>(null)
  const [capturingGps, setCapturingGps] = useState(false)
  const [photoBase64, setPhotoBase64] = useState<string | null>(null)
  const fileInputRef = useRef<HTMLInputElement>(null)

  const captureGps = () => {
    if (!navigator.geolocation) {
      toast.error('Geolocation is not supported by your browser')
      return
    }
    setCapturingGps(true)
    navigator.geolocation.getCurrentPosition(
      pos => {
        setGpsCoords({ lat: pos.coords.latitude, lng: pos.coords.longitude })
        setCapturingGps(false)
        toast.success('Location captured')
      },
      () => {
        setCapturingGps(false)
        toast.error('Could not capture location')
      },
      { enableHighAccuracy: true }
    )
  }

  const handlePhotoUpload = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0]
    if (!file) return
    const reader = new FileReader()
    reader.onload = () => setPhotoBase64(reader.result as string)
    reader.readAsDataURL(file)
  }

  const handleSubmitPod = async () => {
    if (!receiverName.trim()) {
      toast.error('Receiver name is required')
      return
    }

    try {
      await recordPod.mutateAsync({
        ulid: drUlid,
        payload: {
          receiver_name: receiverName,
          receiver_designation: receiverDesignation || undefined,
          photo_base64: photoBase64 || undefined,
          latitude: gpsCoords?.lat,
          longitude: gpsCoords?.lng,
          delivery_notes: deliveryNotes || undefined,
        },
      })
      toast.success('Proof of Delivery recorded successfully')
    } catch (err) {
      toast.error(firstErrorMessage(err, 'Failed to record POD'))
    }
  }

  if (hasPod) {
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
            <InfoRow label="Designation" value={podData?.pod_receiver_designation ?? '-'} />
            <InfoRow label="Recorded At" value={podData?.pod_recorded_at ? new Date(podData.pod_recorded_at).toLocaleString('en-PH') : '-'} />
            <InfoRow label="GPS" value={podData?.pod_latitude && podData?.pod_longitude ? `${podData.pod_latitude.toFixed(6)}, ${podData.pod_longitude.toFixed(6)}` : 'Not captured'} />
            {podData?.pod_notes && <InfoRow label="Notes" value={podData.pod_notes} />}
          </InfoList>
        </CardBody>
      </Card>
    )
  }

  return (
    <Card className="mb-5 border-amber-200">
      <CardHeader>
        <span className="flex items-center gap-2 text-amber-700">
          <PenTool className="h-4 w-4" />
          Record Proof of Delivery
        </span>
      </CardHeader>
      <CardBody>
        <p className="text-sm text-neutral-500 mb-4">
          Capture delivery confirmation before marking as delivered. The receiver must acknowledge receipt of goods.
        </p>

        <div className="space-y-4">
          <div className="grid sm:grid-cols-2 gap-4">
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
            <div>
              <label className="block text-sm font-medium text-neutral-700 mb-1">Designation</label>
              <input
                type="text"
                value={receiverDesignation}
                onChange={e => setReceiverDesignation(e.target.value)}
                placeholder="e.g. Warehouse Manager"
                className="w-full border border-neutral-200 rounded-lg px-3 py-2 text-sm"
              />
            </div>
          </div>

          <div className="grid sm:grid-cols-2 gap-4">
            <div>
              <label className="block text-sm font-medium text-neutral-700 mb-1">
                <Camera className="h-3.5 w-3.5 inline mr-1" />
                Photo of Delivered Goods
              </label>
              <input
                ref={fileInputRef}
                type="file"
                accept="image/*"
                capture="environment"
                onChange={handlePhotoUpload}
                className="hidden"
              />
              <button
                onClick={() => fileInputRef.current?.click()}
                className="w-full border border-dashed border-neutral-300 rounded-lg px-3 py-3 text-sm text-neutral-600 hover:border-blue-400 hover:text-blue-600 transition-colors flex items-center justify-center gap-2"
              >
                <Camera className="h-4 w-4" />
                {photoBase64 ? 'Photo captured' : 'Take Photo / Upload'}
              </button>
              {photoBase64 && (
                <div className="mt-2 relative">
                  <img src={photoBase64} alt="Delivery" className="w-full h-24 object-cover rounded-lg" />
                  <button onClick={() => setPhotoBase64(null)} className="absolute top-1 right-1 bg-red-500 text-white rounded-full w-5 h-5 text-xs flex items-center justify-center">x</button>
                </div>
              )}
            </div>
            <div>
              <label className="block text-sm font-medium text-neutral-700 mb-1">
                <MapPin className="h-3.5 w-3.5 inline mr-1" />
                GPS Location
              </label>
              <button
                onClick={captureGps}
                disabled={capturingGps}
                className="w-full border border-dashed border-neutral-300 rounded-lg px-3 py-3 text-sm text-neutral-600 hover:border-blue-400 hover:text-blue-600 transition-colors flex items-center justify-center gap-2"
              >
                <MapPin className="h-4 w-4" />
                {capturingGps ? 'Capturing...' : gpsCoords ? `${gpsCoords.lat.toFixed(4)}, ${gpsCoords.lng.toFixed(4)}` : 'Capture Location'}
              </button>
            </div>
          </div>

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

          <button
            onClick={handleSubmitPod}
            disabled={recordPod.isPending || !receiverName.trim()}
            className="w-full py-2.5 bg-amber-600 text-white font-medium rounded-lg hover:bg-amber-700 transition-colors text-sm disabled:opacity-50 flex items-center justify-center gap-2"
          >
            <PenTool className="h-4 w-4" />
            {recordPod.isPending ? 'Recording...' : 'Submit Proof of Delivery'}
          </button>
        </div>
      </CardBody>
    </Card>
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
  const [deliverOpen, setDeliverOpen] = useState(false);
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
    } catch (err) {
      toast.error(firstErrorMessage(err, 'Failed to confirm receipt.'));
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
    } catch (err) {
      toast.error(firstErrorMessage(err, 'Failed to prepare shipment.'));
    }
  };

  const handleDispatch = async () => {
    try {
      await dispatchMut.mutateAsync(dr.ulid);
      toast.success('Delivery dispatched. Shipment is now in transit.');
      setDispatchOpen(false);
    } catch (err) {
      toast.error(firstErrorMessage(err, 'Failed to dispatch.'));
    }
  };

  const handleDeliver = async () => {
    try {
      await deliverMut.mutateAsync(dr.ulid);
      toast.success('Delivery marked as delivered.');
      setDeliverOpen(false);
    } catch (err) {
      toast.error(firstErrorMessage(err, 'Failed to mark as delivered.'));
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
            {(dr.status === 'dispatched' || dr.status === 'in_transit' || dr.status === 'partially_delivered') && canManage && (
              <button
                type="button"
                onClick={() => setDeliverOpen(true)}
                disabled={!hasPod}
                title={!hasPod ? 'Record Proof of Delivery first' : 'Mark as delivered'}
                className={`px-4 py-2 text-sm rounded flex items-center gap-1.5 ${hasPod ? 'bg-green-600 text-white hover:bg-green-700' : 'bg-neutral-200 text-neutral-400 cursor-not-allowed'}`}
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

      {/* Shipment Details Card (if shipment exists) */}
      {activeShipment && (
        <Card className="mb-5 border-blue-200 bg-blue-50/30">
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
                  {activeShipment.status?.replace(/_/g, ' ').replace(/\b\w/g, (c: string) => c.toUpperCase())}
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

      {/* POD Section (when dispatched/in_transit) */}
      {(dr.status === 'dispatched' || dr.status === 'in_transit' || dr.status === 'partially_delivered') && canManage && (
        <PodCaptureSection
          drUlid={dr.ulid}
          hasPod={hasPod}
          podData={drAny}
        />
      )}

      {/* POD Summary (when delivered) */}
      {dr.status === 'delivered' && hasPod && (
        <PodCaptureSection
          drUlid={dr.ulid}
          hasPod={true}
          podData={drAny}
        />
      )}

      {/* Receipt Details */}
      <Card className="mb-5">
        <CardHeader>Receipt Details</CardHeader>
        <CardBody>
          <InfoList columns={2}>
            <InfoRow label="Vendor" value={dr.vendor?.name ?? '-'} />
            <InfoRow label="Customer" value={dr.customer?.name ?? '-'} />
            <InfoRow label="Received By" value={dr.received_by?.name ?? '-'} />
            <InfoRow label="Receipt Date" value={dr.receipt_date ?? '-'} />
            <InfoRow label="Remarks" value={dr.remarks ?? '-'} />
          </InfoList>
        </CardBody>
      </Card>

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

      {/* Document Chain */}
      <Card className="mt-5">
        <CardHeader>Document Chain</CardHeader>
        <CardBody>
          <ChainRecordTimeline documentType="delivery_receipt" documentId={dr.id} />
        </CardBody>
      </Card>

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

      <ConfirmDialog
        open={deliverOpen}
        onClose={() => setDeliverOpen(false)}
        onConfirm={handleDeliver}
        title="Mark as Delivered?"
        description="Confirm that the goods have been received by the customer. The linked client order will be updated and an invoice may be auto-generated."
        confirmLabel="Mark Delivered"
        variant="warning"
        loading={deliverMut.isPending}
      />
    </div>
  );
}
