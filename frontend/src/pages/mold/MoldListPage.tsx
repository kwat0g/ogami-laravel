import { useState } from 'react';
import { Link } from 'react-router-dom';
import { Plus, Settings } from 'lucide-react';
import { useMolds } from '@/hooks/useMold';
import type { MoldStatus } from '@/types/mold';

const STATUS_COLORS: Record<MoldStatus, string> = {
  active: 'bg-green-100 text-green-700',
  under_maintenance: 'bg-yellow-100 text-yellow-700',
  retired: 'bg-gray-100 text-gray-500',
};

export default function MoldListPage() {
  const [status, setStatus] = useState('');
  const { data, isLoading } = useMolds(status ? { status } : undefined);

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-semibold">Mold Masters</h1>
        <Link
          to="/mold/masters/new"
          className="inline-flex items-center gap-1.5 rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700"
        >
          <Plus size={16} /> New Mold
        </Link>
      </div>

      <div className="flex gap-2">
        <select value={status} onChange={e => setStatus(e.target.value)} className="rounded border border-gray-300 px-2 py-1.5 text-sm">
          <option value="">All Statuses</option>
          <option value="active">Active</option>
          <option value="under_maintenance">Under Maintenance</option>
          <option value="retired">Retired</option>
        </select>
      </div>

      <div className="overflow-hidden rounded-lg border border-gray-200 bg-white">
        <table className="w-full text-sm">
          <thead className="bg-gray-50 text-xs uppercase text-gray-500">
            <tr>
              <th className="px-4 py-3 text-left">Code</th>
              <th className="px-4 py-3 text-left">Name</th>
              <th className="px-4 py-3 text-left">Cavities</th>
              <th className="px-4 py-3 text-left">Shots</th>
              <th className="px-4 py-3 text-left">Status</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-gray-100">
            {isLoading ? (
              <tr><td colSpan={5} className="px-4 py-8 text-center text-gray-400">Loading…</td></tr>
            ) : (data?.data ?? []).length === 0 ? (
              <tr>
                <td colSpan={5} className="px-4 py-8 text-center text-gray-400">
                  <Settings size={32} className="mx-auto mb-2 opacity-30" />
                  No molds found.
                </td>
              </tr>
            ) : (
              (data?.data ?? []).map(m => {
                const shotPct = m.max_shots ? Math.min(100, (m.current_shots / m.max_shots) * 100) : null;
                return (
                  <tr key={m.ulid} className="hover:bg-gray-50">
                    <td className="px-4 py-3 font-mono text-xs">{m.mold_code}</td>
                    <td className="px-4 py-3">
                      <Link to={`/mold/masters/${m.ulid}`} className="font-medium text-indigo-600 hover:underline">
                        {m.name}
                      </Link>
                      {m.is_critical && (
                        <span className="ml-2 rounded-full bg-red-100 px-1.5 py-0.5 text-xs font-medium text-red-700">Critical</span>
                      )}
                    </td>
                    <td className="px-4 py-3 text-gray-500">{m.cavity_count}</td>
                    <td className="px-4 py-3">
                      <div className="flex items-center gap-2">
                        <span className="text-gray-700">{m.current_shots.toLocaleString()}</span>
                        {shotPct !== null && (
                          <div className="h-2 w-24 overflow-hidden rounded-full bg-gray-200">
                            <div
                              className={`h-full rounded-full ${shotPct >= 90 ? 'bg-red-500' : shotPct >= 70 ? 'bg-yellow-400' : 'bg-green-500'}`}
                              style={{ width: `${shotPct}%` }}
                            />
                          </div>
                        )}
                      </div>
                    </td>
                    <td className="px-4 py-3">
                      <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${STATUS_COLORS[m.status]}`}>
                        {m.status.replace('_', ' ')}
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
