import { useState } from 'react';
import { Link } from 'react-router-dom';
import { Plus, List } from 'lucide-react';
import { useWorkOrders } from '@/hooks/useMaintenance';
import type { WorkOrderStatus, WorkOrderPriority } from '@/types/maintenance';

const PRIORITY_COLORS: Record<WorkOrderPriority, string> = {
  low: 'bg-gray-100 text-gray-600',
  normal: 'bg-blue-100 text-blue-700',
  high: 'bg-orange-100 text-orange-700',
  critical: 'bg-red-100 text-red-700',
};

const STATUS_COLORS: Record<WorkOrderStatus, string> = {
  open: 'bg-gray-100 text-gray-600',
  in_progress: 'bg-blue-100 text-blue-700',
  completed: 'bg-green-100 text-green-700',
  cancelled: 'bg-gray-100 text-gray-400',
};

export default function WorkOrderListPage() {
  const [status, setStatus] = useState('');
  const [type, setType] = useState('');
  const [priority, setPriority] = useState('');
  const [withArchived, setWithArchived] = useState(false);

  const params: Record<string, string | boolean> = {};
  if (status) params.status = status;
  if (type) params.type = type;
  if (priority) params.priority = priority;
  if (withArchived) params.with_archived = true;

  const { data, isLoading } = useWorkOrders(Object.keys(params).length ? params : undefined);

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-semibold">Work Orders</h1>
        <Link
          to="/maintenance/work-orders/new"
          className="inline-flex items-center gap-1.5 rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700"
        >
          <Plus size={16} /> New Work Order
        </Link>
      </div>

      <div className="flex gap-2 flex-wrap">
        <select value={type} onChange={e => setType(e.target.value)} className="rounded border border-gray-300 px-2 py-1.5 text-sm">
          <option value="">All Types</option>
          <option value="corrective">Corrective</option>
          <option value="preventive">Preventive</option>
        </select>
        <select value={priority} onChange={e => setPriority(e.target.value)} className="rounded border border-gray-300 px-2 py-1.5 text-sm">
          <option value="">All Priorities</option>
          <option value="low">Low</option>
          <option value="normal">Normal</option>
          <option value="high">High</option>
          <option value="critical">Critical</option>
        </select>
        <select value={status} onChange={e => setStatus(e.target.value)} className="rounded border border-gray-300 px-2 py-1.5 text-sm">
          <option value="">All Statuses</option>
          <option value="open">Open</option>
          <option value="in_progress">In Progress</option>
          <option value="completed">Completed</option>
          <option value="cancelled">Cancelled</option>
        </select>
        <label className="flex items-center gap-2 text-sm text-gray-600 cursor-pointer select-none">
          <input type="checkbox" checked={withArchived} onChange={(e) => setWithArchived(e.target.checked)} className="rounded border-gray-300 text-indigo-600" />
          <span>Show Archived</span>
        </label>
      </div>

      <div className="overflow-hidden rounded-lg border border-gray-200 bg-white">
        <table className="w-full text-sm">
          <thead className="bg-gray-50 text-xs uppercase text-gray-500">
            <tr>
              <th className="px-4 py-3 text-left">Reference</th>
              <th className="px-4 py-3 text-left">Title</th>
              <th className="px-4 py-3 text-left">Equipment</th>
              <th className="px-4 py-3 text-left">Type</th>
              <th className="px-4 py-3 text-left">Priority</th>
              <th className="px-4 py-3 text-left">Status</th>
              <th className="px-4 py-3 text-left">Scheduled</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-gray-100">
            {isLoading ? (
              <tr><td colSpan={7} className="px-4 py-8 text-center text-gray-400">Loading…</td></tr>
            ) : (data?.data ?? []).length === 0 ? (
              <tr>
                <td colSpan={7} className="px-4 py-8 text-center text-gray-400">
                  <List size={32} className="mx-auto mb-2 opacity-30" />
                  No work orders found.
                </td>
              </tr>
            ) : (
              (data?.data ?? []).map(wo => (
                <tr key={wo.ulid} className="hover:bg-gray-50">
                  <td className="px-4 py-3 font-mono text-xs">{wo.mwo_reference}</td>
                  <td className="px-4 py-3 font-medium text-indigo-600">{wo.title}</td>
                  <td className="px-4 py-3 text-gray-500">{wo.equipment?.name ?? '—'}</td>
                  <td className="px-4 py-3 capitalize text-gray-600">{wo.type}</td>
                  <td className="px-4 py-3">
                    <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${PRIORITY_COLORS[wo.priority]}`}>
                      {wo.priority}
                    </span>
                  </td>
                  <td className="px-4 py-3">
                    {wo.deleted_at && <span className="rounded-full px-2 py-0.5 text-xs font-medium bg-orange-100 text-orange-700 mr-1">Archived</span>}
                    <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${STATUS_COLORS[wo.status]}`}>
                      {wo.status.replace('_', ' ')}
                    </span>
                  </td>
                  <td className="px-4 py-3 text-gray-500">{wo.scheduled_date ?? '—'}</td>
                </tr>
              ))
            )}
          </tbody>
        </table>
      </div>
    </div>
  );
}
