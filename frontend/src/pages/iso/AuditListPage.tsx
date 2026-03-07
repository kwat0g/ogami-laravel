import { useState } from 'react';
import { Link } from 'react-router-dom';
import { Plus, Search } from 'lucide-react';
import { useAudits } from '@/hooks/useISO';
import type { AuditStatus } from '@/types/iso';

const STATUS_COLORS: Record<AuditStatus, string> = {
  planned: 'bg-gray-100 text-gray-600',
  in_progress: 'bg-blue-100 text-blue-700',
  completed: 'bg-green-100 text-green-700',
  closed: 'bg-gray-200 text-gray-500',
};

export default function AuditListPage() {
  const [status, setStatus] = useState('');
  const [withArchived, setWithArchived] = useState(false);
  const { data, isLoading } = useAudits({ ...(status ? { status } : {}), ...(withArchived ? { with_archived: true } : {}) });

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-semibold">Internal Audits</h1>
        <Link
          to="/iso/audits/new"
          className="inline-flex items-center gap-1.5 rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700"
        >
          <Plus size={16} /> Schedule Audit
        </Link>
      </div>

      <div className="flex gap-2">
        <select value={status} onChange={e => setStatus(e.target.value)} className="rounded border border-gray-300 px-2 py-1.5 text-sm">
          <option value="">All Statuses</option>
          <option value="planned">Planned</option>
          <option value="in_progress">In Progress</option>
          <option value="completed">Completed</option>
          <option value="closed">Closed</option>
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
              <th className="px-4 py-3 text-left">Standard</th>
              <th className="px-4 py-3 text-left">Lead Auditor</th>
              <th className="px-4 py-3 text-left">Date</th>
              <th className="px-4 py-3 text-left">Status</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-gray-100">
            {isLoading ? (
              <tr><td colSpan={5} className="px-4 py-8 text-center text-gray-400">Loading…</td></tr>
            ) : (data?.data ?? []).length === 0 ? (
              <tr>
                <td colSpan={5} className="px-4 py-8 text-center text-gray-400">
                  <Search size={32} className="mx-auto mb-2 opacity-30" />
                  No audits found.
                </td>
              </tr>
            ) : (
              (data?.data ?? []).map(a => (
                <tr key={a.ulid} className="hover:bg-gray-50">
                  <td className="px-4 py-3 font-mono text-xs">
                    <Link to={`/iso/audits/${a.ulid}`} className="text-indigo-600 hover:underline">
                      {a.audit_reference}
                    </Link>
                  </td>
                  <td className="px-4 py-3 text-gray-600">{a.standard}</td>
                  <td className="px-4 py-3 text-gray-500">{a.lead_auditor?.name ?? '—'}</td>
                  <td className="px-4 py-3 text-gray-500">{a.audit_date}</td>
                  <td className="px-4 py-3">
                    {a.deleted_at && <span className="rounded-full px-2 py-0.5 text-xs font-medium bg-orange-100 text-orange-700 mr-1">Archived</span>}
                    <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${STATUS_COLORS[a.status]}`}>
                      {a.status.replace('_', ' ')}
                    </span>
                  </td>
                </tr>
              ))
            )}
          </tbody>
        </table>
      </div>
    </div>
  );
}
