import { useState } from 'react';
import { Link } from 'react-router-dom';
import { Plus, Wrench } from 'lucide-react';
import { useEquipment } from '@/hooks/useMaintenance';
import type { EquipmentStatus } from '@/types/maintenance';

const STATUS_COLORS: Record<EquipmentStatus, string> = {
  operational: 'bg-green-100 text-green-700',
  under_maintenance: 'bg-yellow-100 text-yellow-700',
  decommissioned: 'bg-gray-100 text-gray-500',
};

export default function EquipmentListPage() {
  const [status, setStatus] = useState('');
  const { data, isLoading } = useEquipment(status ? { status } : undefined);

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-semibold">Equipment</h1>
        <Link
          to="/maintenance/equipment/new"
          className="inline-flex items-center gap-1.5 rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700"
        >
          <Plus size={16} /> Add Equipment
        </Link>
      </div>

      <div className="flex gap-2">
        <select
          value={status}
          onChange={e => setStatus(e.target.value)}
          className="rounded border border-gray-300 px-2 py-1.5 text-sm"
        >
          <option value="">All Statuses</option>
          <option value="operational">Operational</option>
          <option value="under_maintenance">Under Maintenance</option>
          <option value="decommissioned">Decommissioned</option>
        </select>
      </div>

      <div className="overflow-hidden rounded-lg border border-gray-200 bg-white">
        <table className="w-full text-sm">
          <thead className="bg-gray-50 text-xs uppercase text-gray-500">
            <tr>
              <th className="px-4 py-3 text-left">Code</th>
              <th className="px-4 py-3 text-left">Name</th>
              <th className="px-4 py-3 text-left">Category</th>
              <th className="px-4 py-3 text-left">Location</th>
              <th className="px-4 py-3 text-left">Status</th>
              <th className="px-4 py-3 text-left">WOs</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-gray-100">
            {isLoading ? (
              <tr>
                <td colSpan={6} className="px-4 py-8 text-center text-gray-400">Loading…</td>
              </tr>
            ) : (data?.data ?? []).length === 0 ? (
              <tr>
                <td colSpan={6} className="px-4 py-8 text-center text-gray-400">
                  <Wrench size={32} className="mx-auto mb-2 opacity-30" />
                  No equipment found.
                </td>
              </tr>
            ) : (
              (data?.data ?? []).map(eq => (
                <tr key={eq.ulid} className="hover:bg-gray-50">
                  <td className="px-4 py-3 font-mono text-xs">{eq.equipment_code}</td>
                  <td className="px-4 py-3">
                    <Link to={`/maintenance/equipment/${eq.ulid}`} className="font-medium text-indigo-600 hover:underline">
                      {eq.name}
                    </Link>
                  </td>
                  <td className="px-4 py-3 text-gray-500">{eq.category ?? '—'}</td>
                  <td className="px-4 py-3 text-gray-500">{eq.location ?? '—'}</td>
                  <td className="px-4 py-3">
                    <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${STATUS_COLORS[eq.status]}`}>
                      {eq.status.replace('_', ' ')}
                    </span>
                  </td>
                  <td className="px-4 py-3 text-gray-500">{eq.work_orders_count ?? 0}</td>
                </tr>
              ))
            )}
          </tbody>
        </table>
      </div>
    </div>
  );
}
