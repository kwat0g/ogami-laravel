import { useState, useCallback } from 'react';
import { Link } from 'react-router-dom';
import { Plus, Truck } from 'lucide-react';
import { PageHeader } from '@/components/ui/PageHeader';
import SearchInput from '@/components/ui/SearchInput';
import Pagination from '@/components/ui/Pagination';
import { useDeliveryReceipts } from '@/hooks/useDelivery';
import { useAuthStore } from '@/stores/authStore'
import { useQuery } from '@tanstack/react-query'
import ArchiveToggleButton from '@/components/ui/ArchiveToggleButton'
import ArchiveViewBanner from '@/components/ui/ArchiveViewBanner'
import ArchiveRowActions from '@/components/ui/ArchiveRowActions'
import api from '@/lib/api';
import { ExportButton } from '@/components/ui/ExportButton';
import type { DrDirection, DrStatus } from '@/types/delivery';

const STATUS_COLORS: Record<DrStatus, string> = {
  draft: 'bg-neutral-100 text-neutral-600',
  confirmed: 'bg-neutral-200 text-neutral-800',
  cancelled: 'bg-neutral-100 text-neutral-400',
};

const DIRECTION_COLORS: Record<DrDirection, string> = {
  inbound: 'bg-neutral-100 text-neutral-700',
  outbound: 'bg-neutral-100 text-neutral-700',
};

export default function DeliveryReceiptListPage() {
  const [direction, setDirection] = useState('');
  const [status, setStatus] = useState('');
  const [isArchiveView, setIsArchiveView] = useState(false);
  const [search, setSearch] = useState('');
  const [debouncedSearch, setDebouncedSearch] = useState('');
  const [page, setPage] = useState(1);
  const canManage = useAuthStore(s => s.hasPermission('delivery.manage'));

  const params: Record<string, string | number | boolean> = { page, per_page: 20 };
  if (direction) params.direction = direction;
  if (status) params.status = status;
  // Archive view handled by separate query
  if (debouncedSearch) params.search = debouncedSearch;

  const { data, isLoading } = useDeliveryReceipts(Object.keys(params).length ? params : undefined);

  const handleSearch = useCallback((val: string) => {
    setDebouncedSearch(val);
    setPage(1);
  }, []);

  return (
    <div className="space-y-4">
      <PageHeader
        title="Delivery Receipts"
        actions={
          <div className="flex items-center gap-2">
            <ExportButton
              data={data?.data ?? []}
              columns={[
                { key: 'dr_number', label: 'DR Number' },
                { key: 'direction', label: 'Direction' },
                { key: 'status', label: 'Status' },
                { key: 'customer.name', label: 'Customer' },
                { key: 'delivery_date', label: 'Delivery Date' },
              ]}
              filename="delivery-receipts"
            />
            {canManage && (
              <Link
                to="/delivery/receipts/new"
                className="inline-flex items-center gap-1.5 rounded bg-neutral-900 px-3 py-2 text-sm font-medium text-white hover:bg-neutral-800"
              >
                <Plus size={16} /> New Receipt
              </Link>
            )}
          </div>
        }
      />

      <div className="flex flex-wrap items-center gap-2">
        <SearchInput
          value={search}
          onChange={setSearch}
          onSearch={handleSearch}
          placeholder="Search receipts..."
          className="w-64"
        />
        <select value={direction} onChange={e => { setDirection(e.target.value); setPage(1); }} className="rounded border border-neutral-300 px-2 py-2 text-sm bg-white focus:ring-1 focus:ring-neutral-400 focus:outline-none">
          <option value="">All Directions</option>
          <option value="inbound">Inbound</option>
          <option value="outbound">Outbound</option>
        </select>
        <select value={status} onChange={e => { setStatus(e.target.value); setPage(1); }} className="rounded border border-neutral-300 px-2 py-2 text-sm bg-white focus:ring-1 focus:ring-neutral-400 focus:outline-none">
          <option value="">All Statuses</option>
          <option value="draft">Draft</option>
          <option value="confirmed">Confirmed</option>
          <option value="cancelled">Cancelled</option>
        </select>
        <ArchiveToggleButton isArchiveView={isArchiveView} onToggle={() => setIsArchiveView(prev => !prev)} />
      </div>

      <div className="overflow-hidden rounded border border-neutral-200 bg-white">
        <table className="w-full text-sm">
          <thead className="bg-neutral-50 text-xs text-neutral-600">
            <tr>
              <th className="px-4 py-3 text-left font-medium">Reference</th>
              <th className="px-4 py-3 text-left font-medium">Direction</th>
              <th className="px-4 py-3 text-left font-medium">Party</th>
              <th className="px-4 py-3 text-left font-medium">Date</th>
              <th className="px-4 py-3 text-left font-medium">Status</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-neutral-100">
            {isLoading ? (
              <tr><td colSpan={5} className="px-4 py-8 text-center text-neutral-400">Loading…</td></tr>
            ) : (data?.data ?? []).length === 0 ? (
              <tr>
                <td colSpan={5} className="px-4 py-8 text-center text-neutral-400">
                  <Truck size={32} className="mx-auto mb-2 opacity-30" />
                  No delivery receipts found.
                </td>
              </tr>
            ) : (
              (data?.data ?? []).map(dr => (
                <tr key={dr.ulid} className="even:bg-neutral-100 hover:bg-neutral-50">
                  <td className="px-4 py-3 font-mono text-xs">
                    <Link to={`/delivery/receipts/${dr.ulid}`} className="text-neutral-900 hover:underline">
                      {dr.dr_reference}
                    </Link>
                  </td>
                  <td className="px-4 py-3">
                    <span className={`rounded px-2 py-0.5 text-xs font-medium ${DIRECTION_COLORS[dr.direction]}`}>
                      {dr.direction}
                    </span>
                  </td>
                  <td className="px-4 py-3 text-neutral-600">
                    {dr.vendor?.name ?? dr.customer?.name ?? '—'}
                  </td>
                  <td className="px-4 py-3 text-neutral-500">{dr.receipt_date}</td>
                  <td className="px-4 py-3">
                    {dr.deleted_at && <span className="rounded px-2 py-0.5 text-xs font-medium bg-neutral-100 text-neutral-500 mr-1">Archived</span>}
                    <span className={`rounded px-2 py-0.5 text-xs font-medium ${STATUS_COLORS[dr.status]}`}>
                      {dr.status}
                    </span>
                  </td>
                </tr>
              ))
            )}
          </tbody>
        </table>
      </div>

      {data?.meta && <Pagination meta={data.meta} onPageChange={setPage} />}
    </div>
  );
}
