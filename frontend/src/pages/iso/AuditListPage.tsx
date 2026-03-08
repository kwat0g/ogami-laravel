import { useState } from 'react';
import { Link } from 'react-router-dom';
import { Plus, Search } from 'lucide-react';
import { useAudits } from '@/hooks/useISO';
import type { AuditStatus } from '@/types/iso';

const STATUS_COLORS: Record<AuditStatus, string> = {
  planned: 'bg-neutral-100 text-neutral-600',
  in_progress: 'bg-neutral-100 text-neutral-700',
  completed: 'bg-neutral-100 text-neutral-700',
  closed: 'bg-neutral-200 text-neutral-500',
};

export default function AuditListPage() {
  const [status, setStatus] = useState('');
  const [withArchived, setWithArchived] = useState(false);
  const { data, isLoading } = useAudits({ ...(status ? { status } : {}), ...(withArchived ? { with_archived: true } : {}) });

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h1 className="text-lg font-semibold text-neutral-900 mb-6">Internal Audits</h1>
        <Link
          to="/iso/audits/new"
          className="inline-flex items-center gap-1.5 rounded bg-neutral-900 px-3 py-2 text-sm font-medium text-white hover:bg-neutral-800"
        >
          <Plus size={16} /> Schedule Audit
        </Link>
      </div>

      <div className="flex gap-2">
        <select value={status} onChange={e => setStatus(e.target.value)} className="rounded border border-neutral-300 px-2 py-1.5 text-sm">
          <option value="">All Statuses</option>
          <option value="planned">Planned</option>
          <option value="in_progress">In Progress</option>
          <option value="completed">Completed</option>
          <option value="closed">Closed</option>
        </select>
        <label className="flex items-center gap-2 text-sm text-neutral-600 cursor-pointer select-none">
          <input type="checkbox" checked={withArchived} onChange={(e) => setWithArchived(e.target.checked)} className="rounded border-neutral-300 text-neutral-900" />
          <span>Show Archived</span>
        </label>
      </div>

      <div className="overflow-hidden rounded border border-neutral-200 bg-white">
        <table className="w-full text-sm">
          <thead className="bg-neutral-50 text-xs font-medium text-neutral-500">
            <tr>
              <th className="px-4 py-3 text-left">Reference</th>
              <th className="px-4 py-3 text-left">Standard</th>
              <th className="px-4 py-3 text-left">Lead Auditor</th>
              <th className="px-4 py-3 text-left">Date</th>
              <th className="px-4 py-3 text-left">Status</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-neutral-100">
            {isLoading ? (
              <tr><td colSpan={5} className="px-4 py-8 text-center text-neutral-400">Loading…</td></tr>
            ) : (data?.data ?? []).length === 0 ? (
              <tr>
                <td colSpan={5} className="px-4 py-8 text-center text-neutral-400">
                  <Search size={32} className="mx-auto mb-2 opacity-30" />
                  No audits found.
                </td>
              </tr>
            ) : (
              (data?.data ?? []).map(a => (
                <tr key={a.ulid} className="even:bg-neutral-100 hover:bg-neutral-50">
                  <td className="px-4 py-3 font-mono text-xs">
                    <Link to={`/iso/audits/${a.ulid}`} className="text-neutral-900 hover:underline">
                      {a.audit_reference}
                    </Link>
                  </td>
                  <td className="px-4 py-3 text-neutral-600">{a.standard}</td>
                  <td className="px-4 py-3 text-neutral-500">{a.lead_auditor?.name ?? '—'}</td>
                  <td className="px-4 py-3 text-neutral-500">{a.audit_date}</td>
                  <td className="px-4 py-3">
                    {a.deleted_at && <span className="rounded px-2 py-0.5 text-xs font-medium bg-neutral-100 text-neutral-700 mr-1">Archived</span>}
                    <span className={`rounded px-2 py-0.5 text-xs font-medium ${STATUS_COLORS[a.status]}`}>
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
