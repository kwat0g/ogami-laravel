import { useState } from 'react'
import { Link, useNavigate, useSearchParams } from 'react-router-dom'
import { Plus, Briefcase, Users, Calendar, BarChart3 } from 'lucide-react'
import { useAuthStore } from '@/stores/authStore'
import api from '@/lib/api'
import { PageHeader } from '@/components/ui/PageHeader'
import { Card, CardHeader, CardBody } from '@/components/ui/Card'
import StatusBadge from '@/components/ui/StatusBadge'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import Pagination from '@/components/ui/Pagination'
import RecruitmentKpiCards from '@/components/recruitment/RecruitmentKpiCards'
import PipelineFunnelChart from '@/components/recruitment/PipelineFunnelChart'
import {
  useRecruitmentDashboard,
  useRequisitions,
  usePostings,
  useApplications,
  useInterviews,
  useTimeToFillReport,
  useSourceMixReport,
} from '@/hooks/useRecruitment'

type Tab = 'dashboard' | 'requisitions' | 'postings' | 'applications' | 'interviews' | 'reports'

const TABS: { key: Tab; label: string; icon: typeof Briefcase; permission?: string }[] = [
  { key: 'dashboard', label: 'Dashboard', icon: BarChart3 },
  { key: 'postings', label: 'Postings', icon: Briefcase, permission: 'recruitment.postings.view' },
  { key: 'applications', label: 'Applicants', icon: Users, permission: 'recruitment.applications.view' },
  { key: 'interviews', label: 'Interviews', icon: Calendar, permission: 'recruitment.interviews.view' },
  { key: 'reports', label: 'Reports', icon: BarChart3, permission: 'recruitment.reports.view' },
]

type DepartmentLike = string | { name?: string | null } | null | undefined

function formatDepartmentName(department: DepartmentLike): string {
  if (!department) return 'N/A'
  return typeof department === 'string' ? department : (department.name ?? 'N/A')
}

export default function RecruitmentPage() {
  const [searchParams, setSearchParams] = useSearchParams()
  const activeTab = (searchParams.get('tab') as Tab) || 'dashboard'
  const postingUlidFilter = searchParams.get('posting_ulid') ?? ''
  const { hasPermission } = useAuthStore()

  const setTab = (tab: Tab) => {
    setSearchParams((prev) => {
      const next = new URLSearchParams(prev)
      next.set('tab', tab)
      if (tab !== 'applications') {
        next.delete('posting_ulid')
      }
      return next
    })
  }

  const visibleTabs = TABS.filter(
    (t) => !t.permission || hasPermission(t.permission) || hasPermission('hr.full_access')
  )
  const safeActiveTab = visibleTabs.some((tab) => tab.key === activeTab) ? activeTab : 'dashboard'

  return (
    <div>
      <PageHeader
        title="Recruitment"
        subtitle="Manage the full hiring lifecycle"
        actions={
          hasPermission('recruitment.postings.create') || hasPermission('hr.full_access') ? (
            <Link
              to="/hr/recruitment/postings/new"
              className="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-neutral-900 dark:bg-neutral-100 dark:text-neutral-900 rounded-lg hover:bg-neutral-800 dark:hover:bg-neutral-200 transition-colors"
            >
              <Plus className="w-4 h-4" />
              New Job Posting
            </Link>
          ) : undefined
        }
      />

      {/* Tab Navigation */}
      <div className="border-b border-neutral-200 dark:border-neutral-800 mb-6 -mt-2">
        <nav className="flex gap-0 overflow-x-auto" aria-label="Recruitment tabs">
          {visibleTabs.map((tab) => {
            const Icon = tab.icon
            const isActive = safeActiveTab === tab.key
            return (
              <button
                key={tab.key}
                onClick={() => setTab(tab.key)}
                className={`flex items-center gap-2 px-4 py-3 text-sm font-medium border-b-2 transition-colors whitespace-nowrap ${
                  isActive
                    ? 'border-neutral-900 dark:border-neutral-100 text-neutral-900 dark:text-neutral-100'
                    : 'border-transparent text-neutral-500 dark:text-neutral-400 hover:text-neutral-700 dark:hover:text-neutral-300 hover:border-neutral-300'
                }`}
              >
                <Icon className="w-4 h-4" />
                {tab.label}
              </button>
            )
          })}
        </nav>
      </div>

      {/* Tab Content */}
      {safeActiveTab === 'dashboard' && <DashboardTab />}
      {safeActiveTab === 'postings' && <PostingsTab />}
      {safeActiveTab === 'applications' && <ApplicationsTab postingUlidFilter={postingUlidFilter} />}
      {safeActiveTab === 'interviews' && <InterviewsTab />}
      {safeActiveTab === 'reports' && <ReportsTab />}
    </div>
  )
}

// ── Dashboard Tab ────────────────────────────────────────────────────────────

function DashboardTab() {
  const { data, isLoading } = useRecruitmentDashboard()

  if (isLoading) return <SkeletonLoader rows={6} />
  if (!data) return <p className="text-sm text-neutral-400">Failed to load dashboard.</p>

  return (
    <div className="space-y-6">
      <RecruitmentKpiCards kpis={data.kpis} />

      <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <Card>
          <CardHeader>Pipeline Funnel</CardHeader>
          <CardBody>
            <PipelineFunnelChart data={data.pipeline_funnel} />
          </CardBody>
        </Card>

        <Card>
          <CardHeader>Applications by Source</CardHeader>
          <CardBody>
            <div className="space-y-2">
              {/* eslint-disable-next-line @typescript-eslint/no-explicit-any */}
              {data.source_mix.map((s: any) => (
                <div key={s.source} className="flex items-center justify-between text-sm">
                  <span className="text-neutral-600 dark:text-neutral-400">{s.label}</span>
                  <span className="font-medium text-neutral-900 dark:text-neutral-100">{s.count}</span>
                </div>
              ))}
              {data.source_mix.length === 0 && (
                <p className="text-sm text-neutral-400">No application data yet.</p>
              )}
            </div>
          </CardBody>
        </Card>
      </div>

      <div className="grid grid-cols-1 gap-6">
        <Card>
          <CardHeader>Upcoming Interviews</CardHeader>
          <CardBody className="p-0">
            <div className="divide-y divide-neutral-100 dark:divide-neutral-800">
              {/* eslint-disable-next-line @typescript-eslint/no-explicit-any */}
              {data.upcoming_interviews.map((i: any) => (
                <div key={i.id} className="flex items-center justify-between px-5 py-3">
                  <div>
                    <p className="text-sm font-medium text-neutral-900 dark:text-neutral-100">{i.candidate_name}</p>
                    <p className="text-xs text-neutral-500">{i.position} - R{i.round} ({i.type})</p>
                  </div>
                  <div className="text-right">
                    <p className="text-sm text-neutral-700 dark:text-neutral-300">{new Date(i.scheduled_at).toLocaleDateString()}</p>
                    <p className="text-xs text-neutral-400">with {i.interviewer}</p>
                  </div>
                </div>
              ))}
              {data.upcoming_interviews.length === 0 && (
                <p className="px-5 py-4 text-sm text-neutral-400">No upcoming interviews.</p>
              )}
            </div>
          </CardBody>
        </Card>
      </div>
    </div>
  )
}

// ── Requisitions Tab ─────────────────────────────────────────────────────────

function RequisitionsTab() {
  const navigate = useNavigate()
  const [search, setSearch] = useState('')
  const [status, setStatus] = useState('')
  const [page, setPage] = useState(1)
  const { data, isLoading } = useRequisitions({
    ...(search && { search }),
    ...(status && { status }),
    page: String(page),
  })

  if (isLoading) return <SkeletonLoader rows={8} />

  return (
    <Card>
      <CardHeader action={
        <div className="flex items-center gap-3">
          <input
            type="text"
            placeholder="Search..."
            value={search}
            onChange={(e) => { setSearch(e.target.value); setPage(1) }}
            className="px-3 py-1.5 text-sm border border-neutral-200 dark:border-neutral-700 rounded-lg bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 focus:outline-none focus:ring-1 focus:ring-neutral-400"
          />
          <select
            value={status}
            onChange={(e) => { setStatus(e.target.value); setPage(1) }}
            className="px-3 py-1.5 text-sm border border-neutral-200 dark:border-neutral-700 rounded-lg bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100"
          >
            <option value="">All Statuses</option>
            <option value="draft">Draft</option>
            <option value="pending_approval">Pending Approval</option>
            <option value="approved">Approved</option>
            <option value="open">Open</option>
            <option value="closed">Closed</option>
            <option value="cancelled">Cancelled</option>
          </select>
        </div>
      }>
        Requisitions
      </CardHeader>
      <div className="overflow-x-auto">
        <table className="w-full text-sm">
          <thead>
            <tr className="border-b border-neutral-100 dark:border-neutral-800">
              <th className="px-5 py-3 text-left text-xs font-medium text-neutral-500 uppercase">Req #</th>
              <th className="px-5 py-3 text-left text-xs font-medium text-neutral-500 uppercase">Position</th>
              <th className="px-5 py-3 text-left text-xs font-medium text-neutral-500 uppercase">Department</th>
              <th className="px-5 py-3 text-left text-xs font-medium text-neutral-500 uppercase">HC</th>
              <th className="px-5 py-3 text-left text-xs font-medium text-neutral-500 uppercase">Status</th>
              <th className="px-5 py-3 text-left text-xs font-medium text-neutral-500 uppercase">Requested By</th>
              <th className="px-5 py-3 text-left text-xs font-medium text-neutral-500 uppercase">Date</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-neutral-50 dark:divide-neutral-800">
            {/* eslint-disable-next-line @typescript-eslint/no-explicit-any */}
            {data?.data?.map((req: any) => (
              <tr key={req.ulid} onClick={() => navigate(`/hr/recruitment/requisitions/${req.ulid}`)} className="hover:bg-neutral-50 dark:hover:bg-neutral-800/50 cursor-pointer transition-colors">
                <td className="px-5 py-3 font-medium text-neutral-900 dark:text-neutral-100">{req.requisition_number}</td>
                <td className="px-5 py-3 text-neutral-700 dark:text-neutral-300">{req.position?.title}</td>
                <td className="px-5 py-3 text-neutral-500">{req.department?.name}</td>
                <td className="px-5 py-3 text-neutral-500">{req.headcount}</td>
                <td className="px-5 py-3"><StatusBadge status={req.status}>{req.status_label}</StatusBadge></td>
                <td className="px-5 py-3 text-neutral-500">{req.requester?.name}</td>
                <td className="px-5 py-3 text-neutral-400">{req.created_at ? new Date(req.created_at).toLocaleDateString() : ''}</td>
              </tr>
            ))}
          </tbody>
        </table>
        {(!data?.data || data.data.length === 0) && (
          <div className="px-5 py-12 text-center text-sm text-neutral-400">No requisitions found.</div>
        )}
      </div>
      {data?.meta && data.meta.last_page > 1 && (
        <div className="px-5 py-3 border-t border-neutral-100 dark:border-neutral-800">
          <Pagination meta={data.meta} onPageChange={setPage} />
        </div>
      )}
    </Card>
  )
}

// ── Postings Tab ─────────────────────────────────────────────────────────────

function PostingsTab() {
  const navigate = useNavigate()
  const [search, setSearch] = useState('')
  const [status, setStatus] = useState('')
  const { data, isLoading } = usePostings({
    ...(search && { search }),
    ...(status && { status }),
  })

  if (isLoading) return <SkeletonLoader rows={6} />

  return (
    <Card>
      <CardHeader action={
        <div className="flex items-center gap-3">
          <input type="text" placeholder="Search..." value={search} onChange={(e) => setSearch(e.target.value)}
            className="px-3 py-1.5 text-sm border border-neutral-200 dark:border-neutral-700 rounded-lg bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 focus:outline-none focus:ring-1 focus:ring-neutral-400" />
          <select value={status} onChange={(e) => setStatus(e.target.value)}
            className="px-3 py-1.5 text-sm border border-neutral-200 dark:border-neutral-700 rounded-lg bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100">
            <option value="">All Statuses</option>
            <option value="draft">Draft</option>
            <option value="published">Published</option>
            <option value="closed">Closed</option>
            <option value="expired">Expired</option>
          </select>
        </div>
      }>
        Job Postings
      </CardHeader>
      <div className="overflow-x-auto">
        <table className="w-full text-sm">
          <thead>
            <tr className="border-b border-neutral-100 dark:border-neutral-800">
              <th className="px-5 py-3 text-left text-xs font-medium text-neutral-500 uppercase">Posting #</th>
              <th className="px-5 py-3 text-left text-xs font-medium text-neutral-500 uppercase">Title</th>
              <th className="px-5 py-3 text-left text-xs font-medium text-neutral-500 uppercase">Department</th>
              <th className="px-5 py-3 text-left text-xs font-medium text-neutral-500 uppercase">Salary Grade</th>
              <th className="px-5 py-3 text-left text-xs font-medium text-neutral-500 uppercase">Location</th>
              <th className="px-5 py-3 text-left text-xs font-medium text-neutral-500 uppercase">Status</th>
              <th className="px-5 py-3 text-left text-xs font-medium text-neutral-500 uppercase">Published</th>
              <th className="px-5 py-3 text-left text-xs font-medium text-neutral-500 uppercase">Closes</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-neutral-50 dark:divide-neutral-800">
            {/* eslint-disable-next-line @typescript-eslint/no-explicit-any */}
            {data?.data?.map((p: any) => (
              <tr key={p.ulid} onClick={() => navigate(`/hr/recruitment/postings/${p.ulid}`)} className="hover:bg-neutral-50 dark:hover:bg-neutral-800/50 cursor-pointer transition-colors">
                <td className="px-5 py-3 font-medium text-neutral-900 dark:text-neutral-100">{p.posting_number}</td>
                <td className="px-5 py-3 text-neutral-700 dark:text-neutral-300">{p.title}</td>
                <td className="px-5 py-3 text-neutral-500">{formatDepartmentName(p.requisition?.department ?? p.department)}</td>
                <td className="px-5 py-3 text-neutral-500">
                  {p.salary_grade
                    ? `SG ${p.salary_grade.level ?? '*'} - ${p.salary_grade.name ?? p.salary_grade.code}`
                    : '-'}
                </td>
                <td className="px-5 py-3 text-neutral-500">{p.location ?? '-'}</td>
                <td className="px-5 py-3"><StatusBadge status={p.status}>{p.status_label}</StatusBadge></td>
                <td className="px-5 py-3 text-neutral-400">{p.published_at ? new Date(p.published_at).toLocaleDateString() : '-'}</td>
                <td className="px-5 py-3 text-neutral-400">{p.closes_at ? new Date(p.closes_at).toLocaleDateString() : '-'}</td>
              </tr>
            ))}
          </tbody>
        </table>
        {(!data?.data || data.data.length === 0) && (
          <div className="px-5 py-12 text-center text-sm text-neutral-400">No job postings found.</div>
        )}
      </div>
    </Card>
  )
}

// ── Applications Tab ─────────────────────────────────────────────────────────

function ApplicationsTab({ postingUlidFilter }: { postingUlidFilter: string }) {
  const navigate = useNavigate()
  const [search, setSearch] = useState('')
  const [status, setStatus] = useState('')
  const [page, setPage] = useState(1)
  const [selected, setSelected] = useState<string[]>([])
  const { data, isLoading } = useApplications({
    ...(postingUlidFilter && { job_posting_ulid: postingUlidFilter }),
    ...(search && { search }),
    ...(status && { status }),
    page: String(page),
  })

  const toggleSelect = (ulid: string) => {
    setSelected((prev) => prev.includes(ulid) ? prev.filter((s) => s !== ulid) : [...prev, ulid])
  }

  const toggleAll = () => {
    if (!data?.data) return
    const allUlids = data.data.map((a: any) => a.ulid)
    setSelected((prev) => prev.length === allUlids.length ? [] : allUlids)
  }

  const handleBulkReject = async () => {
    if (!confirm(`Reject ${selected.length} application(s)? This cannot be undone.`)) return
    const reason = prompt('Enter rejection reason:')
    if (!reason?.trim()) return
    for (const ulid of selected) {
      try {
        await api.post(`/recruitment/applications/${ulid}/reject`, { reason })
      } catch { /* continue */ }
    }
    setSelected([])
    // Force refetch by changing a dep
    setPage((p) => p)
  }

  if (isLoading) return <SkeletonLoader rows={6} />

  return (
    <Card>
      {/* GAP-31: Bulk actions bar */}
      {selected.length > 0 && (
        <div className="px-5 py-2 bg-neutral-50 dark:bg-neutral-800 border-b border-neutral-200 dark:border-neutral-700 flex items-center gap-3">
          <span className="text-sm font-medium text-neutral-700 dark:text-neutral-300">{selected.length} selected</span>
          <button onClick={handleBulkReject}
            className="px-3 py-1 text-xs font-medium text-white bg-red-600 rounded hover:bg-red-700">
            Bulk Reject
          </button>
          <button onClick={() => setSelected([])}
            className="px-3 py-1 text-xs font-medium text-neutral-600 dark:text-neutral-400 hover:text-neutral-900">
            Clear
          </button>
        </div>
      )}
      <CardHeader action={
        <div className="flex items-center gap-3">
          <input type="text" placeholder="Search by name..." value={search} onChange={(e) => setSearch(e.target.value)}
            className="px-3 py-1.5 text-sm border border-neutral-200 dark:border-neutral-700 rounded-lg bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 focus:outline-none focus:ring-1 focus:ring-neutral-400" />
          <select value={status} onChange={(e) => setStatus(e.target.value)}
            className="px-3 py-1.5 text-sm border border-neutral-200 dark:border-neutral-700 rounded-lg bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100">
            <option value="">All Statuses</option>
            <option value="new">New</option>
            <option value="under_review">Under Review</option>
            <option value="shortlisted">Shortlisted</option>
            <option value="rejected">Rejected</option>
            <option value="withdrawn">Withdrawn</option>
          </select>
          <Link
            to="/hr/recruitment/applications/new"
            className="inline-flex items-center gap-1 px-3 py-1.5 text-sm font-medium text-white bg-neutral-900 dark:bg-neutral-100 dark:text-neutral-900 rounded-lg hover:bg-neutral-800 dark:hover:bg-neutral-200 transition-colors whitespace-nowrap"
          >
            <Plus className="w-3.5 h-3.5" />
            New Application
          </Link>
        </div>
      }>
        Applications
      </CardHeader>
      <div className="overflow-x-auto">
        <table className="w-full text-sm">
          <thead>
            <tr className="border-b border-neutral-100 dark:border-neutral-800">
              <th className="px-3 py-3 w-8"><input type="checkbox" onChange={toggleAll} checked={selected.length > 0 && selected.length === (data?.data?.length ?? 0)} className="rounded border-neutral-300" /></th>
              <th className="px-5 py-3 text-left text-xs font-medium text-neutral-500 uppercase">App #</th>
              <th className="px-5 py-3 text-left text-xs font-medium text-neutral-500 uppercase">Candidate</th>
              <th className="px-5 py-3 text-left text-xs font-medium text-neutral-500 uppercase">Position</th>
              <th className="px-5 py-3 text-left text-xs font-medium text-neutral-500 uppercase">Source</th>
              <th className="px-5 py-3 text-left text-xs font-medium text-neutral-500 uppercase">Status</th>
              <th className="px-5 py-3 text-left text-xs font-medium text-neutral-500 uppercase">Applied</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-neutral-50 dark:divide-neutral-800">
            {/* eslint-disable-next-line @typescript-eslint/no-explicit-any */}
            {data?.data?.map((app: any) => (
              <tr key={app.ulid} onClick={() => navigate(`/hr/recruitment/applications/${app.ulid}`)} className="hover:bg-neutral-50 dark:hover:bg-neutral-800/50 cursor-pointer transition-colors">
                <td className="px-3 py-3 w-8" onClick={(e) => e.stopPropagation()}><input type="checkbox" checked={selected.includes(app.ulid)} onChange={() => toggleSelect(app.ulid)} className="rounded border-neutral-300" /></td>
                <td className="px-5 py-3 font-medium text-neutral-900 dark:text-neutral-100">{app.application_number}</td>
                <td className="px-5 py-3 text-neutral-700 dark:text-neutral-300">{app.candidate?.full_name}</td>
                <td className="px-5 py-3 text-neutral-500">{app.posting?.position}</td>
                <td className="px-5 py-3 text-neutral-500">{app.source_label}</td>
                <td className="px-5 py-3"><StatusBadge status={app.status}>{app.status_label}</StatusBadge></td>
                <td className="px-5 py-3 text-neutral-400">{app.application_date}</td>
              </tr>
            ))}
          </tbody>
        </table>
        {(!data?.data || data.data.length === 0) && (
          <div className="px-5 py-12 text-center text-sm text-neutral-400">No applications found.</div>
        )}
      </div>
      {data?.meta && data.meta.last_page > 1 && (
        <div className="px-5 py-3 border-t border-neutral-100 dark:border-neutral-800">
          <Pagination meta={data.meta} onPageChange={setPage} />
        </div>
      )}
    </Card>
  )
}

// ── Interviews Tab ───────────────────────────────────────────────────────────

function InterviewsTab() {
  const navigate = useNavigate()
  const [status, setStatus] = useState('')
  const { data, isLoading } = useInterviews({
    ...(status && { status }),
  })
  const interviews = data?.data ?? []

  if (isLoading) return <SkeletonLoader rows={6} />

  return (
    <Card>
      <CardHeader action={
        <select value={status} onChange={(e) => setStatus(e.target.value)}
          className="px-3 py-1.5 text-sm border border-neutral-200 dark:border-neutral-700 rounded-lg bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100">
          <option value="">All Statuses</option>
          <option value="scheduled">Scheduled</option>
          <option value="completed">Completed</option>
          <option value="cancelled">Cancelled</option>
          <option value="no_show">No Show</option>
        </select>
      }>
        Interviews
      </CardHeader>
      <div className="overflow-x-auto">
        <table className="w-full text-sm">
          <thead>
            <tr className="border-b border-neutral-100 dark:border-neutral-800">
              <th className="px-5 py-3 text-left text-xs font-medium text-neutral-500 uppercase">Candidate</th>
              <th className="px-5 py-3 text-left text-xs font-medium text-neutral-500 uppercase">Position</th>
              <th className="px-5 py-3 text-left text-xs font-medium text-neutral-500 uppercase">Round</th>
              <th className="px-5 py-3 text-left text-xs font-medium text-neutral-500 uppercase">Type</th>
              <th className="px-5 py-3 text-left text-xs font-medium text-neutral-500 uppercase">Scheduled</th>
              <th className="px-5 py-3 text-left text-xs font-medium text-neutral-500 uppercase">Interviewer</th>
              <th className="px-5 py-3 text-left text-xs font-medium text-neutral-500 uppercase">Status</th>
              <th className="px-5 py-3 text-left text-xs font-medium text-neutral-500 uppercase">Score</th>
              <th className="px-5 py-3 text-left text-xs font-medium text-neutral-500 uppercase">Action</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-neutral-50 dark:divide-neutral-800">
            {/* eslint-disable-next-line @typescript-eslint/no-explicit-any */}
            {interviews.map((i: any) => (
              <tr
                key={i.id}
                onClick={() => navigate(`/hr/recruitment/interviews/${i.id}`)}
                className="cursor-pointer hover:bg-neutral-50 dark:hover:bg-neutral-800/50 transition-colors"
              >
                <td className="px-5 py-3 font-medium text-neutral-900 dark:text-neutral-100">
                  <Link to={`/hr/recruitment/interviews/${i.id}`} className="hover:underline">
                    {i.application?.candidate?.full_name ?? 'N/A'}
                  </Link>
                </td>
                <td className="px-5 py-3 text-neutral-500">{i.application?.posting?.requisition?.position?.title ?? i.application?.posting?.position?.title ?? '-'}</td>
                <td className="px-5 py-3 text-neutral-500">R{i.round}</td>
                <td className="px-5 py-3 text-neutral-500">{i.type?.replace(/_/g, ' ')}</td>
                <td className="px-5 py-3 text-neutral-400">{i.scheduled_at ? new Date(i.scheduled_at).toLocaleString() : '-'}</td>
                <td className="px-5 py-3 text-neutral-500">{i.interviewer?.name ?? '-'}</td>
                <td className="px-5 py-3"><StatusBadge status={i.status}>{i.status?.replace(/_/g, ' ')}</StatusBadge></td>
                <td className="px-5 py-3 text-neutral-500">{i.evaluation?.overall_score ? `${i.evaluation.overall_score}/5` : '-'}</td>
                <td className="px-5 py-3">
                  <Link
                    to={`/hr/recruitment/interviews/${i.id}`}
                    onClick={(e) => e.stopPropagation()}
                    className="inline-flex rounded-md border border-neutral-300 px-3 py-1 text-xs font-semibold text-neutral-700 hover:bg-neutral-50 dark:border-neutral-600 dark:text-neutral-300"
                  >
                    Open
                  </Link>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
        {interviews.length === 0 && (
          <div className="px-5 py-12 text-center text-sm text-neutral-400">No interviews found.</div>
        )}
      </div>
    </Card>
  )
}

// ── Reports Tab ──────────────────────────────────────────────────────────────

function ReportsTab() {
  const { data: ttf, isLoading: ttfLoading } = useTimeToFillReport()
  const { data: source, isLoading: sourceLoading } = useSourceMixReport()

  return (
    <div className="space-y-6">
      <Card>
        <CardHeader>Time to Fill</CardHeader>
        <CardBody>
          {ttfLoading ? <SkeletonLoader rows={3} /> : ttf ? (
            <div>
              <div className="mb-4 p-4 rounded-lg bg-neutral-50 dark:bg-neutral-800">
                <p className="text-sm text-neutral-500">Average Time to Fill</p>
                <p className="text-3xl font-bold text-neutral-900 dark:text-neutral-100">{ttf.average_days} days</p>
              </div>
              {ttf.details?.length > 0 && (
                <table className="w-full text-sm">
                  <thead>
                    <tr className="border-b text-left text-xs text-neutral-500">
                      <th className="py-2 pr-4">Requisition</th>
                      <th className="py-2 pr-4">Department</th>
                      <th className="py-2 pr-4">Position</th>
                      <th className="py-2 text-right">Days</th>
                    </tr>
                  </thead>
                  <tbody>
                    {/* eslint-disable-next-line @typescript-eslint/no-explicit-any */}
                    {ttf.details.map((row: any, i: number) => (
                      <tr key={i} className="border-b border-neutral-50 dark:border-neutral-800">
                        <td className="py-2 pr-4 font-medium text-neutral-900 dark:text-neutral-100">{row.requisition_number}</td>
                        <td className="py-2 pr-4 text-neutral-500">{row.department}</td>
                        <td className="py-2 pr-4 text-neutral-500">{row.position}</td>
                        <td className="py-2 text-right font-bold text-neutral-900 dark:text-neutral-100">{row.days_to_fill}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              )}
            </div>
          ) : <p className="text-sm text-neutral-400">No hiring data available.</p>}
        </CardBody>
      </Card>

      <Card>
        <CardHeader>Applications by Source</CardHeader>
        <CardBody>
          {sourceLoading ? <SkeletonLoader rows={3} /> : source && Array.isArray(source) && source.length > 0 ? (
            <div className="space-y-2">
              {/* eslint-disable-next-line @typescript-eslint/no-explicit-any */}
              {source.map((row: any) => {
                // eslint-disable-next-line @typescript-eslint/no-explicit-any
                const maxCount = Math.max(...source.map((s: any) => s.count), 1)
                const width = Math.max((row.count / maxCount) * 100, 5)
                return (
                  <div key={row.source} className="flex items-center gap-3">
                    <span className="w-20 text-sm text-neutral-600 dark:text-neutral-400 text-right capitalize">{row.source?.replace(/_/g, ' ')}</span>
                    <div className="flex-1 h-6 bg-neutral-100 dark:bg-neutral-800 rounded overflow-hidden">
                      <div className="h-full bg-neutral-600 dark:bg-neutral-400 rounded flex items-center justify-end pr-2 transition-all" style={{ width: `${width}%` }}>
                        <span className="text-xs font-bold text-white dark:text-neutral-900">{row.count}</span>
                      </div>
                    </div>
                  </div>
                )
              })}
            </div>
          ) : <p className="text-sm text-neutral-400">No application data available.</p>}
        </CardBody>
      </Card>
    </div>
  )
}
