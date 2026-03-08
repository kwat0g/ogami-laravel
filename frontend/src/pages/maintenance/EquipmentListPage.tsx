import { useState } from 'react';
import { Link } from 'react-router-dom';
import { Plus, Wrench } from 'lucide-react';
import { PageHeader } from '@/components/ui/PageHeader';
import { useEquipment } from '@/hooks/useMaintenance';
import type { EquipmentStatus } from '@/types/maintenance';

const STATUS_COLORS: Record<EquipmentStatus, string> = {
  operational: 'bg-neutral-100 text-neutral-700',
  under_maintenance: 'bg-neutral-100 text-neutral-700',
  decommissioned: 'bg-neutral-100 text-neutral-500',
};

export default function EquipmentListPage() {
  const [status, setStatus] = useState('');
  const [withArchived, setWithArchived] = useState(false);
  const { data, isLoading } = useEquipment({ ...(status ? { status } : {}), ...(withArchived ? { with_archived: true } : {}) });

  return (
    <div className="space-y-4">
      <PageHeader
        title="Equipment"
        actions={
          <Link
            to="/maintenance/equipment/new"
            className="inline-flex items-center gap-1.5 rounded bg-neutral-900 px-3 py-2 text-sm font-medium text-white hover:bg-neutral-800"
          >
            <Plus size={16} /> Add Equipment
          </Link>
        }
      />

      <div className="flex gap-2">
        <select
          value={status}
          onChange={e => setStatus(e.target.value)}
          className="rounded border border-neutral-300 px-2 py-1.5 text-sm focus:ring-1 focus:ring-neutral-400"
        >
          <option value="">All Statuses</option>
          <option value="operational">Operational</option>
          <option value="under_maintenance">Under Maintenance</option>
          <option value="decommissioned">Decommissioned</option>
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
                    <span className={`rounded px-2 py-0.5 text-xs font-medium ${STATUS_COLORS[eq.status] || STATUS_COLORS.active}`}>
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
    </div>
  );
}
