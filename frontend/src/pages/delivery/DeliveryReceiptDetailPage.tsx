import { useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { Truck } from 'lucide-react';
import { toast } from 'sonner';
import { firstErrorMessage } from '@/lib/errorHandler'
import { useDeliveryReceipt, useConfirmDeliveryReceipt, useMarkDispatched, useMarkDelivered } from '@/hooks/useDelivery';
import { useAuthStore } from '@/stores/authStore';
import SkeletonLoader from '@/components/ui/SkeletonLoader';
import ConfirmDialog from '@/components/ui/ConfirmDialog';
import PageHeader from '@/components/ui/PageHeader';
import StatusBadge from '@/components/ui/StatusBadge';
import { Card, CardHeader, CardBody } from '@/components/ui/Card';
import { InfoRow, InfoList } from '@/components/ui/InfoRow';
import StatusTimeline from '@/components/ui/StatusTimeline';
import { getDeliveryReceiptSteps, isRejectedStatus } from '@/lib/workflowSteps';
import type { DrDirection } from '@/types/delivery';

const DIRECTION_COLORS: Record<DrDirection, string> = {
  inbound: 'bg-neutral-100 text-neutral-700',
  outbound: 'bg-neutral-100 text-neutral-700',
};

export default function DeliveryReceiptDetailPage(): React.ReactElement {
  const { ulid } = useParams<{ ulid: string }>();
  const navigate = useNavigate();
  const { hasPermission } = useAuthStore();
  const { data, isLoading, isError } = useDeliveryReceipt(ulid ?? '');
  const confirmMut = useConfirmDeliveryReceipt();
  const dispatchMut = useMarkDispatched();
  const deliverMut = useMarkDelivered();
  const [confirmOpen, setConfirmOpen] = useState(false);
  const [dispatchOpen, setDispatchOpen] = useState(false);
  const [deliverOpen, setDeliverOpen] = useState(false);
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

  const handleConfirm = async () => {
    try {
      await confirmMut.mutateAsync(dr.ulid);
      toast.success('Delivery receipt confirmed.');
      setConfirmOpen(false);
    } catch (err) {
      toast.error(firstErrorMessage(err, 'Failed to confirm receipt.'));
    }
  };

  const handleDispatch = async () => { try { await dispatchMut.mutateAsync(dr.ulid); toast.success('Delivery receipt dispatched.'); setDispatchOpen(false); } catch (err) { toast.error(firstErrorMessage(err, 'Failed to dispatch receipt.')); } };

  const handleDeliver = async () => { try { await deliverMut.mutateAsync(dr.ulid); toast.success('Delivery receipt marked as delivered.'); setDeliverOpen(false); } catch (err) { toast.error(firstErrorMessage(err, 'Failed to mark as delivered.')); } };

  const statusBadges = (
    <div className="flex items-center gap-2">
      <span className={`inline-flex items-center px-2.5 py-0.5 rounded text-xs font-medium ${DIRECTION_COLORS[dr.direction]}`}>
        {dr.direction}
      </span>
      <StatusBadge status={dr.status}>{dr.status?.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase())}</StatusBadge>
    </div>
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
              <button type="button" onClick={() => setConfirmOpen(true)} className="px-4 py-2 text-sm bg-neutral-900 text-white rounded hover:bg-neutral-800">Confirm Receipt</button>
            )}
            {dr.status === 'confirmed' && canManage && (
              <button type="button" onClick={() => setDispatchOpen(true)} className="px-4 py-2 text-sm bg-blue-600 text-white rounded hover:bg-blue-700">Dispatch Goods</button>
            )}
            {(dr.status === 'dispatched' || dr.status === 'partially_delivered') && canManage && (
              <button type="button" onClick={() => setDeliverOpen(true)} className="px-4 py-2 text-sm bg-green-600 text-white rounded hover:bg-green-700">Mark Delivered</button>
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

      {/* Details */}
      <Card className="mb-5">
        <CardHeader>Receipt Details</CardHeader>
        <CardBody>
          <InfoList columns={2}>
            <InfoRow label="Vendor" value={dr.vendor?.name ?? '—'} />
            <InfoRow label="Customer" value={dr.customer?.name ?? '—'} />
            <InfoRow label="Received By" value={dr.received_by?.name ?? '—'} />
            <InfoRow label="Remarks" value={dr.remarks ?? '—'} />
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
        </CardBody>
      </Card>

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
      <ConfirmDialog open={dispatchOpen} onClose={() => setDispatchOpen(false)} onConfirm={handleDispatch} title="Dispatch goods?" description="Mark delivery as dispatched from warehouse?" confirmLabel="Dispatch" variant="warning" loading={dispatchMut.isPending} />
      <ConfirmDialog open={deliverOpen} onClose={() => setDeliverOpen(false)} onConfirm={handleDeliver} title="Mark as Delivered?" description="Confirm goods received by customer?" confirmLabel="Mark Delivered" variant="warning" loading={deliverMut.isPending} />
    </div>
  );
}
