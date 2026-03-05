import { useState } from 'react';
import { Link } from 'react-router-dom';
import { Plus, Truck } from 'lucide-react';
import { useDeliveryReceipts } from '@/hooks/useDelivery';
import type { DrDirection, DrStatus } from '@/types/delivery';

const STATUS_COLORS: Record<DrStatus, string> = {
  draft: 'bg-gray-100 text-gray-600',
  confirmed: 'bg-green-100 text-green-700',
  cancelled: 'bg-red-100 text-red-500',
};

const DIRECTION_COLORS: Record<DrDirection, string> = {
  inbound: 'bg-blue-100 text-blue-700',
  outbound: 'bg-purple-100 text-purple-700',
};

export default function DeliveryReceiptListPage() {
  const [direction, setDirection] = useState('');
  const [status, setStatus] = useState('');

  const params: Record<string, string> = {};
  if (direction) params.direction = direction;
  if (status) params.status = status;

  const { data, isLoading } = useDeliveryReceipts(Object.keys(params).length ? params : undefined);

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-semibold">Delivery Receipts</h1>
        <Link
          to="/delivery/receipts/new"
          className="inline-flex items-center gap-1.5 rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700"
        >
          <Plus size={16} /> New Receipt
        </Link>
      </div>

      <div className="flex gap-2">
        <select value={direction} onChange={e => setDirection(e.target.value)} className="rounded border border-gray-300 px-2 py-1.5 text-sm">
          <option value="">All Directions</option>
          <option value="inbound">Inbound</option>
          <option value="outbound">Outbound</option>
        </select>
        <select value={status} onChange={e => setStatus(e.target.value)} className="rounded border border-gray-300 px-2 py-1.5 text-sm">
          <option value="">All Statuses</option>
          <option value="draft">Draft</option>
          <option value="confirmed">Confirmed</option>
          <option value="cancelled">Cancelled</option>
        </select>
      </div>

      <div className="overflow-hidden rounded-lg border border-gray-200 bg-white">
        <table className="w-full text-sm">
          <thead className="bg-gray-50 text-xs uppercase text-gray-500">
            <tr>
              <th className="px-4 py-3 text-left">Reference</th>
              <th className="px-4 py-3 text-left">Direction</th>
              <th className="px-4 py-3 text-left">Party</th>
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
                  <Truck size={32} className="mx-auto mb-2 opacity-30" />
                  No delivery receipts found.
                </td>
              </tr>
            ) : (
              (data?.data ?? []).map(dr => (
                <tr key={dr.ulid} className="hover:bg-gray-50">
                  <td className="px-4 py-3 font-mono text-xs">
                    <Link to={`/delivery/receipts/${dr.ulid}`} className="text-indigo-600 hover:underline">
                      {dr.dr_reference}
                    </Link>
                  </td>
                  <td className="px-4 py-3">
                    <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${DIRECTION_COLORS[dr.direction]}`}>
                      {dr.direction}
                    </span>
                  </td>
                  <td className="px-4 py-3 text-gray-600">
                    {dr.vendor?.name ?? dr.customer?.name ?? '—'}
                  </td>
                  <td className="px-4 py-3 text-gray-500">{dr.receipt_date}</td>
                  <td className="px-4 py-3">
                    <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${STATUS_COLORS[dr.status]}`}>
                      {dr.status}
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
