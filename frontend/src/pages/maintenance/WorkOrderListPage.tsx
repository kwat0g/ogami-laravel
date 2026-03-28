import { useState, useCallback } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { Plus, List } from 'lucide-react';
import { PageHeader } from '@/components/ui/PageHeader';
import SearchInput from '@/components/ui/SearchInput';
import Pagination from '@/components/ui/Pagination';
import { useWorkOrders } from '@/hooks/useMaintenance';
import { useAuthStore } from '@/stores/authStore'
import { useQuery } from '@tanstack/react-query'
import ArchiveToggleButton from '@/components/ui/ArchiveToggleButton'
import ArchiveViewBanner from '@/components/ui/ArchiveViewBanner'
import ArchiveRowActions from '@/components/ui/ArchiveRowActions'
import api from '@/lib/api';
import type { WorkOrderStatus, WorkOrderPriority } from '@/types/maintenance';

const PRIORITY_COLORS: Record<WorkOrderPriority, string> = {
  low: 'bg-neutral-100 text-neutral-600',
  normal: 'bg-neutral-100 text-neutral-700',
  high: 'bg-neutral-100 text-neutral-700',
  critical: 'bg-neutral-100 text-neutral-700',
};

const STATUS_COLORS: Record<WorkOrderStatus, string> = {
  open: 'bg-neutral-100 text-neutral-600',
  in_progress: 'bg-neutral-100 text-neutral-700',
  completed: 'bg-neutral-100 text-neutral-700',
  cancelled: 'bg-neutral-100 text-neutral-400',
};

export default function WorkOrderListPage() {
  const navigate = useNavigate();
  const [status, setStatus] = useState('');
  const [type, setType] = useState('');
  const [priority, setPriority] = useState('');
  const [isArchiveView, setIsArchiveView] = useState(false);
  const [search, setSearch] = useState('');
  const [debouncedSearch, setDebouncedSearch] = useState('');
  const [page, setPage] = useState(1);
  const canManage = useAuthStore(s => s.hasPermission('maintenance.manage'));

  const handleSearch = useCallback((val: string) => {
    setDebouncedSearch(val);
    setPage(1);
  }, []);

  const params: Record<string, string | number | boolean> = { page, per_page: 20 };
  if (status) params.status = status;
  if (type) params.type = type;
  if (priority) params.priority = priority;
  // Archive view handled by separate query
  if (debouncedSearch) params.search = debouncedSearch;

  const { data, isLoading } = useWorkOrders(params);

  return (
    <div className="space-y-4">
      <PageHeader
        title="Work Orders"
        actions={
          canManage ? (
            <Link
              to="/maintenance/work-orders/new"
              className="inline-flex items-center gap-1.5 rounded bg-neutral-900 px-3 py-2 text-sm font-medium text-white hover:bg-neutral-800"
            >
              <Plus size={16} /> New Work Order
            </Link>
          ) : undefined
        }
      />

      <div className="flex gap-2 flex-wrap items-center">
        <SearchInput
          value={search}
          onChange={setSearch}
          onSearch={handleSearch}
          placeholder="Search work orders..."
          className="w-64"
        />
        <select value={type} onChange={e => { setType(e.target.value); setPage(1); }} className="rounded border border-neutral-300 px-2 py-1.5 text-sm focus:ring-1 focus:ring-neutral-400">
          <option value="">All Types</option>
          <option value="corrective">Corrective</option>
          <option value="preventive">Preventive</option>
        </select>
        <select value={priority} onChange={e => setPriority(e.target.value)} className="rounded border border-neutral-300 px-2 py-1.5 text-sm focus:ring-1 focus:ring-neutral-400">
          <option value="">All Priorities</option>
          <option value="low">Low</option>
          <option value="normal">Normal</option>
          <option value="high">High</option>
          <option value="critical">Critical</option>
        </select>
        <select value={status} onChange={e => setStatus(e.target.value)} className="rounded border border-neutral-300 px-2 py-1.5 text-sm focus:ring-1 focus:ring-neutral-400">
          <option value="">All Statuses</option>
          <option value="open">Open</option>
          <option value="in_progress">In Progress</option>
          <option value="completed">Completed</option>
          <option value="cancelled">Cancelled</option>
        </select>
        <ArchiveToggleButton isArchiveView={isArchiveView} onToggle={() => setIsArchiveView(prev => !prev)} />
      </div>

      <div className="overflow-hidden rounded-lg border border-neutral-200 bg-white">
        <table className="w-full text-sm">
          <thead className="bg-neutral-50 text-xs text-neutral-500">
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
          <tbody className="divide-y divide-neutral-100">
            {isLoading ? (
              <tr><td colSpan={7} className="px-4 py-8 text-center text-neutral-400">Loading…</td></tr>
            ) : (data?.data ?? []).length === 0 ? (
              <tr>
                <td colSpan={7} className="px-4 py-8 text-center text-neutral-400">
                  <List size={32} className="mx-auto mb-2 opacity-30" />
                  No work orders found.
                </td>
              </tr>
            ) : (
              (data?.data ?? []).map(wo => (
                <tr
                  key={wo.ulid}
                  className="even:bg-neutral-100 hover:bg-neutral-50 cursor-pointer"
                  onClick={() => navigate(`/maintenance/work-orders/${wo.ulid}`)}
                >
                  <td className="px-4 py-3 font-mono text-xs">{wo.mwo_reference}</td>
                  <td className="px-4 py-3 font-medium text-neutral-900">{wo.title}</td>
                  <td className="px-4 py-3 text-neutral-500">{wo.equipment?.name ?? '—'}</td>
                  <td className="px-4 py-3 capitalize text-neutral-600">{wo.type}</td>
                  <td className="px-4 py-3">
                    <span className={`rounded px-2 py-0.5 text-xs font-medium ${PRIORITY_COLORS[wo.priority]}`}>
                      {wo.priority}
                    </span>
                  </td>
                  <td className="px-4 py-3">
                    {wo.deleted_at && <span className="rounded px-2 py-0.5 text-xs font-medium bg-neutral-100 text-neutral-700 mr-1">Archived</span>}
                    <span className={`rounded px-2 py-0.5 text-xs font-medium ${STATUS_COLORS[wo.status]}`}>
                      {wo.status?.replace('_', ' ') || 'Unknown'}
                    </span>
                  </td>
                  <td className="px-4 py-3 text-neutral-500">{wo.scheduled_date ?? '—'}</td>
                </tr>
              ))
            )}
          </tbody>
        </table>
      </div>

      {data?.meta && <Pagination meta={data.meta} onPageChange={setPage} />}
    </div>
  );
}
