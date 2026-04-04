import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useCreateApplication, usePostings } from '@/hooks/useRecruitment'
import { PageHeader } from '@/components/ui/PageHeader'
import { Card, CardHeader, CardBody } from '@/components/ui/Card'
import FormField from '@/components/ui/FormField'
import { toast } from 'sonner'

type DepartmentLike = string | { name?: string | null } | null | undefined

function formatDepartmentName(department: DepartmentLike): string {
  if (!department) return 'N/A'
  return typeof department === 'string' ? department : (department.name ?? 'N/A')
}

export default function ApplicationFormPage() {
  const navigate = useNavigate()
  const createMutation = useCreateApplication()

  // Fetch published postings for the dropdown
  const { data: postingsData } = usePostings({ status: 'published' })
  const postings = postingsData?.data ?? []

  const [form, setForm] = useState({
    job_posting_id: '',
    first_name: '',
    last_name: '',
    email: '',
    phone: '',
    address: '',
    linkedin_url: '',
    source: 'walk_in',
    cover_letter: '',
  })

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    if (!form.job_posting_id) {
      return
    }

    try {
      await createMutation.mutateAsync({
        job_posting_id: Number(form.job_posting_id),
        candidate: {
          first_name: form.first_name,
          last_name: form.last_name,
          email: form.email,
          phone: form.phone || null,
          address: form.address || null,
          linkedin_url: form.linkedin_url || null,
          source: form.source,
        },
        cover_letter: form.cover_letter || null,
        source: form.source,
      })
      toast.success('Application submitted successfully')
      navigate('/hr/recruitment?tab=applications')
    } catch {
    }
  }

  return (
    <div>
      <PageHeader
        title="New Application"
        subtitle="Manually add a walk-in or referred applicant"
        backTo="/hr/recruitment?tab=applications"
      />

      <form onSubmit={handleSubmit}>
        <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
          {/* Left column - Job Posting & Source */}
          <Card>
            <CardHeader>Job & Source</CardHeader>
            <CardBody className="space-y-4">
              <FormField label="Job Posting" required>
                <select
                  value={form.job_posting_id}
                  onChange={(e) => setForm({ ...form, job_posting_id: e.target.value })}
                  className="w-full px-3 py-2 text-sm border border-neutral-200 dark:border-neutral-700 rounded-lg bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 focus:outline-none focus:ring-1 focus:ring-neutral-400"
                  required
                >
                  <option value="">Select a published posting...</option>
                  {/* eslint-disable-next-line @typescript-eslint/no-explicit-any */}
                  {postings.map((p: any) => (
                    <option key={p.ulid} value={p.id}>
                      {p.posting_number ?? p.title} - {p.title} ({formatDepartmentName(p.requisition?.department)})
                    </option>
                  ))}
                </select>
                {postings.length === 0 && (
                  <p className="mt-1 text-xs text-amber-600">No published postings found. Publish a posting first.</p>
                )}
              </FormField>

              <FormField label="Application Source" required>
                <select
                  value={form.source}
                  onChange={(e) => setForm({ ...form, source: e.target.value })}
                  className="w-full px-3 py-2 text-sm border border-neutral-200 dark:border-neutral-700 rounded-lg bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 focus:outline-none focus:ring-1 focus:ring-neutral-400"
                >
                  <option value="walk_in">Walk-in</option>
                  <option value="referral">Referral</option>
                  <option value="job_board">Job Board</option>
                  <option value="agency">Agency</option>
                  <option value="internal">Internal</option>
                </select>
              </FormField>

              <FormField label="Cover Letter">
                <textarea
                  value={form.cover_letter}
                  onChange={(e) => setForm({ ...form, cover_letter: e.target.value })}
                  className="w-full px-3 py-2 text-sm border border-neutral-200 dark:border-neutral-700 rounded-lg bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 focus:outline-none focus:ring-1 focus:ring-neutral-400 resize-none"
                  rows={4}
                  placeholder="Optional cover letter or notes..."
                />
              </FormField>
            </CardBody>
          </Card>

          {/* Right column - Candidate Info */}
          <Card>
            <CardHeader>Candidate Information</CardHeader>
            <CardBody className="space-y-4">
              <div className="grid grid-cols-2 gap-4">
                <FormField label="First Name" required>
                  <input
                    type="text"
                    value={form.first_name}
                    onChange={(e) => setForm({ ...form, first_name: e.target.value })}
                    className="w-full px-3 py-2 text-sm border border-neutral-200 dark:border-neutral-700 rounded-lg bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 focus:outline-none focus:ring-1 focus:ring-neutral-400"
                    required
                  />
                </FormField>
                <FormField label="Last Name" required>
                  <input
                    type="text"
                    value={form.last_name}
                    onChange={(e) => setForm({ ...form, last_name: e.target.value })}
                    className="w-full px-3 py-2 text-sm border border-neutral-200 dark:border-neutral-700 rounded-lg bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 focus:outline-none focus:ring-1 focus:ring-neutral-400"
                    required
                  />
                </FormField>
              </div>

              <FormField label="Email" required>
                <input
                  type="email"
                  value={form.email}
                  onChange={(e) => setForm({ ...form, email: e.target.value })}
                  className="w-full px-3 py-2 text-sm border border-neutral-200 dark:border-neutral-700 rounded-lg bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 focus:outline-none focus:ring-1 focus:ring-neutral-400"
                  required
                />
              </FormField>

              <FormField label="Phone">
                <input
                  type="tel"
                  value={form.phone}
                  onChange={(e) => setForm({ ...form, phone: e.target.value })}
                  className="w-full px-3 py-2 text-sm border border-neutral-200 dark:border-neutral-700 rounded-lg bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 focus:outline-none focus:ring-1 focus:ring-neutral-400"
                />
              </FormField>

              <FormField label="Address">
                <textarea
                  value={form.address}
                  onChange={(e) => setForm({ ...form, address: e.target.value })}
                  className="w-full px-3 py-2 text-sm border border-neutral-200 dark:border-neutral-700 rounded-lg bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 focus:outline-none focus:ring-1 focus:ring-neutral-400 resize-none"
                  rows={2}
                />
              </FormField>

              <FormField label="LinkedIn URL">
                <input
                  type="url"
                  value={form.linkedin_url}
                  onChange={(e) => setForm({ ...form, linkedin_url: e.target.value })}
                  className="w-full px-3 py-2 text-sm border border-neutral-200 dark:border-neutral-700 rounded-lg bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 focus:outline-none focus:ring-1 focus:ring-neutral-400"
                  placeholder="https://linkedin.com/in/..."
                />
              </FormField>
            </CardBody>
          </Card>
        </div>

        <div className="flex justify-end gap-3 mt-6">
          <button
            type="button"
            onClick={() => navigate(-1)}
            className="px-4 py-2 text-sm font-medium text-neutral-700 dark:text-neutral-300 bg-white dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 rounded-lg hover:bg-neutral-50 dark:hover:bg-neutral-700 transition-colors"
          >
            Cancel
          </button>
          <button
            type="submit"
            disabled={createMutation.isPending}
            className="px-4 py-2 text-sm font-medium text-white bg-neutral-900 dark:bg-neutral-100 dark:text-neutral-900 rounded-lg hover:bg-neutral-800 dark:hover:bg-neutral-200 disabled:opacity-50 transition-colors"
          >
            {createMutation.isPending ? 'Submitting...' : 'Submit Application'}
          </button>
        </div>
      </form>
    </div>
  )
}
