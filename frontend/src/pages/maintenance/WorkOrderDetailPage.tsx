import { useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { ArrowLeft, Wrench } from 'lucide-react';
import { toast } from 'sonner';
import { useWorkOrderDetail, useStartWorkOrder, useCompleteWorkOrder } from '@/hooks/useMaintenance';
import SkeletonLoader from '@/components/ui/SkeletonLoader';
import ConfirmDialog from '@/components/ui/ConfirmDialog';
import type { WorkOrderStatus, WorkOrderPriority } from '@/types/maintenance';

const STATUS_COLOR: Record<WorkOrderStatus, string> = {
  open:        'bg-neutral-100 text-neutral-600',
  in_progress: 'bg-blue-50 text-blue-700',
  completed:   'bg-green-50 text-green-700',
  cancelled:   'bg-neutral-100 text-neutral-400',
};

const STATUS_LABEL: Record<WorkOrderStatus, string> = {
  open:        'Open',
  in_progress: 'In Progress',
  completed:   'Completed',
  cancelled:   'Cancelled',
};

const PRIORITY_COLOR: Record<WorkOrderPriority, string> = {
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

  return (
    <div className="max-w-3xl">
      {/* Header */}
      <div className="flex items-center justify-between gap-4 mb-6">
        <div className="flex items-center gap-3">
          <button
            type="button"
            onClick={() => navigate('/maintenance/work-orders')}
            className="p-2 rounded-lg border border-neutral-200 bg-white hover:bg-neutral-50 hover:border-neutral-300 text-neutral-500"
            aria-label="Back to Work Orders"
          >
            <ArrowLeft className="w-4 h-4" />
          </button>
          <div className="w-10 h-10 bg-neutral-100 rounded-lg flex items-center justify-center shrink-0">
            <Wrench className="w-5 h-5 text-neutral-600" />
          </div>
          <div>
            <div className="flex items-center gap-3">
              <h1 className="text-lg font-semibold text-neutral-900 font-mono">{wo.mwo_reference}</h1>
              <span className={`inline-flex items-center px-2.5 py-0.5 rounded text-xs font-medium ${STATUS_COLOR[wo.status]}`}>
                {STATUS_LABEL[wo.status]}
              </span>
              <span className={`inline-flex items-center px-2.5 py-0.5 rounded text-xs font-medium capitalize ${PRIORITY_COLOR[wo.priority]}`}>
                {wo.priority}
              </span>
            </div>
            <p className="text-sm text-neutral-500 mt-0.5">{wo.title}</p>
          </div>
        </div>

        {/* Action buttons */}
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
      </div>

      {/* Detail card */}
      {!showCompleteForm && (
        <div className="bg-white border border-neutral-200 rounded p-6 mb-5">
          <dl className="grid grid-cols-2 gap-x-8 gap-y-4 text-sm">
            <div>
              <dt className="text-xs font-semibold text-neutral-500 uppercase tracking-wide">Type</dt>
              <dd className="mt-1 text-neutral-900 capitalize">{wo.type}</dd>
            </div>
            <div>
              <dt className="text-xs font-semibold text-neutral-500 uppercase tracking-wide">Equipment</dt>
              <dd className="mt-1 text-neutral-900">{wo.equipment?.name ?? '—'}</dd>
            </div>
            <div>
              <dt className="text-xs font-semibold text-neutral-500 uppercase tracking-wide">Assigned To</dt>
              <dd className="mt-1 text-neutral-900">{wo.assigned_to?.name ?? '—'}</dd>
            </div>
            <div>
              <dt className="text-xs font-semibold text-neutral-500 uppercase tracking-wide">Scheduled Date</dt>
              <dd className="mt-1 text-neutral-900">{wo.scheduled_date ?? '—'}</dd>
            </div>
            {isCompleted && (
              <div>
                <dt className="text-xs font-semibold text-neutral-500 uppercase tracking-wide">Completed At</dt>
                <dd className="mt-1 text-neutral-900">{wo.completed_at ? new Date(wo.completed_at).toLocaleDateString() : '—'}</dd>
              </div>
            )}
            {isCompleted && wo.labor_hours != null && (
              <div>
                <dt className="text-xs font-semibold text-neutral-500 uppercase tracking-wide">Labor Hours</dt>
                <dd className="mt-1 text-neutral-900">{wo.labor_hours} hrs</dd>
              </div>
            )}
            {wo.description && (
              <div className="col-span-2">
                <dt className="text-xs font-semibold text-neutral-500 uppercase tracking-wide">Description</dt>
                <dd className="mt-1 text-neutral-900 whitespace-pre-wrap">{wo.description}</dd>
              </div>
            )}
            {wo.completion_notes && (
              <div className="col-span-2">
                <dt className="text-xs font-semibold text-neutral-500 uppercase tracking-wide">Completion Notes</dt>
                <dd className="mt-1 text-neutral-900 whitespace-pre-wrap">{wo.completion_notes}</dd>
              </div>
            )}
          </dl>
        </div>
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
              className="px-4 py-2 text-sm border border-neutral-300 rounded hover:bg-neutral-50"
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
