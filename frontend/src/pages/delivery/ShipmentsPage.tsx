import { useState } from 'react';
import { Package, AlertTriangle, Plus, ChevronDown, ChevronUp } from 'lucide-react';
import { useShipments, useCreateShipment, useUpdateShipmentStatus, useDeliveryReceipts } from '@/hooks/useDelivery';
import { useAuthStore } from '@/stores/authStore';
import { PageHeader } from '@/components/ui/PageHeader';
import { toast } from 'sonner';
import { firstErrorMessage } from '@/lib/errorHandler';
import ConfirmDialog from '@/components/ui/ConfirmDialog';
import { ExportButton } from '@/components/ui/ExportButton';
import type { ShipmentStatus } from '@/types/delivery';

const STATUS_COLORS: Record<ShipmentStatus, string> = {
  pending:    'bg-neutral-100 text-neutral-600',
  in_transit: 'bg-neutral-100 text-neutral-700',
  delivered:  'bg-neutral-200 text-neutral-800',
  returned:   'bg-neutral-100 text-neutral-400',
};

const STATUS_LABELS: Record<ShipmentStatus, string> = {
  pending:    'Pending',
  in_transit: 'In Transit',
  delivered:  'Delivered',
  returned:   'Returned',
};

const INPUT = 'w-full border border-neutral-300 rounded px-3 py-2 text-sm focus:ring-1 focus:ring-neutral-400 bg-white';

const NEXT_STATUS: Partial<Record<ShipmentStatus, ShipmentStatus>> = {
  pending: 'in_transit',
  in_transit: 'delivered',
};

const NEXT_STATUS_LABEL: Partial<Record<ShipmentStatus, string>> = {
  pending: 'Mark In Transit',
  in_transit: 'Mark Delivered',
};

export default function ShipmentsPage() {
  const [status, setStatus] = useState('');
  const [showCreate, setShowCreate] = useState(false);
  const [expandedUlid, setExpandedUlid] = useState<string | null>(null);
  const [actualArrival, setActualArrival] = useState('');
  const [deliveryReceiptId, setDeliveryReceiptId] = useState<number | null>(null);
  const [createForm, setCreateForm] = useState({
    carrier: '',
    tracking_number: '',
    shipped_at: '',
    estimated_arrival: '',
    notes: '',
  });
  const [confirmStatusUpdate, setConfirmStatusUpdate] = useState<{
    open: boolean;
    ulid: string | null;
    nextStatus: ShipmentStatus | null;
  }>({ open: false, ulid: null, nextStatus: null });

  const params: Record<string, string> = {};
  if (status) params.status = status;

  const { data, isLoading, isError } = useShipments(Object.keys(params).length ? params : undefined);
  const { data: drData } = useDeliveryReceipts({ status: 'confirmed', per_page: '100' });
  const confirmedDrs = drData?.data ?? [];
  const createMut = useCreateShipment();
  const updateStatusMut = useUpdateShipmentStatus();
  const canManage = useAuthStore(s => s.hasPermission('delivery.manage'));

  // Validation for create form
  const createFormErrors = () => {
    const errors: string[] = [];
    if (!deliveryReceiptId) errors.push('Delivery receipt is required');
    if (!createForm.shipped_at) errors.push('Shipped date is required');
    if (!createForm.carrier.trim()) errors.push('Carrier is required');
    return errors;
  };

  const handleCreate = async (e: React.FormEvent) => {
    e.preventDefault();
    const errors = createFormErrors();
    if (errors.length > 0) {
      errors.forEach(err => toast.error(err));
      return;
    }
    try {
      await createMut.mutateAsync({
        delivery_receipt_id: deliveryReceiptId ?? undefined,
        carrier: createForm.carrier || undefined,
        tracking_number: createForm.tracking_number || undefined,
        shipped_at: createForm.shipped_at || undefined,
        estimated_arrival: createForm.estimated_arrival || undefined,
        notes: createForm.notes || undefined,
      });
      toast.success('Shipment created.');
      setCreateForm({ carrier: '', tracking_number: '', shipped_at: '', estimated_arrival: '', notes: '' });
      setDeliveryReceiptId(null);
      setShowCreate(false);
    } catch (err) {
      toast.error(firstErrorMessage(err));
    }
  };

  const initiateStatusUpdate = (ulid: string, nextStatus: ShipmentStatus) => {
    if (nextStatus === 'delivered' && !actualArrival) {
      toast.error('Please enter the actual arrival date');
      return;
    }
    setConfirmStatusUpdate({ open: true, ulid, nextStatus });
  };

  const handleStatusUpdate = async () => {
    if (!confirmStatusUpdate.ulid || !confirmStatusUpdate.nextStatus) return;
    try {
      await updateStatusMut.mutateAsync({
        ulid: confirmStatusUpdate.ulid,
        payload: {
          status: confirmStatusUpdate.nextStatus,
          ...(confirmStatusUpdate.nextStatus === 'delivered' && actualArrival ? { actual_arrival: actualArrival } : {}),
        },
      });
      toast.success('Status updated.');
      setExpandedUlid(null);
      setActualArrival('');
      setConfirmStatusUpdate({ open: false, ulid: null, nextStatus: null });
    } catch (err) {
      toast.error(firstErrorMessage(err));
    }
  };

  return (
    <div className="space-y-4">
      <PageHeader
        title="Shipments"
        actions={
          <ExportButton
            data={data?.data ?? []}
            columns={[
              { key: 'tracking_number', label: 'Tracking #' },
              { key: 'delivery_receipt.dr_reference', label: 'DR Reference' },
              { key: 'status', label: 'Status' },
              { key: 'carrier', label: 'Carrier' },
              { key: 'shipped_date', label: 'Shipped Date' },
              { key: 'delivered_date', label: 'Delivered Date' },
            ]}
            filename="shipments"
          />
        }
      />
      <div className="flex items-center justify-between">
        <div />
        {canManage && (
          <button
            type="button"
            onClick={() => setShowCreate(s => !s)}
            className="inline-flex items-center gap-1.5 rounded bg-neutral-900 px-3 py-2 text-sm font-medium text-white hover:bg-neutral-800"
          >
            <Plus size={16} /> New Shipment
          </button>
        )}
      </div>

      {showCreate && (
        <form onSubmit={handleCreate} className="bg-white border border-neutral-200 rounded p-5 space-y-4">
          <div>
            <label className="block text-sm font-medium text-neutral-700 mb-1">Delivery Receipt *</label>
            <select
              className={INPUT}
              value={deliveryReceiptId ?? ''}
              onChange={e => setDeliveryReceiptId(e.target.value ? Number(e.target.value) : null)}
              required
            >
              <option value="">— Select Receipt —</option>
              {confirmedDrs.map(dr => (
                <option key={dr.id} value={dr.id}>
                  {dr.dr_reference} — {dr.customer?.name ?? dr.vendor?.name ?? dr.direction}
                </option>
              ))}
            </select>
          </div>
          <div className="grid grid-cols-2 gap-4">
            <div>
              <label className="block text-sm font-medium text-neutral-700 mb-1">Carrier *</label>
              <input type="text" className={INPUT} value={createForm.carrier} onChange={e => setCreateForm(s => ({ ...s, carrier: e.target.value }))} placeholder="e.g. LBC Express" required />
            </div>
            <div>
              <label className="block text-sm font-medium text-neutral-700 mb-1">Tracking No.</label>
              <input type="text" className={INPUT} value={createForm.tracking_number} onChange={e => setCreateForm(s => ({ ...s, tracking_number: e.target.value }))} />
            </div>
          </div>
          <div className="grid grid-cols-2 gap-4">
            <div>
              <label className="block text-sm font-medium text-neutral-700 mb-1">Shipped Date *</label>
              <input type="date" className={INPUT} value={createForm.shipped_at} onChange={e => setCreateForm(s => ({ ...s, shipped_at: e.target.value }))} required />
            </div>
            <div>
              <label className="block text-sm font-medium text-neutral-700 mb-1">Estimated Arrival</label>
              <input type="date" className={INPUT} value={createForm.estimated_arrival} onChange={e => setCreateForm(s => ({ ...s, estimated_arrival: e.target.value }))} />
            </div>
          </div>
          <div>
            <label className="block text-sm font-medium text-neutral-700 mb-1">Notes</label>
            <textarea className={INPUT} rows={2} value={createForm.notes} onChange={e => setCreateForm(s => ({ ...s, notes: e.target.value }))} />
          </div>
          <div className="flex justify-end gap-3 pt-1">
            <button type="button" onClick={() => setShowCreate(false)} className="px-4 py-2 text-sm border border-neutral-300 rounded hover:bg-neutral-50">Cancel</button>
            <button type="submit" disabled={createMut.isPending} className="px-4 py-2 text-sm bg-neutral-900 text-white rounded hover:bg-neutral-800 disabled:opacity-50 disabled:cursor-not-allowed">
              {createMut.isPending ? 'Creating…' : 'Create Shipment'}
            </button>
          </div>
        </form>
      )}

      <div className="flex gap-2">
        <select
          value={status}
          onChange={(e) => setStatus(e.target.value)}
          className="text-sm border border-neutral-300 rounded px-3 py-2 focus:ring-1 focus:ring-neutral-400 focus:outline-none bg-white"
        >
          <option value="">All Statuses</option>
          <option value="pending">Pending</option>
          <option value="in_transit">In Transit</option>
          <option value="delivered">Delivered</option>
          <option value="returned">Returned</option>
        </select>
      </div>

      {isLoading && (
        <div className="bg-white border border-neutral-200 rounded p-8 text-center text-neutral-400 text-sm">
          Loading shipments…
        </div>
      )}

      {isError && (
        <div className="flex items-center gap-2 text-red-600 text-sm">
          <AlertTriangle className="w-4 h-4" /> Failed to load shipments.
        </div>
      )}

      {!isLoading && !isError && (
        <div className="overflow-hidden rounded border border-neutral-200 bg-white">
          <table className="w-full text-sm">
            <thead className="bg-neutral-50 border-b border-neutral-200">
              <tr>
                {['Reference', 'Carrier', 'Tracking #', 'Shipped', 'Est. Arrival', 'Actual Arrival', 'Status', ''].map((h) => (
                  <th key={h} className="px-4 py-3 text-left text-xs font-medium text-neutral-600">
                    {h}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody className="divide-y divide-neutral-100">
              {(data?.data ?? []).length === 0 ? (
                <tr>
                  <td colSpan={8} className="px-4 py-10 text-center text-neutral-400">
                    <Package size={32} className="mx-auto mb-2 opacity-30" />
                    No shipments found.
                  </td>
                </tr>
              ) : (
                (data?.data ?? []).map((shipment) => (
                  <>
                    <tr key={shipment.ulid} className="even:bg-neutral-100 hover:bg-neutral-50">
                      <td className="px-4 py-3 font-mono text-xs font-medium text-neutral-900">
                        {shipment.shipment_reference}
                      </td>
                      <td className="px-4 py-3 text-neutral-700">
                        {shipment.carrier ?? <span className="text-neutral-400">—</span>}
                      </td>
                      <td className="px-4 py-3 font-mono text-xs text-neutral-600">
                        {shipment.tracking_number ?? <span className="text-neutral-400">—</span>}
                      </td>
                      <td className="px-4 py-3 text-neutral-600 text-xs">
                        {shipment.shipped_at ? shipment.shipped_at.slice(0, 10) : <span className="text-neutral-400">—</span>}
                      </td>
                      <td className="px-4 py-3 text-neutral-600 text-xs">
                        {shipment.estimated_arrival ? shipment.estimated_arrival.slice(0, 10) : <span className="text-neutral-400">—</span>}
                      </td>
                      <td className="px-4 py-3 text-neutral-600 text-xs">
                        {shipment.actual_arrival
                          ? <span className="text-neutral-900 font-medium">{shipment.actual_arrival.slice(0, 10)}</span>
                          : <span className="text-neutral-400">—</span>}
                      </td>
                      <td className="px-4 py-3">
                        <span className={`inline-flex px-2 py-0.5 rounded text-xs font-medium ${STATUS_COLORS[shipment.status]}`}>
                          {STATUS_LABELS[shipment.status]}
                        </span>
                      </td>
                      <td className="px-4 py-3">
                        {NEXT_STATUS[shipment.status] && canManage && (
                          <button
                            type="button"
                            onClick={() => {
                              if (expandedUlid === shipment.ulid) {
                                setExpandedUlid(null);
                              } else {
                                setExpandedUlid(shipment.ulid);
                                setActualArrival('');
                              }
                            }}
                            className="flex items-center gap-1 text-xs text-neutral-600 border border-neutral-300 rounded px-2 py-1 hover:bg-neutral-50"
                          >
                            {NEXT_STATUS_LABEL[shipment.status]}
                            {expandedUlid === shipment.ulid ? <ChevronUp className="w-3 h-3" /> : <ChevronDown className="w-3 h-3" />}
                          </button>
                        )}
                      </td>
                    </tr>
                    {expandedUlid === shipment.ulid && NEXT_STATUS[shipment.status] && (
                      <tr key={`${shipment.ulid}-expand`}>
                        <td colSpan={8} className="px-4 py-3 bg-neutral-50 border-t border-neutral-200">
                          <div className="flex items-end gap-4">
                            {NEXT_STATUS[shipment.status] === 'delivered' && (
                              <div>
                                <label className="block text-xs font-medium text-neutral-600 mb-1">Actual Arrival Date *</label>
                                <input
                                  type="date"
                                  className="border border-neutral-300 rounded px-3 py-1.5 text-sm focus:ring-1 focus:ring-neutral-400 bg-white"
                                  value={actualArrival}
                                  onChange={e => setActualArrival(e.target.value)}
                                  required
                                />
                              </div>
                            )}
                            <button
                              type="button"
                              disabled={updateStatusMut.isPending}
                              onClick={() => initiateStatusUpdate(shipment.ulid, NEXT_STATUS[shipment.status]!)}
                              className="px-3 py-1.5 text-sm bg-neutral-900 text-white rounded hover:bg-neutral-800 disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                              {updateStatusMut.isPending ? 'Updating…' : `Confirm: ${NEXT_STATUS_LABEL[shipment.status]}`}
                            </button>
                            <button
                              type="button"
                              onClick={() => setExpandedUlid(null)}
                              className="px-3 py-1.5 text-sm border border-neutral-300 rounded hover:bg-neutral-50"
                            >
                              Cancel
                            </button>
                          </div>
                        </td>
                      </tr>
                    )}
                  </>
                ))
              )}
            </tbody>
          </table>
        </div>
      )}

      <ConfirmDialog
        title={`${confirmStatusUpdate.nextStatus === 'delivered' ? 'Mark as delivered?' : 'Mark as in transit?'}`}
        description={
          confirmStatusUpdate.nextStatus === 'delivered'
            ? 'This will mark the shipment as delivered and record the actual arrival date. This action cannot be undone.'
            : 'This will mark the shipment as in transit. Continue?'
        }
        confirmLabel={confirmStatusUpdate.nextStatus === 'delivered' ? 'Mark Delivered' : 'Mark In Transit'}
        onConfirm={handleStatusUpdate}
      >
        <span />
      </ConfirmDialog>
    </div>
  );
}
