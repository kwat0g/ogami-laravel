import { useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { Wrench } from 'lucide-react';
import { toast } from 'sonner';
import { useWorkOrderDetail, useStartWorkOrder, useCompleteWorkOrder } from '@/hooks/useMaintenance';
import SkeletonLoader from '@/components/ui/SkeletonLoader';
import ConfirmDialog from '@/components/ui/ConfirmDialog';
import PageHeader from '@/components/ui/PageHeader';
import StatusBadge from '@/components/ui/StatusBadge';
import { Card, CardHeader, CardBody } from '@/components/ui/Card';
import { InfoRow, InfoList } from '@/components/ui/InfoRow';
import type { WorkOrderStatus, WorkOrderPriority } from '@/types/maintenance';

const PRIORITY_COLORS: Record<WorkOrderPriority, string> = {
  low:      'bg-neutral-100 text-neutral-500',
  normal:   'bg-neutral-100 text-neutral-600',
  high:     'bg-amber-50 text-amber-700',
  critical: 'bg-red-50 text-red-700',
};

const INPUT = 'w-full border border-neutral-300 rounded px-3 py-2 text-sm focus:ring-1 focus:ring-neutral-400 bg-white';

export default function WorkOrderDetailPage(): React.ReactElement {
  const { ulid } = useParams<{ ulid: string }>();
  const navigate = useNavigate();
  const { data, isLoading, isError } = useWorkOrderDetail(ulid ?? '');
  const startMut    = useStartWorkOrder();
  const completeMut = useCompleteWorkOrder();

  const [showCompleteForm, setShowCompleteForm] = useState(false);
  const [completionNotes, setCompletionNotes]   = useState('');
  const [laborHours, setLaborHours]             = useState('');
  const [actualDate, setActualDate]             = useState('');
  const [startConfirm, setStartConfirm]         = useState(false);

  if (isLoading) return <SkeletonLoader rows={6} />;
  if (isError || !data?.data) {
    return (
      <div className="text-sm text-red-600 mt-4">
        Work order not found or you do not have access.{' '}
        <button onClick={() => navigate('/maintenance/work-orders')} className="underline text-neutral-600">
          Back to list
        </button>
      </div>
    );
  }

  const wo = data.data;

  const handleStart = async () => {
    try {
      await startMut.mutateAsync(wo.ulid);
      setStartConfirm(false);
      toast.success('Work order started.');
    } catch {
      toast.error('Failed to start work order.');
    }
  };

  const handleComplete = async (e: React.FormEvent) => {
    e.preventDefault();
    try {
      await completeMut.mutateAsync({ ulid: wo.ulid, payload: {
        completion_notes:       completionNotes,
        labor_hours:            laborHours ? parseFloat(laborHours) : undefined,
        actual_completion_date: actualDate || undefined,
      } });
      toast.success('Work order completed.');
      setShowCompleteForm(false);
    } catch {
      toast.error('Failed to complete work order.');
    }
  };

  const isOpen       = wo.status === 'open';
  const isInProgress = wo.status === 'in_progress';
  const isCompleted  = wo.status === 'completed';

  const statusBadges = (
    <div className="flex items-center gap-2">
      <StatusBadge label={wo.status} />
      <span className={`inline-flex items-center px-2.5 py-0.5 rounded text-xs font-medium capitalize ${PRIORITY_COLORS[wo.priority]}`}>
        {wo.priority}
      </span>
    </div>
  );

  return (
    <div className="max-w-5xl mx-auto">
      <PageHeader
        backTo="/maintenance/work-orders"
        title={wo.mwo_reference}
        subtitle={wo.title}
        icon={<Wrench className="w-5 h-5" />}
        status={statusBadges}
        actions={
          <div className="flex items-center gap-2 shrink-0">
            {isOpen && (
              <button
                type="button"
                onClick={() => setStartConfirm(true)}
                disabled={startMut.isPending}
                className="px-4 py-2 text-sm bg-neutral-900 text-white rounded hover:bg-neutral-800 disabled:opacity-50"
              >
                Start Work
              </button>
            )}
            {isInProgress && !showCompleteForm && (
              <button
                type="button"
                onClick={() => setShowCompleteForm(true)}
                className="px-4 py-2 text-sm bg-neutral-900 text-white rounded hover:bg-neutral-800"
              >
                Complete
              </button>
            )}
          </div>
        }
      />

      {/* Detail card */}
      {!showCompleteForm && (
        <Card className="mb-5">
          <CardHeader>Work Order Details</CardHeader>
          <CardBody>
            <InfoList columns={2}>
              <InfoRow label="Type" value={<span className="capitalize">{wo.type}</span>} />
              <InfoRow label="Equipment" value={wo.equipment?.name ?? '—'} />
              <InfoRow label="Assigned To" value={wo.assigned_to?.name ?? '—'} />
              <InfoRow label="Scheduled Date" value={wo.scheduled_date ?? '—'} />
              {isCompleted && (
                <InfoRow label="Completed At" value={wo.completed_at ? new Date(wo.completed_at).toLocaleDateString() : '—'} />
              )}
              {isCompleted && wo.labor_hours != null && (
                <InfoRow label="Labor Hours" value={`${wo.labor_hours} hrs`} />
              )}
              {wo.description && <InfoRow label="Description" value={wo.description} />}
              {wo.completion_notes && <InfoRow label="Completion Notes" value={wo.completion_notes} />}
            </InfoList>
          </CardBody>
        </Card>
      )}

      {/* Complete form */}
      {showCompleteForm && (
        <form onSubmit={handleComplete} className="bg-white border border-neutral-200 rounded p-6 mb-5 space-y-4">
          <h2 className="text-sm font-semibold text-neutral-700">Complete Work Order</h2>
          <div className="grid grid-cols-2 gap-4">
            <div>
              <label className="block text-sm font-medium text-neutral-700 mb-1">
                Actual Completion Date
              </label>
              <input
                type="date"
                className={INPUT}
                value={actualDate}
                onChange={e => setActualDate(e.target.value)}
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-neutral-700 mb-1">
                Labor Hours
              </label>
              <input
                type="number"
                min="0"
                step="0.5"
                className={INPUT}
                value={laborHours}
                onChange={e => setLaborHours(e.target.value)}
                placeholder="e.g. 3.5"
              />
            </div>
          </div>
          <div>
            <label className="block text-sm font-medium text-neutral-700 mb-1">Completion Notes <span className="text-red-500">*</span></label>
            <textarea
              rows={4}
              required
              className={INPUT + ' resize-none'}
              value={completionNotes}
              onChange={e => setCompletionNotes(e.target.value)}
              placeholder="Describe the work performed, parts replaced, test results…"
            />
          </div>
          <div className="flex justify-end gap-3 pt-1">
            <button
              type="button"
              onClick={() => setShowCompleteForm(false)}
              className="px-4 py-2 text-sm bg-white text-neutral-700 border border-neutral-300 rounded hover:bg-neutral-50"
            >
              Cancel
            </button>
            <button
              type="submit"
              disabled={completeMut.isPending}
              className="px-4 py-2 text-sm bg-neutral-900 text-white rounded hover:bg-neutral-800 disabled:opacity-50"
            >
              {completeMut.isPending ? 'Saving…' : 'Mark as Completed'}
            </button>
          </div>
        </form>
      )}

      <ConfirmDialog
        open={startConfirm}
        onClose={() => setStartConfirm(false)}
        onConfirm={handleStart}
        title="Start this work order?"
        description="This will move the status to In Progress. Make sure a technician is assigned before starting."
        confirmLabel="Start Work"
        loading={startMut.isPending}
      />
    </div>
  );
}
