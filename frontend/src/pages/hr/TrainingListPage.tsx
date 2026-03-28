import { useState } from 'react'
import { GraduationCap } from 'lucide-react'
import { useQuery } from '@tanstack/react-query'
import api from '@/lib/api'
import { PageHeader } from '@/components/ui/PageHeader'
import { Card } from '@/components/ui/Card'
import StatusBadge from '@/components/ui/StatusBadge'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import EmptyState from '@/components/ui/EmptyState'

export default function TrainingListPage() {
  const [status, setStatus] = useState('')
  const { data, isLoading } = useQuery({
    queryKey: ['trainings', status],
    queryFn: async () => { const { data } = await api.get('/hr/trainings', { params: { status: status || undefined } }); return data },
  })
  const trainings = data?.data ?? []

  return (
    <div className="space-y-6">
      <PageHeader title="Employee Training Programs" icon={<GraduationCap className="w-5 h-5 text-neutral-600" />} />
      <Card className="p-4">
        <select className="input-sm" value={status} onChange={e => setStatus(e.target.value)}>
          <option value="">All Statuses</option>
          {['scheduled','in_progress','completed','cancelled'].map(s => <option key={s} value={s}>{s.replace('_', ' ')}</option>)}
        </select>
      </Card>
      {isLoading ? <SkeletonLoader rows={5} /> : trainings.length === 0 ? <EmptyState title="No trainings" /> : (
        <Card className="overflow-hidden">
          <table className="w-full text-sm">
            <thead className="bg-neutral-50 dark:bg-neutral-800">
              <tr><th className="text-left p-3">Title</th><th className="text-left p-3">Type</th><th className="text-left p-3">Provider</th><th className="text-left p-3">Date</th><th className="text-left p-3">Status</th></tr>
            </thead>
            <tbody className="divide-y">
              {trainings.map((t: any) => (
                <tr key={t.id} className="hover:bg-neutral-50 dark:hover:bg-neutral-800/50">
                  <td className="p-3 font-medium">{t.title}</td>
                  <td className="p-3 capitalize">{t.type?.replace('_', ' ')}</td>
                  <td className="p-3">{t.provider ?? '-'}</td>
                  <td className="p-3">{new Date(t.start_date).toLocaleDateString()}</td>
                  <td className="p-3"><StatusBadge status={t.status} /></td>
                </tr>
              ))}
            </tbody>
          </table>
        </Card>
      )}
    </div>
  )
}
