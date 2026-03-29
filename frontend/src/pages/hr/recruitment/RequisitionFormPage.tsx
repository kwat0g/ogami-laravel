import { useState, useEffect } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { useCreateRequisition, useRequisition, useUpdateRequisition } from '@/hooks/useRecruitment'
import { useDepartments, usePositions } from '@/hooks/useEmployees'
import { PageHeader } from '@/components/ui/PageHeader'
import { Card, CardHeader, CardBody } from '@/components/ui/Card'
import FormField from '@/components/ui/FormField'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import { toast } from 'sonner'

export default function RequisitionFormPage() {
  const { ulid } = useParams<{ ulid: string }>()
  const isEdit = !!ulid
  const navigate = useNavigate()
  const { data: existing, isLoading: loadingExisting } = useRequisition(ulid ?? '')
  const createMutation = useCreateRequisition()
  const updateMutation = useUpdateRequisition(ulid ?? '')

  // Fetch departments and positions for dropdowns
  const { data: deptsData } = useDepartments(true)
  const departments = deptsData?.data ?? []

  const [departmentId, setDepartmentId] = useState('')
  const { data: positionsData } = usePositions(departmentId ? Number(departmentId) : undefined)
  const positions = positionsData?.data ?? positionsData ?? []

  const [form, setForm] = useState({
    department_id: '',
    position_id: '',
    employment_type: 'regular',
    headcount: '1',
    reason: '',
    justification: '',
    salary_range_min: '',
    salary_range_max: '',
    target_start_date: '',
  })

  // Pre-fill form when editing
  useEffect(() => {
    if (existing && isEdit) {
      setForm({
        department_id: String(existing.department?.id ?? ''),
        position_id: String(existing.position?.id ?? ''),
        employment_type: existing.employment_type ?? 'regular',
        headcount: String(existing.headcount ?? 1),
        reason: existing.reason ?? '',
        justification: existing.justification ?? '',
        salary_range_min: existing.salary_range_min ? String(existing.salary_range_min) : '',
        salary_range_max: existing.salary_range_max ? String(existing.salary_range_max) : '',
        target_start_date: existing.target_start_date ?? '',
      })
      setDepartmentId(String(existing.department?.id ?? ''))
    }
  }, [existing, isEdit])

  // Sync department selection
  useEffect(() => {
    if (form.department_id !== departmentId) {
      setDepartmentId(form.department_id)
      // Reset position when department changes (unless editing)
      if (!isEdit || form.department_id !== String(existing?.department?.id ?? '')) {
        setForm((f) => ({ ...f, position_id: '' }))
      }
    }
  }, [form.department_id])

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    const payload = {
      department_id: Number(form.department_id),
      position_id: Number(form.position_id),
      employment_type: form.employment_type,
      headcount: Number(form.headcount),
      reason: form.reason,
      justification: form.justification || null,
      salary_range_min: form.salary_range_min ? Number(form.salary_range_min) : null,
      salary_range_max: form.salary_range_max ? Number(form.salary_range_max) : null,
      target_start_date: form.target_start_date || null,
    }

    try {
      if (isEdit) {
        await updateMutation.mutateAsync(payload)
        toast.success('Requisition updated')
        navigate(`/hr/recruitment/requisitions/${ulid}`)
      } else {
        await createMutation.mutateAsync(payload)
        toast.success('Requisition created')
        navigate('/hr/recruitment?tab=requisitions')
      }
    } catch {
      toast.error('Failed to save requisition')
    }
  }

  const isPending = createMutation.isPending || updateMutation.isPending

  if (isEdit && loadingExisting) return <SkeletonLoader rows={8} />

  return (
    <div>
      <PageHeader
        title={isEdit ? 'Edit Requisition' : 'New Job Requisition'}
        subtitle={isEdit ? `Editing ${existing?.requisition_number}` : 'Submit a new hiring request'}
        backTo="/hr/recruitment?tab=requisitions"
      />

      <form onSubmit={handleSubmit}>
        <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
          {/* Left column - Main details */}
          <Card>
            <CardHeader>Position Details</CardHeader>
            <CardBody className="space-y-4">
              <FormField label="Department" required>
                <select
                  value={form.department_id}
                  onChange={(e) => setForm({ ...form, department_id: e.target.value })}
                  className="w-full px-3 py-2 text-sm border border-neutral-200 dark:border-neutral-700 rounded-lg bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 focus:outline-none focus:ring-1 focus:ring-neutral-400"
                  required
                >
                  <option value="">Select department...</option>
                  {/* eslint-disable-next-line @typescript-eslint/no-explicit-any */}
                  {departments.map((d: any) => (
                    <option key={d.id} value={d.id}>{d.name} ({d.code})</option>
                  ))}
                </select>
              </FormField>

              <FormField label="Position" required>
                <select
                  value={form.position_id}
                  onChange={(e) => setForm({ ...form, position_id: e.target.value })}
                  className="w-full px-3 py-2 text-sm border border-neutral-200 dark:border-neutral-700 rounded-lg bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 focus:outline-none focus:ring-1 focus:ring-neutral-400"
                  required
                  disabled={!form.department_id}
                >
                  <option value="">{form.department_id ? 'Select position...' : 'Select department first'}</option>
                  {/* eslint-disable-next-line @typescript-eslint/no-explicit-any */}
                  {(Array.isArray(positions) ? positions : []).map((p: any) => (
                    <option key={p.id} value={p.id}>{p.title} ({p.code})</option>
                  ))}
                </select>
              </FormField>

              <div className="grid grid-cols-2 gap-4">
                <FormField label="Employment Type" required>
                  <select
                    value={form.employment_type}
                    onChange={(e) => setForm({ ...form, employment_type: e.target.value })}
                    className="w-full px-3 py-2 text-sm border border-neutral-200 dark:border-neutral-700 rounded-lg bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 focus:outline-none focus:ring-1 focus:ring-neutral-400"
                  >
                    <option value="regular">Regular</option>
                    <option value="contractual">Contractual</option>
                    <option value="project_based">Project-Based</option>
                    <option value="part_time">Part-Time</option>
                  </select>
                </FormField>

                <FormField label="Headcount" required>
                  <input
                    type="number"
                    min="1"
                    value={form.headcount}
                    onChange={(e) => setForm({ ...form, headcount: e.target.value })}
                    className="w-full px-3 py-2 text-sm border border-neutral-200 dark:border-neutral-700 rounded-lg bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 focus:outline-none focus:ring-1 focus:ring-neutral-400"
                    required
                  />
                </FormField>
              </div>

              <FormField label="Target Start Date">
                <input
                  type="date"
                  value={form.target_start_date}
                  onChange={(e) => setForm({ ...form, target_start_date: e.target.value })}
                  className="w-full px-3 py-2 text-sm border border-neutral-200 dark:border-neutral-700 rounded-lg bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 focus:outline-none focus:ring-1 focus:ring-neutral-400"
                />
              </FormField>
            </CardBody>
          </Card>

          {/* Right column - Justification & salary */}
          <div className="space-y-6">
            <Card>
              <CardHeader>Salary Range (in centavos)</CardHeader>
              <CardBody className="space-y-4">
                <div className="grid grid-cols-2 gap-4">
                  <FormField label="Minimum" hint="e.g. 2000000 = PHP 20,000">
                    <input
                      type="number"
                      min="0"
                      value={form.salary_range_min}
                      onChange={(e) => setForm({ ...form, salary_range_min: e.target.value })}
                      className="w-full px-3 py-2 text-sm border border-neutral-200 dark:border-neutral-700 rounded-lg bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 focus:outline-none focus:ring-1 focus:ring-neutral-400"
                      placeholder="0"
                    />
                  </FormField>
                  <FormField label="Maximum" hint="e.g. 4000000 = PHP 40,000">
                    <input
                      type="number"
                      min="0"
                      value={form.salary_range_max}
                      onChange={(e) => setForm({ ...form, salary_range_max: e.target.value })}
                      className="w-full px-3 py-2 text-sm border border-neutral-200 dark:border-neutral-700 rounded-lg bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 focus:outline-none focus:ring-1 focus:ring-neutral-400"
                      placeholder="0"
                    />
                  </FormField>
                </div>
              </CardBody>
            </Card>

            <Card>
              <CardHeader>Justification</CardHeader>
              <CardBody className="space-y-4">
                <FormField label="Reason for Hiring" required>
                  <textarea
                    value={form.reason}
                    onChange={(e) => setForm({ ...form, reason: e.target.value })}
                    rows={3}
                    className="w-full px-3 py-2 text-sm border border-neutral-200 dark:border-neutral-700 rounded-lg bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 focus:outline-none focus:ring-1 focus:ring-neutral-400 resize-none"
                    required
                    placeholder="Why is this position needed?"
                  />
                </FormField>

                <FormField label="Additional Justification">
                  <textarea
                    value={form.justification}
                    onChange={(e) => setForm({ ...form, justification: e.target.value })}
                    rows={2}
                    className="w-full px-3 py-2 text-sm border border-neutral-200 dark:border-neutral-700 rounded-lg bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 focus:outline-none focus:ring-1 focus:ring-neutral-400 resize-none"
                    placeholder="Optional business justification or budget notes"
                  />
                </FormField>
              </CardBody>
            </Card>
          </div>
        </div>

        {/* Actions */}
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
            disabled={isPending}
            className="px-4 py-2 text-sm font-medium text-white bg-neutral-900 dark:bg-neutral-100 dark:text-neutral-900 rounded-lg hover:bg-neutral-800 dark:hover:bg-neutral-200 disabled:opacity-50 transition-colors"
          >
            {isPending ? 'Saving...' : isEdit ? 'Update Requisition' : 'Create Requisition'}
          </button>
        </div>
      </form>
    </div>
  )
}
