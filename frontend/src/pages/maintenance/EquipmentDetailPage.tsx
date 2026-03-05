import { useState } from 'react';
import { useParams } from 'react-router-dom';
import { useEquipmentDetail, useStartWorkOrder, useCompleteWorkOrder } from '@/hooks/useMaintenance';
import { toast } from 'sonner';

export default function EquipmentDetailPage() {
  const { ulid } = useParams<{ ulid: string }>();
  const { data, isLoading } = useEquipmentDetail(ulid ?? '');
  const startWo = useStartWorkOrder();
  const completeWo = useCompleteWorkOrder();
  const [completionNotes, _setCompletionNotes] = useState<Record<string, string>>({});

  const eq = data?.data;

  if (isLoading) return <div className="py-12 text-center text-gray-400">Loading…</div>;
  if (!eq) return <div className="py-12 text-center text-gray-400">Equipment not found.</div>;

  const _handleStart = async (woUlid: string) => {
    try {
      await startWo.mutateAsync(woUlid);
      toast.success('Work order started.');
    } catch {
      toast.error('Failed to start work order.');
    }
  };

  const _handleComplete = async (woUlid: string) => {
    try {
      await completeWo.mutateAsync({ ulid: woUlid, payload: { completion_notes: completionNotes[woUlid] ?? '' } });
      toast.success('Work order completed. Equipment returned to operational.');
    } catch {
      toast.error('Failed to complete work order.');
    }
  };

  const STATUS_COLOR: Record<string, string> = {
    operational: 'bg-green-100 text-green-700',
    under_maintenance: 'bg-yellow-100 text-yellow-700',
    decommissioned: 'bg-gray-100 text-gray-500',
  };

  return (
    <div className="space-y-6">
      <div className="flex items-start justify-between">
        <div>
          <p className="font-mono text-xs text-gray-400">{eq.equipment_code}</p>
          <h1 className="text-2xl font-semibold">{eq.name}</h1>
        </div>
        <span className={`rounded-full px-3 py-1 text-sm font-medium ${STATUS_COLOR[eq.status]}`}>
          {eq.status.replace('_', ' ')}
        </span>
      </div>

      {/* Details */}
      <div className="rounded-lg border border-gray-200 bg-white p-5 grid grid-cols-2 gap-4 text-sm">
        <div><span className="text-gray-500">Category:</span> {eq.category ?? '—'}</div>
        <div><span className="text-gray-500">Manufacturer:</span> {eq.manufacturer ?? '—'}</div>
        <div><span className="text-gray-500">Model:</span> {eq.model_number ?? '—'}</div>
        <div><span className="text-gray-500">Serial:</span> {eq.serial_number ?? '—'}</div>
        <div><span className="text-gray-500">Location:</span> {eq.location ?? '—'}</div>
        <div><span className="text-gray-500">Commissioned:</span> {eq.commissioned_on ?? '—'}</div>
      </div>

      {/* PM Schedules */}
      {eq.pm_schedules && eq.pm_schedules.length > 0 && (
        <div>
          <h2 className="mb-2 font-semibold">PM Schedules</h2>
          <div className="overflow-hidden rounded-lg border border-gray-200 bg-white">
            <table className="w-full text-sm">
              <thead className="bg-gray-50 text-xs uppercase text-gray-500">
                <tr>
                  <th className="px-4 py-2 text-left">Task</th>
                  <th className="px-4 py-2 text-left">Every</th>
                  <th className="px-4 py-2 text-left">Last Done</th>
                  <th className="px-4 py-2 text-left">Next Due</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-100">
                {eq.pm_schedules.map(s => (
                  <tr key={s.id}>
                    <td className="px-4 py-2">{s.task_name}</td>
                    <td className="px-4 py-2">{s.frequency_days}d</td>
                    <td className="px-4 py-2">{s.last_done_on ?? '—'}</td>
                    <td className="px-4 py-2">{s.next_due_on ?? '—'}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      )}

      {/* Work Orders inline actions — only shown when loaded */}
      {eq.work_orders_count !== undefined && eq.work_orders_count > 0 && (
        <p className="text-sm text-gray-500">This equipment has {eq.work_orders_count} linked work order(s). View them in the Work Orders list.</p>
      )}
    </div>
  );
}
