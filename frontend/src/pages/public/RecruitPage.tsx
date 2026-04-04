import { useMemo, useState } from 'react'
import { Link } from 'react-router-dom'
import { Briefcase, MapPin, Send } from 'lucide-react'
import { toast } from 'sonner'
import { usePublicRecruitmentPostings, useSubmitPublicApplication } from '@/hooks/usePublicRecruitment'

interface PublicApplicationForm {
  first_name: string
  last_name: string
  email: string
  phone: string
  address: string
  linkedin_url: string
  cover_letter: string
  resume: File | null
}

const INITIAL_FORM: PublicApplicationForm = {
  first_name: '',
  last_name: '',
  email: '',
  phone: '',
  address: '',
  linkedin_url: '',
  cover_letter: '',
  resume: null,
}

export default function RecruitPage() {
  const { data, isLoading } = usePublicRecruitmentPostings()
  const submitMutation = useSubmitPublicApplication()
  const [selectedPostingUlid, setSelectedPostingUlid] = useState<string>('')
  const [form, setForm] = useState<PublicApplicationForm>(INITIAL_FORM)

  const postings = data?.data ?? []
  const selectedPosting = useMemo(
    () => postings.find((posting) => posting.ulid === selectedPostingUlid) ?? null,
    [postings, selectedPostingUlid],
  )

  const onSubmit = async (e: React.FormEvent) => {
    e.preventDefault()

    if (!selectedPostingUlid) {
      toast.error('Please select a job posting first.')
      return
    }

    if (!form.resume) {
      toast.error('Please attach your resume in PDF format.')
      return
    }

    const payload = new FormData()
    payload.append('posting_ulid', selectedPostingUlid)
    payload.append('candidate[first_name]', form.first_name)
    payload.append('candidate[last_name]', form.last_name)
    payload.append('candidate[email]', form.email)
    payload.append('candidate[phone]', form.phone)
    payload.append('candidate[address]', form.address)
    payload.append('candidate[linkedin_url]', form.linkedin_url)
    payload.append('cover_letter', form.cover_letter)
    payload.append('resume', form.resume)

    try {
      await submitMutation.mutateAsync(payload)
      toast.success('Application submitted successfully.')
      setForm(INITIAL_FORM)
      setSelectedPostingUlid('')
    } catch {
      toast.error('Unable to submit your application. Please review your details and try again.')
    }
  }

  return (
    <div className="min-h-screen bg-neutral-50 text-neutral-900">
      <div className="mx-auto max-w-6xl px-4 py-10 sm:px-6 lg:px-8">
        <div className="mb-8 flex items-center justify-between">
          <div>
            <p className="text-xs font-semibold uppercase tracking-[0.16em] text-blue-600">Careers</p>
            <h1 className="mt-2 text-3xl font-black tracking-tight">Join Ogami Philippines</h1>
            <p className="mt-2 text-sm text-neutral-600">
              Browse active job openings and apply directly with your resume.
            </p>
          </div>
          <Link
            to="/"
            className="rounded-lg border border-neutral-300 bg-white px-4 py-2 text-sm font-semibold hover:bg-neutral-100"
          >
            Back to Landing
          </Link>
        </div>

        <div className="grid gap-8 lg:grid-cols-5">
          <section className="space-y-4 lg:col-span-3">
            <h2 className="text-lg font-bold">Active Job Postings</h2>
            {isLoading && <p className="text-sm text-neutral-500">Loading postings...</p>}
            {!isLoading && postings.length === 0 && (
              <div className="rounded-xl border border-neutral-200 bg-white p-6 text-sm text-neutral-500">
                No active job postings right now.
              </div>
            )}

            {postings.map((posting) => (
              <button
                key={posting.ulid}
                type="button"
                onClick={() => setSelectedPostingUlid(posting.ulid)}
                className={`w-full rounded-xl border bg-white p-5 text-left transition ${
                  selectedPostingUlid === posting.ulid
                    ? 'border-blue-600 ring-2 ring-blue-100'
                    : 'border-neutral-200 hover:border-neutral-300'
                }`}
              >
                <div className="flex items-start justify-between gap-4">
                  <div>
                    <p className="text-lg font-semibold">{posting.title}</p>
                    <p className="mt-1 text-sm text-neutral-600">
                      {posting.department?.name ?? 'N/A'} - {posting.position?.title ?? 'N/A'}
                    </p>
                  </div>
                  <span className="rounded-full bg-blue-50 px-3 py-1 text-xs font-semibold text-blue-700">
                    {posting.employment_type}
                  </span>
                </div>
                <div className="mt-3 flex flex-wrap items-center gap-4 text-xs text-neutral-500">
                  <span className="inline-flex items-center gap-1">
                    <Briefcase className="h-3.5 w-3.5" />
                    Headcount: {posting.headcount ?? 1}
                  </span>
                  <span>
                    Salary Grade: {posting.salary_grade
                      ? `SG ${posting.salary_grade.level ?? '*'} - ${posting.salary_grade.name ?? posting.salary_grade.code}`
                      : 'Not specified'}
                  </span>
                  <span className="inline-flex items-center gap-1">
                    <MapPin className="h-3.5 w-3.5" />
                    {posting.location ?? 'Location to be discussed'}
                  </span>
                </div>

                {(posting.requirement_items?.length ?? 0) > 0 && (
                  <div className="mt-3 flex flex-wrap gap-2">
                    {posting.requirement_items?.slice(0, 6).map((item) => (
                      <span
                        key={`${posting.ulid}-${item}`}
                        className="rounded-full border border-amber-200 bg-amber-50 px-3 py-1 text-[11px] font-semibold text-amber-800"
                      >
                        {item}
                      </span>
                    ))}
                  </div>
                )}
              </button>
            ))}
          </section>

          <section className="rounded-xl border border-neutral-200 bg-white p-6 lg:col-span-2">
            <h2 className="text-lg font-bold">Apply Now</h2>
            <p className="mt-1 text-xs text-neutral-500">
              {selectedPosting
                ? `Applying for: ${selectedPosting.title}`
                : 'Select a posting first from the list.'}
            </p>

            <form onSubmit={onSubmit} className="mt-4 space-y-3">
              <div className="grid grid-cols-2 gap-3">
                <input
                  value={form.first_name}
                  onChange={(e) => setForm({ ...form, first_name: e.target.value })}
                  placeholder="First name"
                  className="rounded-lg border border-neutral-300 px-3 py-2 text-sm"
                  required
                />
                <input
                  value={form.last_name}
                  onChange={(e) => setForm({ ...form, last_name: e.target.value })}
                  placeholder="Last name"
                  className="rounded-lg border border-neutral-300 px-3 py-2 text-sm"
                  required
                />
              </div>

              <input
                type="email"
                value={form.email}
                onChange={(e) => setForm({ ...form, email: e.target.value })}
                placeholder="Email"
                className="w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm"
                required
              />

              <input
                value={form.phone}
                onChange={(e) => setForm({ ...form, phone: e.target.value })}
                placeholder="Phone"
                className="w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm"
                required
              />

              <textarea
                value={form.address}
                onChange={(e) => setForm({ ...form, address: e.target.value })}
                placeholder="Address"
                rows={2}
                className="w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm"
              />

              <input
                type="url"
                value={form.linkedin_url}
                onChange={(e) => setForm({ ...form, linkedin_url: e.target.value })}
                placeholder="LinkedIn URL (optional)"
                className="w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm"
              />

              <textarea
                value={form.cover_letter}
                onChange={(e) => setForm({ ...form, cover_letter: e.target.value })}
                placeholder="Short cover letter"
                rows={4}
                className="w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm"
              />

              <div>
                <label className="mb-1 block text-xs font-semibold text-neutral-600">Resume (PDF only, max 5MB)</label>
                <input
                  type="file"
                  accept="application/pdf"
                  onChange={(e) => setForm({ ...form, resume: e.target.files?.[0] ?? null })}
                  className="w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm"
                  required
                />
              </div>

              <button
                type="submit"
                disabled={submitMutation.isPending || !selectedPostingUlid}
                className="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-500 disabled:cursor-not-allowed disabled:opacity-60"
              >
                <Send className="h-4 w-4" />
                {submitMutation.isPending ? 'Submitting...' : 'Submit Application'}
              </button>
            </form>
          </section>
        </div>
      </div>
    </div>
  )
}
