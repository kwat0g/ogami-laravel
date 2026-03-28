import { useState, useCallback } from 'react';
import { Link } from 'react-router-dom';
import { Plus, Wrench } from 'lucide-react';
import { useQuery } from '@tanstack/react-query';
import { PageHeader } from '@/components/ui/PageHeader';
import SearchInput from '@/components/ui/SearchInput';
import Pagination from '@/components/ui/Pagination';
import { useEquipment } from '@/hooks/useMaintenance';
import { useAuthStore } from '@/stores/authStore';
import ArchiveToggleButton from '@/components/ui/ArchiveToggleButton';
import ArchiveViewBanner from '@/components/ui/ArchiveViewBanner';
import api from '@/lib/api';
import type { EquipmentStatus } from '@/types/maintenance';

const STATUS_COLORS: Record<EquipmentStatus, string> = {
  operational: 'bg-neutral-100 text-neutral-700',
  under_maintenance: 'bg-neutral-100 text-neutral-700',
  decommissioned: 'bg-neutral-100 text-neutral-500',
};

export default function EquipmentListPage() {
  const [status, setStatus] = useState('');
  const [isArchiveView, setIsArchiveView] = useState(false);
  const [search, setSearch] = useState('');
  const [debouncedSearch, setDebouncedSearch] = useState('');
  const [page, setPage] = useState(1);

  const { data, isLoading } = useEquipment({
    ...(status ? { status } : {}),
    ...(debouncedSearch ? { search: debouncedSearch } : {}),
    page,
    per_page: 20,
  });

  const { data: archivedData, isLoading: archivedLoading } = useQuery({
    queryKey: ['equipment', 'archived', debouncedSearch],
    queryFn: () => api.get('/maintenance/equipment-archived', { params: { search: debouncedSearch || undefined, per_page: 20 } }),
    enabled: isArchiveView,
  });

  const currentData = isArchiveView ? (archivedData?.data?.data ?? []) : (data?.data ?? []);
  const currentLoading = isArchiveView ? archivedLoading : isLoading;
  const canManage = useAuthStore(s => s.hasPermission('maintenance.manage'));

  const handleSearch = useCallback((val: string) => {
    setDebouncedSearch(val);
    setPage(1);
  }, []);

  return (
    <div className="space-y-4">
      <PageHeader
        title="Equipment"
        actions={
          canManage ? (
            <Link
              to="/maintenance/equipment/new"
              className="inline-flex items-center gap-1.5 rounded bg-neutral-900 px-3 py-2 text-sm font-medium text-white hover:bg-neutral-800"
            >
              <Plus size={16} /> Add Equipment
            </Link>
          ) : undefined
        }
      />

      <div className="flex flex-wrap items-center gap-2">
        <SearchInput
          value={search}
          onChange={setSearch}
          onSearch={handleSearch}
          placeholder="Search equipment..."
          className="w-64"
        />
        <select
          value={status}
          onChange={e => { setStatus(e.target.value); setPage(1); }}
          className="rounded border border-neutral-300 px-2 py-2 text-sm focus:ring-1 focus:ring-neutral-400"
        >
          <option value="">All Statuses</option>
          <option value="operational">Operational</option>
          <option value="under_maintenance">Under Maintenance</option>
          <option value="decommissioned">Decommissioned</option>
        </select>
        <ArchiveToggleButton isArchiveView={isArchiveView} onToggle={() => setIsArchiveView(prev => !prev)} />
      </div>

      {isArchiveView && <ArchiveViewBanner />}

      <div className="overflow-hidden rounded-lg border border-neutral-200 bg-white">
        <table className="w-full text-sm">
          <thead className="bg-neutral-50 text-xs text-neutral-500">
            <tr>
              <th className="px-4 py-3 text-left">Code</th>
              <th className="px-4 py-3 text-left">Name</th>
              <th className="px-4 py-3 text-left">Category</th>
              <th className="px-4 py-3 text-left">Location</th>
              <th className="px-4 py-3 text-left">Status</th>
              <th className="px-4 py-3 text-left">WOs</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-neutral-100">
            {isLoading ? (
              <tr>
                <td colSpan={6} className="px-4 py-8 text-center text-neutral-400">Loading…</td>
              </tr>
            ) : (data?.data ?? []).length === 0 ? (
              <tr>
                <td colSpan={6} className="px-4 py-8 text-center text-neutral-400">
                  <Wrench size={32} className="mx-auto mb-2 opacity-30" />
                  No equipment found.
                </td>
              </tr>
            ) : (
              (data?.data ?? []).map(eq => (
                <tr key={eq.ulid} className="even:bg-neutral-100 hover:bg-neutral-50">
                  <td className="px-4 py-3 font-mono text-xs">{eq.equipment_code}</td>
                  <td className="px-4 py-3">
                    <Link to={`/maintenance/equipment/${eq.ulid}`} className="font-medium text-neutral-900 hover:text-neutral-700">
                      {eq.name}
                    </Link>
                  </td>
                  <td className="px-4 py-3 text-neutral-500">{eq.category ?? '—'}</td>
                  <td className="px-4 py-3 text-neutral-500">{eq.location ?? '—'}</td>
                  <td className="px-4 py-3">
                    {eq.deleted_at && <span className="rounded px-2 py-0.5 text-xs font-medium bg-neutral-100 text-neutral-700 mr-1">Archived</span>}
                    <span className={`rounded px-2 py-0.5 text-xs font-medium ${STATUS_COLORS[eq.status] || STATUS_COLORS.operational}`}>
                      {eq.status?.replace('_', ' ') || 'Unknown'}
                    </span>
                  </td>
                  <td className="px-4 py-3 text-neutral-500">{eq.work_orders_count ?? 0}</td>
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
