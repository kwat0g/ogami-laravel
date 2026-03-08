import { useState } from 'react';
import { Package, AlertTriangle } from 'lucide-react';
import { useShipments } from '@/hooks/useDelivery';
import type { ShipmentStatus } from '@/types/delivery';

const STATUS_COLORS: Record<ShipmentStatus, string> = {
  pending:    'bg-neutral-100 text-neutral-600',
  in_transit: 'bg-neutral-100 text-neutral-700',
  delivered:  'bg-neutral-200 text-neutral-800',
  returned:   'bg-neutral-100 text-neutral-400',
};

const STATUS_LABELS: Record<ShipmentStatus, string> = {
  pending:    'Pending',
  in_transit: 'In Transit',
  delivered:  'Delivered',
  returned:   'Returned',
};

export default function ShipmentsPage() {
  const [status, setStatus] = useState('');

  const params: Record<string, string> = {};
  if (status) params.status = status;

  const { data, isLoading, isError } = useShipments(Object.keys(params).length ? params : undefined);

  return (
    <div className="space-y-4">
      <h1 className="text-lg font-semibold text-neutral-900 mb-6">Shipments</h1>

      <div className="flex gap-2">
        <select
          value={status}
          onChange={(e) => setStatus(e.target.value)}
          className="text-sm border border-neutral-300 rounded px-3 py-2 focus:ring-1 focus:ring-neutral-400 focus:outline-none bg-white"
        >
          <option value="">All Statuses</option>
          <option value="pending">Pending</option>
          <option value="in_transit">In Transit</option>
          <option value="delivered">Delivered</option>
          <option value="returned">Returned</option>
        </select>
      </div>

      {isLoading && (
        <div className="bg-white border border-neutral-200 rounded p-8 text-center text-neutral-400 text-sm">
          Loading shipments…
        </div>
      )}

      {isError && (
        <div className="flex items-center gap-2 text-red-600 text-sm">
          <AlertTriangle className="w-4 h-4" /> Failed to load shipments.
        </div>
      )}

      {!isLoading && !isError && (
        <div className="overflow-hidden rounded border border-neutral-200 bg-white">
          <table className="w-full text-sm">
            <thead className="bg-neutral-50 border-b border-neutral-200">
              <tr>
                {['Reference', 'Carrier', 'Tracking #', 'Shipped', 'Est. Arrival', 'Actual Arrival', 'Status'].map((h) => (
                  <th key={h} className="px-4 py-3 text-left text-xs font-medium text-neutral-600">
                    {h}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody className="divide-y divide-neutral-100">
              {(data?.data ?? []).length === 0 ? (
                <tr>
                  <td colSpan={7} className="px-4 py-10 text-center text-neutral-400">
                    <Package size={32} className="mx-auto mb-2 opacity-30" />
                    No shipments found.
                  </td>
                </tr>
              ) : (
                (data?.data ?? []).map((shipment) => (
                  <tr key={shipment.ulid} className="even:bg-neutral-100 hover:bg-neutral-50">
                    <td className="px-4 py-3 font-mono text-xs font-medium text-neutral-900">
                      {shipment.shipment_reference}
                    </td>
                    <td className="px-4 py-3 text-neutral-700">
                      {shipment.carrier ?? <span className="text-neutral-400">—</span>}
                    </td>
                    <td className="px-4 py-3 font-mono text-xs text-neutral-600">
                      {shipment.tracking_number ?? <span className="text-neutral-400">—</span>}
                    </td>
                    <td className="px-4 py-3 text-neutral-600 text-xs">
                      {shipment.shipped_at ? shipment.shipped_at.slice(0, 10) : <span className="text-neutral-400">—</span>}
                    </td>
                    <td className="px-4 py-3 text-neutral-600 text-xs">
                      {shipment.estimated_arrival ? shipment.estimated_arrival.slice(0, 10) : <span className="text-neutral-400">—</span>}
                    </td>
                    <td className="px-4 py-3 text-neutral-600 text-xs">
                      {shipment.actual_arrival
                        ? <span className="text-neutral-900 font-medium">{shipment.actual_arrival.slice(0, 10)}</span>
                        : <span className="text-neutral-400">—</span>}
                    </td>
                    <td className="px-4 py-3">
                      <span className={`inline-flex px-2 py-0.5 rounded text-xs font-medium ${STATUS_COLORS[shipment.status]}`}>
                        {STATUS_LABELS[shipment.status]}
                      </span>
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
      )}
    </div>
  );
}
