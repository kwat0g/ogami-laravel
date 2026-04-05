import { useState, useCallback } from 'react';
import { Link } from 'react-router-dom';
import { Plus, Settings } from 'lucide-react';
import { useQuery } from '@tanstack/react-query';
import { PageHeader } from '@/components/ui/PageHeader';
import SearchInput from '@/components/ui/SearchInput';
import Pagination from '@/components/ui/Pagination';
import { useMolds } from '@/hooks/useMold';
import { useAuthStore } from '@/stores/authStore';
import { PERMISSIONS } from '@/lib/permissions'
import ArchiveToggleButton from '@/components/ui/ArchiveToggleButton';
import ArchiveViewBanner from '@/components/ui/ArchiveViewBanner'
import api from '@/lib/api';
import type { MoldStatus } from '@/types/mold';

const STATUS_COLORS: Record<MoldStatus, string> = {
  active: 'bg-neutral-100 text-neutral-700',
  under_maintenance: 'bg-neutral-100 text-neutral-700',
  retired: 'bg-neutral-100 text-neutral-500',
};

export default function MoldListPage() {
  const [status, setStatus] = useState('');
  const [isArchiveView, setIsArchiveView] = useState(false);
  const [search, setSearch] = useState('');
  const [debouncedSearch, setDebouncedSearch] = useState('');
  const [page, setPage] = useState(1);

  const { data, isLoading, refetch } = useMolds({
    ...(status ? { status } : {}),
    ...(debouncedSearch ? { search: debouncedSearch } : {}),
    page,
    per_page: 20,
  });

  const { data: archivedData, isLoading: archivedLoading, refetch: refetchArchived } = useQuery({
    queryKey: ['molds', 'archived', debouncedSearch],
    queryFn: () => api.get('/mold/molds-archived', { params: { search: debouncedSearch || undefined, per_page: 20 } }),
    enabled: isArchiveView,
  });

  const _currentData = isArchiveView ? (archivedData?.data?.data ?? []) : (data?.data ?? []);
  const _currentLoading = isArchiveView ? archivedLoading : isLoading;
  const canManage = useAuthStore(s => s.hasPermission(PERMISSIONS.mold.manage));
  const _isSuperAdmin = useAuthStore(s => s.user?.roles?.some((r: { name: string }) => r.name === 'super_admin'));

  const handleSearch = useCallback((val: string) => {
    setDebouncedSearch(val);
    setPage(1);
  }, []);

  return (
    <div className="space-y-4">
      <PageHeader
        title="Molds"
        actions={
          canManage ? (
            <Link
              to="/mold/masters/new"
              className="inline-flex items-center gap-1.5 rounded bg-neutral-900 px-3 py-2 text-sm font-medium text-white hover:bg-neutral-800"
            >
              <Plus size={16} /> New Mold
            </Link>
          ) : undefined
        }
      />

      <div className="flex flex-wrap items-center gap-2">
        <SearchInput
          value={search}
          onChange={setSearch}
          onSearch={handleSearch}
          placeholder="Search molds..."
          className="w-64"
        />
        <select value={status} onChange={e => { setStatus(e.target.value); setPage(1); }} className="rounded border border-neutral-300 px-2 py-2 text-sm focus:ring-1 focus:ring-neutral-400">
          <option value="">All Statuses</option>
          <option value="active">Active</option>
          <option value="under_maintenance">Under Maintenance</option>
          <option value="retired">Retired</option>
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
              <th className="px-4 py-3 text-left">Cavities</th>
              <th className="px-4 py-3 text-left">Shots</th>
              <th className="px-4 py-3 text-left">Status</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-neutral-100">
            {isLoading ? (
              <tr><td colSpan={5} className="px-4 py-8 text-center text-neutral-400">Loading…</td></tr>
            ) : (data?.data ?? []).length === 0 ? (
              <tr>
                <td colSpan={5} className="px-4 py-8 text-center text-neutral-400">
                  <Settings size={32} className="mx-auto mb-2 opacity-30" />
                  No molds found.
                </td>
              </tr>
            ) : (
              (data?.data ?? []).map(m => {
                const shotPct = m.max_shots ? Math.min(100, (m.current_shots / m.max_shots) * 100) : null;
                return (
                  <tr key={m.ulid} className="even:bg-neutral-100 hover:bg-neutral-50">
                    <td className="px-4 py-3 font-mono text-xs">{m.mold_code}</td>
                    <td className="px-4 py-3">
                      <Link to={`/mold/masters/${m.ulid}`} className="font-medium text-neutral-900 hover:text-neutral-700">
                        {m.name}
                      </Link>
                      {m.is_critical && (
                        <span className="ml-2 rounded bg-neutral-100 px-1.5 py-0.5 text-xs font-medium text-neutral-700">Critical</span>
                      )}
                    </td>
                    <td className="px-4 py-3 text-neutral-500">{m.cavity_count}</td>
                    <td className="px-4 py-3">
                      <div className="flex items-center gap-2">
                        <span className="text-neutral-700">{m.current_shots.toLocaleString()}</span>
                        {shotPct !== null && (
                          <div className="h-2 w-24 overflow-hidden rounded bg-neutral-200">
                            <div
                              className={`h-full rounded ${shotPct >= 90 ? 'bg-neutral-700' : shotPct >= 70 ? 'bg-neutral-500' : 'bg-neutral-400'}`}
                              style={{ width: `${shotPct}%` }}
                            />
                          </div>
                        )}
                      </div>
                    </td>
                    <td className="px-4 py-3">
                      {m.deleted_at && <span className="rounded px-2 py-0.5 text-xs font-medium bg-neutral-100 text-neutral-700 mr-1">Archived</span>}
                      <span className={`rounded px-2 py-0.5 text-xs font-medium ${STATUS_COLORS[m.status]}`}>
                        {m.status?.replace('_', ' ') || 'Unknown'}
                      </span>
                    </td>
                  </tr>
                );
              })
            )}
          </tbody>
        </table>
      </div>

      {data?.meta && <Pagination meta={data.meta} onPageChange={setPage} />}
    </div>
  );
}
