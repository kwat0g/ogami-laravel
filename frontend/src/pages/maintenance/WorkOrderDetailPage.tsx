import { useState } from 'react';
import { useNavigate, useParams, Link } from 'react-router-dom';
import { Wrench, XCircle } from 'lucide-react';
import { toast } from 'sonner';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api from '@/lib/api';
import { useWorkOrderDetail, useStartWorkOrder, useCompleteWorkOrder, useCancelWorkOrder } from '@/hooks/useMaintenance';
import { useAuthStore } from '@/stores/authStore';
import { PERMISSIONS } from '@/lib/permissions'
import SkeletonLoader from '@/components/ui/SkeletonLoader';
import ConfirmDialog from '@/components/ui/ConfirmDialog';
import ConfirmDestructiveDialog from '@/components/ui/ConfirmDestructiveDialog';
import PageHeader from '@/components/ui/PageHeader';
import StatusBadge from '@/components/ui/StatusBadge';
import { Card, CardHeader, CardBody } from '@/components/ui/Card';
import { InfoRow, InfoList } from '@/components/ui/InfoRow';
import { firstErrorMessage } from '@/lib/errorHandler';
import type { WorkOrderPriority } from '@/types/maintenance';

const PRIORITY_COLORS: Record<WorkOrderPriority, string> = {
  low:      'bg-neutral-100 text-neutral-500',
  normal:   'bg-neutral-100 text-neutral-600',
  high:     'bg-amber-50 text-amber-700',
  critical: 'bg-red-50 text-red-700',
};

const INPUT = 'w-full border border-neutral-300 rounded px-3 py-2 text-sm focus:ring-1 focus:ring-neutral-400 bg-white';

interface CompletionErrors {
  completion_notes?: string;
  labor_hours?: string;
  actual_completion_date?: string;
}

export default function WorkOrderDetailPage(): React.ReactElement {
  const { ulid } = useParams<{ ulid: string }>();
  const navigate = useNavigate();
  const { data, isLoading, isError } = useWorkOrderDetail(ulid ?? '');
  const { hasPermission } = useAuthStore();
  const startMut    = useStartWorkOrder();
  const completeMut = useCompleteWorkOrder();
  const cancelMut   = useCancelWorkOrder();

  const canManage = hasPermission(PERMISSIONS.maintenance.manage);

  const [showCompleteForm, setShowCompleteForm] = useState(false);
  const [completionNotes, setCompletionNotes]   = useState('');
  const [laborHours, setLaborHours]             = useState('');
  const [actualDate, setActualDate]             = useState('');
  const [completionErrors, setCompletionErrors] = useState<CompletionErrors>({});

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

  const validateCompletionForm = (): boolean => {
    const errors: CompletionErrors = {};
    if (!completionNotes.trim()) errors.completion_notes = 'Completion notes are required.';
    else if (completionNotes.trim().length < 10) errors.completion_notes = 'Completion notes must be at least 10 characters.';
    else if (completionNotes.length > 5000) errors.completion_notes = 'Completion notes must be less than 5000 characters.';
    
    if (laborHours) {
      const hours = parseFloat(laborHours);
      if (isNaN(hours) || hours < 0) errors.labor_hours = 'Labor hours must be a positive number.';
      else if (hours > 999) errors.labor_hours = 'Labor hours cannot exceed 999.';
    }
    
    if (actualDate) {
      const completion = new Date(actualDate);
      const today = new Date();
      today.setHours(23, 59, 59, 999);
      if (completion > today) errors.actual_completion_date = 'Completion date cannot be in the future.';
    }
    
    setCompletionErrors(errors);
    return Object.keys(errors).length === 0;
  };

  const handleStart = async () => {
    try {
      await startMut.mutateAsync(wo.ulid);
    } catch (err) {
      toast.error(firstErrorMessage(err));
    }
  };

  const handleComplete = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!validateCompletionForm()) {
      return;
    }
    try {
      await completeMut.mutateAsync({ ulid: wo.ulid, payload: {
        completion_notes:       completionNotes,
        labor_hours:            laborHours ? parseFloat(laborHours) : undefined,
        actual_completion_date: actualDate || undefined,
      } });
      setShowCompleteForm(false);
      setCompletionNotes('');
      setLaborHours('');
      setActualDate('');
      setCompletionErrors({});
    } catch (err) {
      toast.error(firstErrorMessage(err));
    }
  };

  const handleCancel = async () => {
    try {
      await cancelMut.mutateAsync(wo.ulid);
      navigate('/maintenance/work-orders');
    } catch (err) {
      toast.error(firstErrorMessage(err));
    }
  };

  const isOpen       = wo.status === 'open';
  const isInProgress = wo.status === 'in_progress';
  const isCompleted  = wo.status === 'completed';
  const _isCancelled  = wo.status === 'cancelled';
  const canBeCancelled = isOpen || isInProgress;

  const statusBadges = (
    <div className="flex items-center gap-2">
      <StatusBadge status={wo.status}>{wo.status?.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase())}</StatusBadge>
      <span className={`inline-flex items-center px-2.5 py-0.5 rounded text-xs font-medium capitalize ${PRIORITY_COLORS[wo.priority]}`}>
        {wo.priority}
      </span>
    </div>
  );

  return (
    <div className="max-w-7xl mx-auto">
      <PageHeader
        backTo="/maintenance/work-orders"
        title={wo.mwo_reference}
        subtitle={wo.title}
        icon={<Wrench className="w-5 h-5" />}
        status={statusBadges}
        actions={
          <div className="flex items-center gap-2 shrink-0">
            {isOpen && canManage && (
              <ConfirmDialog
                title="Start this work order?"
                description="This will move the status to In Progress. Make sure a technician is assigned before starting."
                confirmLabel="Start Work"
                onConfirm={handleStart}
              >
                <button
                  type="button"
                  disabled={startMut.isPending}
                  className="px-4 py-2 text-sm bg-neutral-900 text-white rounded hover:bg-neutral-800 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  Start Work
                </button>
              </ConfirmDialog>
            )}
            {isInProgress && !showCompleteForm && canManage && (
              <button
                type="button"
                onClick={() => setShowCompleteForm(true)}
                className="px-4 py-2 text-sm bg-neutral-900 text-white rounded hover:bg-neutral-800"
              >
                Complete
              </button>
            )}
            {canBeCancelled && canManage && (
              <ConfirmDestructiveDialog
                title="Cancel this work order?"
                description="This will permanently cancel the work order. This action cannot be undone."
                confirmWord="CANCEL"
                confirmLabel="Cancel Work Order"
                onConfirm={handleCancel}
              >
                <button
                  type="button"
                  disabled={cancelMut.isPending}
                  className="flex items-center gap-1.5 px-4 py-2 text-sm bg-white text-red-600 border border-red-300 rounded hover:bg-red-50 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  <XCircle className="w-4 h-4" />
                  Cancel
                </button>
              </ConfirmDestructiveDialog>
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
              <InfoRow 
                label="Equipment" 
                value={
                  wo.equipment ? (
                    <Link 
                      to={`/maintenance/equipment/${wo.equipment.ulid}`}
                      className="underline underline-offset-2 text-neutral-700 hover:text-neutral-900 font-medium"
                    >
                      {wo.equipment.name}
                    </Link>
                  ) : '—'
                } 
              />
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
                className={`${INPUT} ${completionErrors.actual_completion_date ? 'border-red-400' : ''}`}
                value={actualDate}
                onChange={e => {
                  setActualDate(e.target.value);
                  if (completionErrors.actual_completion_date) {
                    setCompletionErrors(prev => ({ ...prev, actual_completion_date: undefined }));
                  }
                }}
              />
              {completionErrors.actual_completion_date && (
                <p className="mt-1 text-xs text-red-600">{completionErrors.actual_completion_date}</p>
              )}
            </div>
            <div>
              <label className="block text-sm font-medium text-neutral-700 mb-1">
                Labor Hours
              </label>
              <input
                type="number"
                min="0"
                step="0.5"
                className={`${INPUT} ${completionErrors.labor_hours ? 'border-red-400' : ''}`}
                value={laborHours}
                onChange={e => {
                  setLaborHours(e.target.value);
                  if (completionErrors.labor_hours) {
                    setCompletionErrors(prev => ({ ...prev, labor_hours: undefined }));
                  }
                }}
                placeholder="e.g. 3.5"
              />
              {completionErrors.labor_hours && (
                <p className="mt-1 text-xs text-red-600">{completionErrors.labor_hours}</p>
              )}
            </div>
          </div>
          <div>
            <label className="block text-sm font-medium text-neutral-700 mb-1">
              Completion Notes <span className="text-red-500">*</span>
            </label>
            <textarea
              rows={4}
              className={`${INPUT} resize-none ${completionErrors.completion_notes ? 'border-red-400' : ''}`}
              value={completionNotes}
              onChange={e => {
                setCompletionNotes(e.target.value);
                if (completionErrors.completion_notes) {
                  setCompletionErrors(prev => ({ ...prev, completion_notes: undefined }));
                }
              }}
              placeholder="Describe the work performed, parts replaced, test results…"
            />
            {completionErrors.completion_notes && (
              <p className="mt-1 text-xs text-red-600">{completionErrors.completion_notes}</p>
            )}
          </div>
          <div className="flex justify-end gap-3 pt-1">
            <button
              type="button"
              onClick={() => {
                setShowCompleteForm(false);
                setCompletionErrors({});
              }}
              className="px-4 py-2 text-sm bg-white text-neutral-700 border border-neutral-300 rounded hover:bg-neutral-50"
            >
              Cancel
            </button>
            <ConfirmDialog
              title="Complete work order?"
              description="This will mark the work order as completed and record the labor hours and notes."
              confirmLabel="Complete"
              onConfirm={(_e?: React.MouseEvent) => {
                // Form onSubmit handles the actual submission
              }}
            >
              <button
                type="submit"
                disabled={completeMut.isPending}
                className="px-4 py-2 text-sm bg-neutral-900 text-white rounded hover:bg-neutral-800 disabled:opacity-50 disabled:cursor-not-allowed"
              >
                {completeMut.isPending ? 'Saving…' : 'Mark as Completed'}
              </button>
            </ConfirmDialog>
          </div>
        </form>
      )}

      {/* ── Work Order Parts (FS-026) ──────────────────────────────────────── */}
      <WorkOrderPartsSection workOrderId={wo.id} ulid={wo.ulid} canManage={canManage && !isCompleted} />
    </div>
  );
}

/* ── Work Order Parts sub-component ───────────────────────────────────────── */
function WorkOrderPartsSection({ workOrderId, ulid, canManage }: { workOrderId: number; ulid: string; canManage: boolean }) {
  const [showAddForm, setShowAddForm] = useState(false);
  const [itemId, setItemId] = useState('');
  const [qty, setQty] = useState('');

  const { data: partsData, isLoading } = useQuery({
    queryKey: ['work-order-parts', ulid],
    queryFn: async () => {
      const res = await api.get<{ data: WorkOrderPart[] }>(`/maintenance/work-orders/${workOrderId}/parts`);
      return res.data.data;
    },
  });

  const addPartMut = useMutation({
    mutationFn: async (payload: { item_master_id: number; quantity: number }) => {
      const res = await api.post(`/maintenance/work-orders/${workOrderId}/parts`, payload);
      return res.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['work-order-parts', ulid] });
      setShowAddForm(false);
      setItemId('');
      setQty('');
      toast.success('Part added to work order');
    },
    onError: (err: unknown) => {
      toast.error(firstErrorMessage(err));
    },
  });

  const queryClient = useQueryClient();
  const parts = partsData ?? [];

  return (
    <Card>
      <CardHeader>
        <div className="flex items-center justify-between">
          <h3 className="font-semibold">Parts Used</h3>
          {canManage && (
            <button onClick={() => setShowAddForm(true)} className="px-3 py-1 text-xs bg-neutral-900 text-white rounded hover:bg-neutral-800">
              + Add Part
            </button>
          )}
        </div>
      </CardHeader>
      <CardBody>
        {isLoading ? (
          <SkeletonLoader rows={3} />
        ) : parts.length === 0 ? (
          <p className="text-sm text-neutral-500">No parts recorded for this work order.</p>
        ) : (
          <table className="w-full text-sm">
            <thead><tr className="text-left text-neutral-500 border-b"><th className="pb-2">Item</th><th className="pb-2 text-right">Qty</th></tr></thead>
            <tbody>
              {parts.map((part: WorkOrderPart) => (
                <tr key={part.id} className="border-b border-neutral-100 last:border-0">
                  <td className="py-2">{part.item_master?.name ?? `Item #${part.item_master_id}`}</td>
                  <td className="py-2 text-right font-mono">{part.quantity}</td>
                </tr>
              ))}
            </tbody>
          </table>
        )}

        {showAddForm && (
          <div className="mt-3 flex gap-2 items-end">
            <div>
              <label className="block text-xs text-neutral-500">Item ID</label>
              <input type="number" value={itemId} onChange={(e) => setItemId(e.target.value)} className={`${INPUT} w-28`} placeholder="Item ID" />
            </div>
            <div>
              <label className="block text-xs text-neutral-500">Quantity</label>
              <input type="number" value={qty} onChange={(e) => setQty(e.target.value)} className={`${INPUT} w-20`} placeholder="Qty" />
            </div>
            <button
              onClick={() => addPartMut.mutate({ item_master_id: Number(itemId), quantity: Number(qty) })}
              disabled={addPartMut.isPending || !itemId || !qty}
              className="px-3 py-2 text-xs bg-neutral-900 text-white rounded hover:bg-neutral-800 disabled:opacity-50"
            >
              {addPartMut.isPending ? 'Adding...' : 'Add'}
            </button>
            <button onClick={() => setShowAddForm(false)} className="px-3 py-2 text-xs border border-neutral-300 rounded hover:bg-neutral-50">
              Cancel
            </button>
          </div>
        )}
      </CardBody>
    </Card>
  );
}

interface WorkOrderPart {
  id: number;
  item_master_id: number;
  quantity: number;
  item_master?: { id: number; name: string; item_code: string };
}
