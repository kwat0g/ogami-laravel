import { useState } from 'react'
import { Link, useNavigate, useSearchParams } from 'react-router-dom'
import { Plus, Briefcase, Users, Calendar, FileText, UserCheck, BarChart3 } from 'lucide-react'
import { useAuthStore } from '@/stores/authStore'
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
  useOffers,
  useCandidates,
  usePipelineReport,
  useTimeToFillReport,
  useSourceMixReport,
} from '@/hooks/useRecruitment'

type Tab = 'dashboard' | 'requisitions' | 'postings' | 'applications' | 'interviews' | 'offers' | 'candidates' | 'reports'

const TABS: { key: Tab; label: string; icon: typeof Briefcase; permission?: string }[] = [
  { key: 'dashboard', label: 'Dashboard', icon: BarChart3 },
  { key: 'requisitions', label: 'Requisitions', icon: FileText, permission: 'recruitment.requisitions.view' },
  { key: 'postings', label: 'Postings', icon: Briefcase, permission: 'recruitment.postings.view' },
  { key: 'applications', label: 'Applications', icon: Users, permission: 'recruitment.applications.view' },
  { key: 'interviews', label: 'Interviews', icon: Calendar, permission: 'recruitment.interviews.view' },
  { key: 'offers', label: 'Offers', icon: FileText, permission: 'recruitment.offers.view' },
  { key: 'candidates', label: 'Candidates', icon: UserCheck, permission: 'recruitment.candidates.view' },
  { key: 'reports', label: 'Reports', icon: BarChart3, permission: 'recruitment.reports.view' },
]

export default function RecruitmentPage() {
  const [searchParams, setSearchParams] = useSearchParams()
  const activeTab = (searchParams.get('tab') as Tab) || 'dashboard'
  const { hasPermission } = useAuthStore()

  const setTab = (tab: Tab) => setSearchParams({ tab })

  const visibleTabs = TABS.filter(
    (t) => !t.permission || hasPermission(t.permission) || hasPermission('hr.full_access')
  )

  return (
    <div>
      <PageHeader
        title="Recruitment"
        subtitle="Manage the full hiring lifecycle"
        actions={
          hasPermission('recruitment.requisitions.create') || hasPermission('hr.full_access') ? (
            <Link
              to="/hr/recruitment/requisitions/new"
              className="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-neutral-900 dark:bg-neutral-100 dark:text-neutral-900 rounded-lg hover:bg-neutral-800 dark:hover:bg-neutral-200 transition-colors"
            >
              <Plus className="w-4 h-4" />
              New Requisition
            </Link>
          ) : undefined
        }
      />

      {/* Tab Navigation */}
      <div className="border-b border-neutral-200 dark:border-neutral-800 mb-6 -mt-2">
        <nav className="flex gap-0 overflow-x-auto" aria-label="Recruitment tabs">
          {visibleTabs.map((tab) => {
            const Icon = tab.icon
            const isActive = activeTab === tab.key
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
      {activeTab === 'dashboard' && <DashboardTab />}
      {activeTab === 'requisitions' && <RequisitionsTab />}
      {activeTab === 'postings' && <PostingsTab />}
      {activeTab === 'applications' && <ApplicationsTab />}
      {activeTab === 'interviews' && <InterviewsTab />}
      {activeTab === 'offers' && <OffersTab />}
      {activeTab === 'candidates' && <CandidatesTab />}
      {activeTab === 'reports' && <ReportsTab />}
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

      <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <Card>
          <CardHeader>Recent Requisitions</CardHeader>
          <CardBody className="p-0">
            <div className="divide-y divide-neutral-100 dark:divide-neutral-800">
              {data.recent_requisitions.map((r: any) => (
                <Link key={r.ulid} to={`/hr/recruitment/requisitions/${r.ulid}`} className="flex items-center justify-between px-5 py-3 hover:bg-neutral-50 dark:hover:bg-neutral-800/50 transition-colors">
                  <div>
                    <p className="text-sm font-medium text-neutral-900 dark:text-neutral-100">{r.position}</p>
                    <p className="text-xs text-neutral-500">{r.department} - {r.requisition_number}</p>
                  </div>
                  <div className="text-right">
                    <StatusBadge status={r.status}>{r.status_label}</StatusBadge>
                    <p className="mt-1 text-xs text-neutral-400">{r.days_open}d open</p>
                  </div>
                </Link>
              ))}
              {data.recent_requisitions.length === 0 && (
                <p className="px-5 py-4 text-sm text-neutral-400">No requisitions yet.</p>
              )}
            </div>
          </CardBody>
        </Card>

        <Card>
          <CardHeader>Upcoming Interviews</CardHeader>
          <CardBody className="p-0">
            <div className="divide-y divide-neutral-100 dark:divide-neutral-800">
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
              <th className="px-5 py-3 text-left text-xs font-medium text-neutral-500 uppercase">Location</th>
              <th className="px-5 py-3 text-left text-xs font-medium text-neutral-500 uppercase">Status</th>
              <th className="px-5 py-3 text-left text-xs font-medium text-neutral-500 uppercase">Published</th>
              <th className="px-5 py-3 text-left text-xs font-medium text-neutral-500 uppercase">Closes</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-neutral-50 dark:divide-neutral-800">
            {data?.data?.map((p: any) => (
              <tr key={p.ulid} onClick={() => navigate(`/hr/recruitment/postings/${p.ulid}`)} className="hover:bg-neutral-50 dark:hover:bg-neutral-800/50 cursor-pointer transition-colors">
                <td className="px-5 py-3 font-medium text-neutral-900 dark:text-neutral-100">{p.posting_number}</td>
                <td className="px-5 py-3 text-neutral-700 dark:text-neutral-300">{p.title}</td>
                <td className="px-5 py-3 text-neutral-500">{p.requisition?.department}</td>
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

function ApplicationsTab() {
  const navigate = useNavigate()
  const [search, setSearch] = useState('')
  const [status, setStatus] = useState('')
  const { data, isLoading } = useApplications({
    ...(search && { search }),
    ...(status && { status }),
  })

  if (isLoading) return <SkeletonLoader rows={6} />

  return (
    <Card>
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
        </div>
      }>
        Applications
      </CardHeader>
      <div className="overflow-x-auto">
        <table className="w-full text-sm">
          <thead>
            <tr className="border-b border-neutral-100 dark:border-neutral-800">
              <th className="px-5 py-3 text-left text-xs font-medium text-neutral-500 uppercase">App #</th>
              <th className="px-5 py-3 text-left text-xs font-medium text-neutral-500 uppercase">Candidate</th>
              <th className="px-5 py-3 text-left text-xs font-medium text-neutral-500 uppercase">Position</th>
              <th className="px-5 py-3 text-left text-xs font-medium text-neutral-500 uppercase">Source</th>
              <th className="px-5 py-3 text-left text-xs font-medium text-neutral-500 uppercase">Status</th>
              <th className="px-5 py-3 text-left text-xs font-medium text-neutral-500 uppercase">Applied</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-neutral-50 dark:divide-neutral-800">
            {data?.data?.map((app: any) => (
              <tr key={app.ulid} onClick={() => navigate(`/hr/recruitment/applications/${app.ulid}`)} className="hover:bg-neutral-50 dark:hover:bg-neutral-800/50 cursor-pointer transition-colors">
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
    </Card>
  )
}

// ── Interviews Tab ───────────────────────────────────────────────────────────

function InterviewsTab() {
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
            </tr>
          </thead>
          <tbody className="divide-y divide-neutral-50 dark:divide-neutral-800">
            {interviews.map((i: any) => (
              <tr key={i.id} className="hover:bg-neutral-50 dark:hover:bg-neutral-800/50 transition-colors">
                <td className="px-5 py-3 font-medium text-neutral-900 dark:text-neutral-100">
                  <Link to={`/hr/recruitment/interviews/${i.id}`} className="hover:underline">
                    {i.application?.candidate?.full_name ?? 'N/A'}
                  </Link>
                </td>
                <td className="px-5 py-3 text-neutral-500">{i.application?.posting?.requisition?.position?.title ?? '-'}</td>
                <td className="px-5 py-3 text-neutral-500">R{i.round}</td>
                <td className="px-5 py-3 text-neutral-500">{i.type?.replace(/_/g, ' ')}</td>
                <td className="px-5 py-3 text-neutral-400">{i.scheduled_at ? new Date(i.scheduled_at).toLocaleString() : '-'}</td>
                <td className="px-5 py-3 text-neutral-500">{i.interviewer?.name ?? '-'}</td>
                <td className="px-5 py-3"><StatusBadge status={i.status}>{i.status?.replace(/_/g, ' ')}</StatusBadge></td>
                <td className="px-5 py-3 text-neutral-500">{i.evaluation?.overall_score ? `${i.evaluation.overall_score}/5` : '-'}</td>
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

// ── Offers Tab ───────────────────────────────────────────────────────────────

function OffersTab() {
  const navigate = useNavigate()
  const [status, setStatus] = useState('')
  const { data, isLoading } = useOffers({
    ...(status && { status }),
  })

  if (isLoading) return <SkeletonLoader rows={6} />

  return (
    <Card>
      <CardHeader action={
        <select value={status} onChange={(e) => setStatus(e.target.value)}
          className="px-3 py-1.5 text-sm border border-neutral-200 dark:border-neutral-700 rounded-lg bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100">
          <option value="">All Statuses</option>
          <option value="draft">Draft</option>
          <option value="sent">Sent</option>
          <option value="accepted">Accepted</option>
          <option value="rejected">Rejected</option>
          <option value="expired">Expired</option>
          <option value="withdrawn">Withdrawn</option>
        </select>
      }>
        Job Offers
      </CardHeader>
      <div className="overflow-x-auto">
        <table className="w-full text-sm">
          <thead>
            <tr className="border-b border-neutral-100 dark:border-neutral-800">
              <th className="px-5 py-3 text-left text-xs font-medium text-neutral-500 uppercase">Offer #</th>
              <th className="px-5 py-3 text-left text-xs font-medium text-neutral-500 uppercase">Candidate</th>
              <th className="px-5 py-3 text-left text-xs font-medium text-neutral-500 uppercase">Position</th>
              <th className="px-5 py-3 text-left text-xs font-medium text-neutral-500 uppercase">Salary</th>
              <th className="px-5 py-3 text-left text-xs font-medium text-neutral-500 uppercase">Start Date</th>
              <th className="px-5 py-3 text-left text-xs font-medium text-neutral-500 uppercase">Status</th>
              <th className="px-5 py-3 text-left text-xs font-medium text-neutral-500 uppercase">Expires</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-neutral-50 dark:divide-neutral-800">
            {data?.data?.map((offer: any) => (
              <tr key={offer.ulid} onClick={() => navigate(`/hr/recruitment/offers/${offer.ulid}`)} className="hover:bg-neutral-50 dark:hover:bg-neutral-800/50 cursor-pointer transition-colors">
                <td className="px-5 py-3 font-medium text-neutral-900 dark:text-neutral-100">{offer.offer_number}</td>
                <td className="px-5 py-3 text-neutral-700 dark:text-neutral-300">{offer.application?.candidate?.full_name ?? '-'}</td>
                <td className="px-5 py-3 text-neutral-500">{offer.offered_position?.title ?? '-'}</td>
                <td className="px-5 py-3 text-neutral-500">{(offer.offered_salary / 100).toLocaleString('en-PH', { style: 'currency', currency: 'PHP' })}</td>
                <td className="px-5 py-3 text-neutral-400">{offer.start_date}</td>
                <td className="px-5 py-3"><StatusBadge status={offer.status}>{offer.status_label}</StatusBadge></td>
                <td className="px-5 py-3 text-neutral-400">{offer.expires_at ? new Date(offer.expires_at).toLocaleDateString() : '-'}</td>
              </tr>
            ))}
          </tbody>
        </table>
        {(!data?.data || data.data.length === 0) && (
          <div className="px-5 py-12 text-center text-sm text-neutral-400">No offers found.</div>
        )}
      </div>
    </Card>
  )
}

// ── Candidates Tab ───────────────────────────────────────────────────────────

function CandidatesTab() {
  const navigate = useNavigate()
  const [search, setSearch] = useState('')
  const { data, isLoading } = useCandidates({
    ...(search && { search }),
  })

  if (isLoading) return <SkeletonLoader rows={6} />

  return (
    <Card>
      <CardHeader action={
        <input type="text" placeholder="Search by name or email..." value={search} onChange={(e) => setSearch(e.target.value)}
          className="px-3 py-1.5 text-sm border border-neutral-200 dark:border-neutral-700 rounded-lg bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 focus:outline-none focus:ring-1 focus:ring-neutral-400 w-64" />
      }>
        Candidate Pool
      </CardHeader>
      <div className="overflow-x-auto">
        <table className="w-full text-sm">
          <thead>
            <tr className="border-b border-neutral-100 dark:border-neutral-800">
              <th className="px-5 py-3 text-left text-xs font-medium text-neutral-500 uppercase">Name</th>
              <th className="px-5 py-3 text-left text-xs font-medium text-neutral-500 uppercase">Email</th>
              <th className="px-5 py-3 text-left text-xs font-medium text-neutral-500 uppercase">Phone</th>
              <th className="px-5 py-3 text-left text-xs font-medium text-neutral-500 uppercase">Source</th>
              <th className="px-5 py-3 text-left text-xs font-medium text-neutral-500 uppercase">Added</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-neutral-50 dark:divide-neutral-800">
            {data?.data?.map((c: any) => (
              <tr key={c.id} onClick={() => navigate(`/hr/recruitment/candidates/${c.id}`)} className="hover:bg-neutral-50 dark:hover:bg-neutral-800/50 cursor-pointer transition-colors">
                <td className="px-5 py-3 font-medium text-neutral-900 dark:text-neutral-100">{c.full_name}</td>
                <td className="px-5 py-3 text-neutral-500">{c.email}</td>
                <td className="px-5 py-3 text-neutral-500">{c.phone ?? '-'}</td>
                <td className="px-5 py-3 text-neutral-500">{c.source_label}</td>
                <td className="px-5 py-3 text-neutral-400">{new Date(c.created_at).toLocaleDateString()}</td>
              </tr>
            ))}
          </tbody>
        </table>
        {(!data?.data || data.data.length === 0) && (
          <div className="px-5 py-12 text-center text-sm text-neutral-400">No candidates found.</div>
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
              {source.map((row: any) => {
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
