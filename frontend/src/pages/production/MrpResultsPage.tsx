import { useState } from 'react'
import { PageHeader } from '@/components/ui/PageHeader'
import { useMrpSummary, useMrpTimePhased, type MrpSummaryItem } from '@/hooks/useProduction'

export default function MrpResultsPage() {
  const [tab, setTab] = useState<'summary' | 'time-phased'>('summary')
  const { data: summary, isLoading: summaryLoading } = useMrpSummary()
  const { data: timePhased, isLoading: tpLoading } = useMrpTimePhased()

  const actionColors: Record<string, string> = {
    'order': 'bg-red-100 text-red-700',
    'reorder': 'bg-orange-100 text-orange-700',
    'sufficient': 'bg-green-100 text-green-700',
    'excess': 'bg-blue-100 text-blue-700',
  }

  return (
    <div className="p-6 space-y-6">
      <PageHeader title="Material Requirements Planning (MRP)" />

      <div className="flex gap-2 border-b">
        <button
          onClick={() => setTab('summary')}
          className={`px-4 py-2 text-sm font-medium border-b-2 ${tab === 'summary' ? 'border-blue-600 text-blue-600' : 'border-transparent text-neutral-500 hover:text-neutral-700'}`}
        >
          Requirements Summary
        </button>
        <button
          onClick={() => setTab('time-phased')}
          className={`px-4 py-2 text-sm font-medium border-b-2 ${tab === 'time-phased' ? 'border-blue-600 text-blue-600' : 'border-transparent text-neutral-500 hover:text-neutral-700'}`}
        >
          Time-Phased Plan
        </button>
      </div>

      {tab === 'summary' && (
        summaryLoading ? (
          <div className="animate-pulse space-y-3">{[1,2,3,4].map(i => <div key={i} className="h-12 bg-neutral-200 rounded" />)}</div>
        ) : (
          <div className="bg-white dark:bg-neutral-800 rounded-lg border overflow-hidden">
            <table className="w-full text-sm">
              <thead className="bg-neutral-50 dark:bg-neutral-700">
                <tr>
                  <th className="text-left px-4 py-3 font-medium">Item Code</th>
                  <th className="text-left px-4 py-3 font-medium">Item Name</th>
                  <th className="text-right px-4 py-3 font-medium">Gross Req</th>
                  <th className="text-right px-4 py-3 font-medium">On Hand</th>
                  <th className="text-right px-4 py-3 font-medium">On Order</th>
                  <th className="text-right px-4 py-3 font-medium">Net Req</th>
                  <th className="text-right px-4 py-3 font-medium">Lead Time</th>
                  <th className="text-center px-4 py-3 font-medium">Action</th>
                </tr>
              </thead>
              <tbody className="divide-y">
                {(summary ?? []).map((item: MrpSummaryItem) => (
                  <tr key={item.item_id} className="hover:bg-neutral-50 dark:hover:bg-neutral-700/50">
                    <td className="px-4 py-3 font-mono text-xs">{item.item_code}</td>
                    <td className="px-4 py-3">{item.item_name}</td>
                    <td className="px-4 py-3 text-right">{item.gross_requirement.toLocaleString()}</td>
                    <td className="px-4 py-3 text-right">{item.on_hand.toLocaleString()}</td>
                    <td className="px-4 py-3 text-right">{item.on_order.toLocaleString()}</td>
                    <td className={`px-4 py-3 text-right font-medium ${item.net_requirement > 0 ? 'text-red-600' : 'text-green-600'}`}>
                      {item.net_requirement.toLocaleString()}
                    </td>
                    <td className="px-4 py-3 text-right">{item.lead_time_days ? `${item.lead_time_days}d` : '-'}</td>
                    <td className="px-4 py-3 text-center">
                      <span className={`px-2 py-0.5 rounded-full text-xs ${actionColors[item.suggested_action] ?? 'bg-neutral-100 text-neutral-600'}`}>
                        {item.suggested_action}
                      </span>
                    </td>
                  </tr>
                ))}
                {(!summary || summary.length === 0) && (
                  <tr><td colSpan={8} className="px-4 py-8 text-center text-neutral-500">No open production orders requiring materials. MRP will show results when orders are scheduled.</td></tr>
                )}
              </tbody>
            </table>
          </div>
        )
      )}

      {tab === 'time-phased' && (
        tpLoading ? (
          <div className="animate-pulse space-y-3">{[1,2,3].map(i => <div key={i} className="h-12 bg-neutral-200 rounded" />)}</div>
        ) : (
          <div className="bg-white dark:bg-neutral-800 rounded-lg border p-5">
            {Array.isArray(timePhased) && timePhased.length > 0 ? (
              <pre className="text-xs overflow-auto max-h-96">{JSON.stringify(timePhased, null, 2)}</pre>
            ) : (
              <p className="text-neutral-500 text-center py-8">No time-phased data available. Schedule production orders to generate the time-phased plan.</p>
            )}
          </div>
        )
      )}
    </div>
  )
}
