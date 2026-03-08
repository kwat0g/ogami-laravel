import { useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { ArrowLeft, Truck } from 'lucide-react';
import { toast } from 'sonner';
import { useDeliveryReceipt, useConfirmDeliveryReceipt } from '@/hooks/useDelivery';
import SkeletonLoader from '@/components/ui/SkeletonLoader';
import ConfirmDialog from '@/components/ui/ConfirmDialog';
import type { DrDirection, DrStatus } from '@/types/delivery';

const STATUS_COLORS: Record<DrStatus, string> = {
  draft: 'bg-neutral-100 text-neutral-600',
  confirmed: 'bg-neutral-200 text-neutral-800',
  cancelled: 'bg-neutral-100 text-neutral-400',
};

const DIRECTION_COLORS: Record<DrDirection, string> = {
  inbound: 'bg-neutral-100 text-neutral-700',
  outbound: 'bg-neutral-100 text-neutral-700',
};

export default function DeliveryReceiptDetailPage(): React.ReactElement {
  const { ulid } = useParams<{ ulid: string }>();
  const navigate = useNavigate();
  const { data, isLoading, isError } = useDeliveryReceipt(ulid ?? '');
  const confirmMut = useConfirmDeliveryReceipt();
  const [confirmOpen, setConfirmOpen] = useState(false);

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

  const handleConfirm = async () => {
    try {
      await confirmMut.mutateAsync(dr.ulid);
      toast.success('Delivery receipt confirmed.');
      setConfirmOpen(false);
    } catch {
      toast.error('Failed to confirm receipt.');
    }
  };

  return (
    <div className="max-w-3xl">
      {/* Header */}
      <div className="flex items-center justify-between gap-4 mb-6">
        <div className="flex items-center gap-3">
          <button
            type="button"
            onClick={() => navigate('/delivery/receipts')}
            className="p-2 rounded-lg border border-neutral-200 bg-white hover:bg-neutral-50 hover:border-neutral-300 text-neutral-500"
            aria-label="Back to Delivery Receipts"
          >
            <ArrowLeft className="w-4 h-4" />
          </button>
          <div className="w-10 h-10 bg-neutral-100 rounded-lg flex items-center justify-center shrink-0">
            <Truck className="w-5 h-5 text-neutral-600" />
          </div>
          <div>
            <div className="flex items-center gap-3">
              <h1 className="text-lg font-semibold text-neutral-900 font-mono">{dr.dr_reference}</h1>
              <span className={`inline-flex items-center px-2.5 py-0.5 rounded text-xs font-medium ${DIRECTION_COLORS[dr.direction]}`}>
                {dr.direction}
              </span>
              <span className={`inline-flex items-center px-2.5 py-0.5 rounded text-xs font-medium ${STATUS_COLORS[dr.status]}`}>
                {dr.status}
              </span>
            </div>
            <p className="text-sm text-neutral-500 mt-0.5">{dr.receipt_date}</p>
          </div>
        </div>

        {dr.status === 'draft' && (
          <button
            type="button"
            onClick={() => setConfirmOpen(true)}
            className="px-4 py-2 text-sm bg-neutral-900 text-white rounded hover:bg-neutral-800"
          >
            Confirm Receipt
          </button>
        )}
      </div>

      {/* Details */}
      <div className="bg-white border border-neutral-200 rounded p-6 mb-5">
        <dl className="grid grid-cols-2 gap-x-8 gap-y-4 text-sm">
          <div>
            <dt className="text-xs font-semibold text-neutral-500 uppercase tracking-wide">Vendor</dt>
            <dd className="mt-1 text-neutral-900">{dr.vendor?.name ?? '—'}</dd>
          </div>
          <div>
            <dt className="text-xs font-semibold text-neutral-500 uppercase tracking-wide">Customer</dt>
            <dd className="mt-1 text-neutral-900">{dr.customer?.name ?? '—'}</dd>
          </div>
          <div>
            <dt className="text-xs font-semibold text-neutral-500 uppercase tracking-wide">Received By</dt>
            <dd className="mt-1 text-neutral-900">{dr.received_by?.name ?? '—'}</dd>
          </div>
          <div>
            <dt className="text-xs font-semibold text-neutral-500 uppercase tracking-wide">Remarks</dt>
            <dd className="mt-1 text-neutral-900">{dr.remarks ?? '—'}</dd>
          </div>
        </dl>
      </div>

      {/* Line Items */}
      <h2 className="text-sm font-semibold text-neutral-700 mb-2">Line Items</h2>
      <div className="overflow-hidden rounded border border-neutral-200 bg-white">
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
              (dr.items ?? []).map(item => (
                <tr key={item.id} className="hover:bg-neutral-50">
                  <td className="px-4 py-3 text-neutral-900">{item.item_name ?? `Item #${item.item_master_id}`}</td>
                  <td className="px-4 py-3 text-right text-neutral-700">{item.quantity_expected}</td>
                  <td className="px-4 py-3 text-right font-medium text-neutral-900">{item.quantity_received}</td>
                  <td className="px-4 py-3 text-neutral-500">{item.unit_of_measure ?? '—'}</td>
                  <td className="px-4 py-3 text-neutral-500 font-mono text-xs">{item.lot_batch_number ?? '—'}</td>
                </tr>
              ))
            )}
          </tbody>
        </table>
      </div>

      <ConfirmDialog
        open={confirmOpen}
        onClose={() => setConfirmOpen(false)}
        onConfirm={handleConfirm}
        title="Confirm delivery receipt?"
        description="This will mark the delivery receipt as confirmed. This action cannot be undone."
        confirmLabel="Confirm Receipt"
        variant="warning"
        loading={confirmMut.isPending}
      />
    </div>
  );
}
