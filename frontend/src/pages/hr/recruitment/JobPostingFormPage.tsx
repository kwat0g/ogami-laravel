import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useCreatePosting } from '@/hooks/useRecruitment'
import {
  useDepartments,
  usePositions,
  useSalaryGrades as useHrSalaryGrades,
} from '@/hooks/useEmployees'
import { toast } from 'sonner'

export default function JobPostingFormPage() {
  const navigate = useNavigate()
  const createMutation = useCreatePosting()
  const [requirementInput, setRequirementInput] = useState('')
  const [requirementItems, setRequirementItems] = useState<string[]>([])

  const [form, setForm] = useState({
    department_id: '',
    position_id: '',
    salary_grade_id: '',
    headcount: '1',
    title: '',
    description: '',
    employment_type: 'regular',
  })

  // Direct-posting references
  const { data: departmentsData } = useDepartments(true)
  const departments = departmentsData?.data ?? []
  const selectedDepartmentId = form.department_id ? Number(form.department_id) : undefined
  const { data: positionsData } = usePositions(selectedDepartmentId)
  const positions = positionsData?.data ?? []
  const { data: salaryGrades } = useHrSalaryGrades()

  const addRequirementItem = () => {
    const item = requirementInput.trim()
    if (!item) return
    setRequirementItems((prev) => (prev.includes(item) ? prev : [...prev, item]))
    setRequirementInput('')
  }

  const removeRequirementItem = (item: string) => {
    setRequirementItems((prev) => prev.filter((req) => req !== item))
  }

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()

    if (!form.department_id || !form.position_id || !form.salary_grade_id || !form.headcount) return

    if (requirementItems.length === 0) {
      toast.error('Add at least one requirement item.')
      return
    }

    const payload: Record<string, unknown> = {
      title: form.title,
      description: form.description,
      requirements: requirementItems.join('\n'),
      employment_type: form.employment_type,
      is_internal: false,
      is_external: true,
      department_id: Number(form.department_id),
      position_id: Number(form.position_id),
      salary_grade_id: Number(form.salary_grade_id),
      headcount: Number(form.headcount),
    }

    try {
      await createMutation.mutateAsync(payload)
      toast.success('Job posting created')
      navigate('/hr/recruitment?tab=postings')
    } catch {
    }
  }

  return (
    <div className="mx-auto max-w-2xl p-6">
      <h1 className="mb-6 text-lg font-semibold text-neutral-900 dark:text-white">Create Job Posting</h1>

      <form onSubmit={handleSubmit} className="space-y-4">
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
              <label className="block text-sm font-medium text-neutral-700 dark:text-neutral-300">Department <span className="text-red-500">*</span></label>
              <select
                value={form.department_id}
                onChange={(e) => setForm({ ...form, department_id: e.target.value, position_id: '' })}
                className="mt-1 w-full rounded-md border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-600 dark:bg-neutral-800"
                required
              >
                <option value="">Select department...</option>
                {departments.map((department) => (
                  <option key={department.id} value={department.id}>{department.code} - {department.name}</option>
                ))}
              </select>
            </div>

            <div>
              <label className="block text-sm font-medium text-neutral-700 dark:text-neutral-300">Position <span className="text-red-500">*</span></label>
              <select
                value={form.position_id}
                onChange={(e) => setForm({ ...form, position_id: e.target.value })}
                className="mt-1 w-full rounded-md border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-600 dark:bg-neutral-800"
                required
                disabled={!form.department_id}
              >
                <option value="">Select position...</option>
                {positions.map((position) => (
                  <option key={position.id} value={position.id}>{position.title}</option>
                ))}
              </select>
            </div>

            <div>
              <label className="block text-sm font-medium text-neutral-700 dark:text-neutral-300">Salary Grade <span className="text-red-500">*</span></label>
              <select
                value={form.salary_grade_id}
                onChange={(e) => setForm({ ...form, salary_grade_id: e.target.value })}
                className="mt-1 w-full rounded-md border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-600 dark:bg-neutral-800"
                required
              >
                <option value="">Select salary grade...</option>
                {(salaryGrades ?? []).map((grade) => (
                  <option key={grade.id} value={grade.id}>{grade.code} - {grade.name}</option>
                ))}
              </select>
            </div>

            <div>
              <label className="block text-sm font-medium text-neutral-700 dark:text-neutral-300">Headcount <span className="text-red-500">*</span></label>
              <input
                type="number"
                min={1}
                value={form.headcount}
                onChange={(e) => setForm({ ...form, headcount: e.target.value })}
                className="mt-1 w-full rounded-md border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-600 dark:bg-neutral-800"
                required
              />
            </div>
          </div>

        <div>
          <label className="block text-sm font-medium text-neutral-700 dark:text-neutral-300">Job Title <span className="text-red-500">*</span></label>
          <input
            type="text"
            value={form.title}
            onChange={(e) => setForm({ ...form, title: e.target.value })}
            className="mt-1 w-full rounded-md border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-600 dark:bg-neutral-800"
            required
          />
        </div>

        <div>
          <label className="block text-sm font-medium text-neutral-700 dark:text-neutral-300">Job Description <span className="text-red-500">*</span></label>
          <textarea
            value={form.description}
            onChange={(e) => setForm({ ...form, description: e.target.value })}
            rows={5}
            className="mt-1 w-full rounded-md border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-600 dark:bg-neutral-800"
            required
            placeholder="Minimum 10 characters..."
          />
        </div>

        <div>
          <label className="block text-sm font-medium text-neutral-700 dark:text-neutral-300">Requirements <span className="text-red-500">*</span></label>
          <div className="mt-1 flex gap-2">
            <input
              type="text"
              value={requirementInput}
              onChange={(e) => setRequirementInput(e.target.value)}
              onKeyDown={(e) => {
                if (e.key === 'Enter') {
                  e.preventDefault()
                  addRequirementItem()
                }
              }}
              className="w-full rounded-md border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-600 dark:bg-neutral-800"
              placeholder="Add one requirement item"
            />
            <button
              type="button"
              onClick={addRequirementItem}
              className="rounded-md bg-neutral-900 px-3 py-2 text-xs font-semibold text-white hover:bg-neutral-800"
            >
              Add
            </button>
          </div>
          <p className="mt-1 text-xs text-neutral-500">These items will appear as requirement banners on the public Recruit page.</p>
          {requirementItems.length > 0 && (
            <div className="mt-2 flex flex-wrap gap-2">
              {requirementItems.map((item) => (
                <span key={item} className="inline-flex items-center gap-2 rounded-full border border-blue-200 bg-blue-50 px-3 py-1 text-xs font-medium text-blue-800">
                  {item}
                  <button
                    type="button"
                    onClick={() => removeRequirementItem(item)}
                    className="text-blue-600 hover:text-blue-900"
                  >
                    x
                  </button>
                </span>
              ))}
            </div>
          )}
        </div>

        <div>
          <label className="block text-sm font-medium text-neutral-700 dark:text-neutral-300">Employment Type</label>
          <select
            value={form.employment_type}
            onChange={(e) => setForm({ ...form, employment_type: e.target.value })}
            className="mt-1 w-full rounded-md border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-600 dark:bg-neutral-800"
          >
            <option value="regular">Regular</option>
            <option value="contractual">Contractual</option>
            <option value="project_based">Project Based</option>
            <option value="part_time">Part Time</option>
          </select>
        </div>

        <div className="flex justify-end gap-3 pt-4">
          <button type="button" onClick={() => navigate(-1)} className="rounded-md border border-neutral-300 px-4 py-2 text-sm text-neutral-700 hover:bg-neutral-50 dark:border-neutral-600 dark:text-neutral-300">
            Cancel
          </button>
          <button type="submit" disabled={createMutation.isPending} className="rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-500 disabled:opacity-50">
            {createMutation.isPending ? 'Creating...' : 'Create Posting'}
          </button>
        </div>
      </form>
    </div>
  )
}
