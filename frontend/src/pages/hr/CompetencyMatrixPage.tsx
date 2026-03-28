import { useState } from 'react'
import { Target } from 'lucide-react'
import { useQuery } from '@tanstack/react-query'
import api from '@/lib/api'
import { PageHeader } from '@/components/ui/PageHeader'
import { Card } from '@/components/ui/Card'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import EmptyState from '@/components/ui/EmptyState'

export default function CompetencyMatrixPage() {
  const [gapsOnly, setGapsOnly] = useState(false)
  const { data, isLoading } = useQuery({
    queryKey: ['competency-matrix', gapsOnly],
    queryFn: async () => { const { data } = await api.get('/hr/competency', { params: { gaps_only: gapsOnly || undefined, per_page: 100 } }); return data },
  })
  const items = data?.data ?? []

  return (
    <div className="space-y-6">
      <PageHeader title="Employee Skill Gap Analysis" icon={<Target className="w-5 h-5 text-neutral-600" />} />
      <Card className="p-4">
        <label className="flex items-center gap-2 text-sm">
          <input type="checkbox" checked={gapsOnly} onChange={e => setGapsOnly(e.target.checked)} />
          Show gaps only (current &lt; required)
        </label>
      </Card>
      {isLoading ? <SkeletonLoader rows={6} /> : items.length === 0 ? <EmptyState title="No competency records" /> : (
        <Card className="overflow-hidden">
          <table className="w-full text-sm">
            <thead className="bg-neutral-50 dark:bg-neutral-800">
              <tr><th className="text-left p-3">Employee ID</th><th className="text-left p-3">Skill</th><th className="text-left p-3">Category</th><th className="text-center p-3">Current</th><th className="text-center p-3">Required</th><th className="text-center p-3">Gap</th></tr>
            </thead>
            <tbody className="divide-y">
              {items.map((c: any) => {
                const hasGap = c.current_level < c.required_level
                return (
                  <tr key={c.id} className={hasGap ? 'bg-red-50/50' : ''}>
                    <td className="p-3">{c.employee_id}</td>
                    <td className="p-3 font-medium">{c.skill_name}</td>
                    <td className="p-3">{c.category ?? '-'}</td>
                    <td className="p-3 text-center">{renderLevel(c.current_level)}</td>
                    <td className="p-3 text-center">{renderLevel(c.required_level)}</td>
                    <td className="p-3 text-center">{hasGap ? <span className="text-red-600 font-bold">-{c.required_level - c.current_level}</span> : <span className="text-green-600">OK</span>}</td>
                  </tr>
                )
              })}
            </tbody>
          </table>
        </Card>
      )}
    </div>
  )
}

function renderLevel(level: number) {
  return <span className="font-mono">{'\u2B24'.repeat(level)}<span className="text-neutral-300">{'\u2B24'.repeat(5 - level)}</span></span>
}
