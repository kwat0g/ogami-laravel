import { useLeadScores, useAutoQualifyLeads } from '@/hooks/useEnhancements'
import type { LeadScore } from '@/hooks/useEnhancements'

export default function LeadScoringPage() {
  const { data: leads, isLoading } = useLeadScores()
  const autoQualify = useAutoQualifyLeads()

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-gray-900 dark:text-white">Lead Scoring</h1>
          <p className="text-sm text-gray-500 mt-1">Ranked leads by engagement, source quality, and profile completeness</p>
        </div>
        <button
          onClick={() => autoQualify.mutate()}
          disabled={autoQualify.isPending}
          className="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 disabled:opacity-50"
        >
          {autoQualify.isPending ? 'Qualifying...' : 'Auto-Qualify Leads'}
        </button>
      </div>

      {autoQualify.isSuccess && (
        <div className="bg-green-50 border border-green-200 rounded-lg p-3 text-green-700 text-sm">
          {autoQualify.data?.qualified_count ?? 0} lead(s) auto-qualified above threshold.
        </div>
      )}

      <div className="grid gap-4">
        {isLoading ? (
          <div className="text-center py-8 text-gray-500">Loading lead scores...</div>
        ) : (leads ?? []).map((lead: LeadScore) => (
          <div key={lead.lead_id} className={`bg-white dark:bg-gray-800 rounded-lg shadow p-4 border-l-4 ${lead.qualified ? 'border-green-500' : lead.score >= 50 ? 'border-yellow-500' : 'border-gray-300'}`}>
            <div className="flex items-center justify-between">
              <div>
                <h3 className="font-medium text-gray-900 dark:text-white">{lead.company_name}</h3>
                <p className="text-sm text-gray-500">{lead.contact_name} | Status: {lead.status}</p>
              </div>
              <div className="text-right">
                <div className="text-2xl font-bold">{lead.score}</div>
                <span className={`text-xs px-2 py-0.5 rounded-full ${lead.qualified ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600'}`}>
                  {lead.qualified ? 'Qualified' : 'Not Qualified'}
                </span>
              </div>
            </div>
            <div className="mt-3 grid grid-cols-4 gap-2">
              {Object.entries(lead.breakdown).map(([key, val]) => (
                <div key={key} className="text-center">
                  <div className="text-xs text-gray-500 capitalize">{key}</div>
                  <div className="text-sm font-medium">{val.points}/{val.max}</div>
                  <div className="w-full bg-gray-200 rounded-full h-1.5 mt-1">
                    <div className="bg-indigo-500 h-1.5 rounded-full" style={{ width: `${(val.points / val.max) * 100}%` }} />
                  </div>
                </div>
              ))}
            </div>
          </div>
        ))}
      </div>
    </div>
  )
}
