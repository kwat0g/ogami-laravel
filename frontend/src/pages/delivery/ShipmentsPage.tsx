import { useState } from 'react';
import { Package, AlertTriangle } from 'lucide-react';
import { useShipments } from '@/hooks/useDelivery';
import type { ShipmentStatus } from '@/types/delivery';

const STATUS_COLORS: Record<ShipmentStatus, string> = {
  pending:    'bg-gray-100 text-gray-600',
  in_transit: 'bg-blue-100 text-blue-700',
  delivered:  'bg-green-100 text-green-700',
  returned:   'bg-red-100 text-red-500',
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
      <div className="flex items-center gap-3">
        <div className="w-10 h-10 bg-blue-100 rounded-xl flex items-center justify-center">
          <Package className="w-5 h-5 text-blue-600" />
        </div>
        <div>
          <h1 className="text-2xl font-bold text-gray-900">Shipments</h1>
          <p className="text-sm text-gray-500 mt-0.5">Track outbound shipments and delivery status</p>
        </div>
      </div>

      <div className="flex gap-2">
        <select
          value={status}
          onChange={(e) => setStatus(e.target.value)}
          className="text-sm border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white"
        >
          <option value="">All Statuses</option>
          <option value="pending">Pending</option>
          <option value="in_transit">In Transit</option>
          <option value="delivered">Delivered</option>
          <option value="returned">Returned</option>
        </select>
      </div>

      {isLoading && (
        <div className="bg-white border border-gray-200 rounded-xl p-8 text-center text-gray-400 text-sm">
          Loading shipments…
        </div>
      )}

      {isError && (
        <div className="flex items-center gap-2 text-red-600 text-sm">
          <AlertTriangle className="w-4 h-4" /> Failed to load shipments.
        </div>
      )}

      {!isLoading && !isError && (
        <div className="overflow-hidden rounded-xl border border-gray-200 bg-white">
          <table className="w-full text-sm">
            <thead className="bg-gray-50 border-b border-gray-200">
              <tr>
                {['Reference', 'Carrier', 'Tracking #', 'Shipped', 'Est. Arrival', 'Actual Arrival', 'Status'].map((h) => (
                  <th key={h} className="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">
                    {h}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-100">
              {(data?.data ?? []).length === 0 ? (
                <tr>
                  <td colSpan={7} className="px-4 py-10 text-center text-gray-400">
                    <Package size={32} className="mx-auto mb-2 opacity-30" />
                    No shipments found.
                  </td>
                </tr>
              ) : (
                (data?.data ?? []).map((shipment) => (
                  <tr key={shipment.ulid} className="hover:bg-gray-50">
                    <td className="px-4 py-3 font-mono text-xs font-medium text-blue-700">
                      {shipment.shipment_reference}
                    </td>
                    <td className="px-4 py-3 text-gray-700">
                      {shipment.carrier ?? <span className="text-gray-400">—</span>}
                    </td>
                    <td className="px-4 py-3 font-mono text-xs text-gray-600">
                      {shipment.tracking_number ?? <span className="text-gray-400">—</span>}
                    </td>
                    <td className="px-4 py-3 text-gray-600 text-xs">
                      {shipment.shipped_at ? shipment.shipped_at.slice(0, 10) : <span className="text-gray-400">—</span>}
                    </td>
                    <td className="px-4 py-3 text-gray-600 text-xs">
                      {shipment.estimated_arrival ? shipment.estimated_arrival.slice(0, 10) : <span className="text-gray-400">—</span>}
                    </td>
                    <td className="px-4 py-3 text-gray-600 text-xs">
                      {shipment.actual_arrival
                        ? <span className="text-green-700 font-medium">{shipment.actual_arrival.slice(0, 10)}</span>
                        : <span className="text-gray-400">—</span>}
                    </td>
                    <td className="px-4 py-3">
                      <span className={`inline-flex px-2 py-0.5 rounded-full text-xs font-medium ${STATUS_COLORS[shipment.status]}`}>
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
