import { useQuarantine, useQuarantineAction } from '@/hooks/useEnhancements'
import type { QuarantineEntry } from '@/hooks/useEnhancements'
import { PageHeader } from '@/components/ui/PageHeader'
import { Card, CardBody } from '@/components/ui/Card'

export default function QuarantineManagementPage() {
  const { data: entries, isLoading } = useQuarantine()
  const action = useQuarantineAction()

  const handleRelease = (entryId: number) => {
    const locationId = prompt('Target location ID:')
    if (!locationId) return
    action.mutate({ entryId, action: 'release', data: { target_location_id: Number(locationId) } })
  }

  const handleReject = (entryId: number, disposition: 'return_to_vendor' | 'scrap') => {
    const remarks = prompt(`Remarks for ${disposition.replace(/_/g, ' ')}:`)
    action.mutate({ entryId, action: 'reject', data: { disposition, remarks } })
  }

  return (
    <div className="space-y-6">
      <PageHeader
        title="QC Quarantine"
        subtitle="Stock pending quality inspection -- not available for production or delivery"
      />

      <Card>
        <CardBody className="p-0">
          <table className="min-w-full divide-y divide-neutral-200 dark:divide-neutral-700">
            <thead className="bg-neutral-50 dark:bg-neutral-900">
              <tr>
                <th className="px-4 py-3 text-left text-xs font-medium text-neutral-500 uppercase">Item</th>
                <th className="px-4 py-3 text-left text-xs font-medium text-neutral-500 uppercase">Quantity</th>
                <th className="px-4 py-3 text-left text-xs font-medium text-neutral-500 uppercase">Reason</th>
                <th className="px-4 py-3 text-left text-xs font-medium text-neutral-500 uppercase">Days</th>
                <th className="px-4 py-3 text-left text-xs font-medium text-neutral-500 uppercase">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-neutral-200 dark:divide-neutral-700">
              {isLoading ? (
                <tr><td colSpan={5} className="px-4 py-8 text-center text-neutral-500">Loading...</td></tr>
              ) : (entries ?? []).length === 0 ? (
                <tr><td colSpan={5} className="px-4 py-8 text-center text-neutral-500">No items in quarantine</td></tr>
              ) : (entries ?? []).map((e: QuarantineEntry) => (
                <tr key={e.quarantine_entry_id} className={e.days_in_quarantine > 7 ? 'bg-red-50 dark:bg-red-900/10' : ''}>
                  <td className="px-4 py-3">
                    <div className="text-sm font-medium">{e.item_code}</div>
                    <div className="text-xs text-neutral-500">{e.item_name}</div>
                  </td>
                  <td className="px-4 py-3 text-sm">{e.quantity}</td>
                  <td className="px-4 py-3 text-sm text-neutral-500 max-w-xs truncate">{e.reason}</td>
                  <td className="px-4 py-3">
                    <span className={`text-sm font-medium ${e.days_in_quarantine > 7 ? 'text-red-600' : e.days_in_quarantine > 3 ? 'text-yellow-600' : 'text-neutral-600'}`}>
                      {e.days_in_quarantine}d
                    </span>
                  </td>
                  <td className="px-4 py-3 space-x-2">
                    <button onClick={() => handleRelease(e.quarantine_entry_id)} className="text-green-600 hover:underline text-xs font-medium">Release</button>
                    <button onClick={() => handleReject(e.quarantine_entry_id, 'return_to_vendor')} className="text-orange-600 hover:underline text-xs">Return</button>
                    <button onClick={() => handleReject(e.quarantine_entry_id, 'scrap')} className="text-red-600 hover:underline text-xs">Scrap</button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </CardBody>
      </Card>
    </div>
  )
}
