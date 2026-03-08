import { useState } from 'react';
import { Link } from 'react-router-dom';
import { Plus, Settings } from 'lucide-react';
import { useMolds } from '@/hooks/useMold';
import type { MoldStatus } from '@/types/mold';

const STATUS_COLORS: Record<MoldStatus, string> = {
  active: 'bg-neutral-100 text-neutral-700',
  under_maintenance: 'bg-neutral-100 text-neutral-700',
  retired: 'bg-neutral-100 text-neutral-500',
};

export default function MoldListPage() {
  const [status, setStatus] = useState('');
  const [withArchived, setWithArchived] = useState(false);
  const { data, isLoading } = useMolds({ ...(status ? { status } : {}), ...(withArchived ? { with_archived: true } : {}) });

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h1 className="text-lg font-semibold text-neutral-900 mb-6">Mold Masters</h1>
        <Link
          to="/mold/masters/new"
          className="inline-flex items-center gap-1.5 rounded bg-neutral-900 px-3 py-2 text-sm font-medium text-white hover:bg-neutral-800"
        >
          <Plus size={16} /> New Mold
        </Link>
      </div>

      <div className="flex gap-2">
        <select value={status} onChange={e => setStatus(e.target.value)} className="rounded border border-neutral-300 px-2 py-1.5 text-sm focus:ring-1 focus:ring-neutral-400">
          <option value="">All Statuses</option>
          <option value="active">Active</option>
          <option value="under_maintenance">Under Maintenance</option>
          <option value="retired">Retired</option>
        </select>
        <label className="flex items-center gap-2 text-sm text-neutral-600 cursor-pointer select-none">
          <input type="checkbox" checked={withArchived} onChange={(e) => setWithArchived(e.target.checked)} className="rounded border-neutral-300 text-neutral-600" />
          <span>Show Archived</span>
        </label>
      </div>

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
    </div>
  );
}
