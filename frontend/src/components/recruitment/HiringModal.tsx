import { useState } from 'react'
import { useHire } from '@/hooks/useRecruitment'
import { toast } from 'sonner'
import { firstErrorMessage } from '@/lib/errorHandler'

interface HiringModalProps {
    applicationUlid: string
    candidateName: string
    postingTitle?: string
    salaryGradeId?: number | null
    salaryGradeLabel?: string | null
    defaultStartDate?: string
    defaultFirstName?: string
    defaultLastName?: string
    defaultEmail?: string
    defaultAddress?: string
    defaultPhone?: string
    onClose: () => void
    onSuccess: () => void
}

export default function HiringModal({
    applicationUlid,
    candidateName,
    postingTitle,
    salaryGradeId,
    salaryGradeLabel,
    defaultStartDate,
    defaultFirstName,
    defaultLastName,
    defaultEmail,
    defaultAddress,
    defaultPhone,
    onClose,
    onSuccess,
}: HiringModalProps) {
    const hire = useHire(applicationUlid)
    const [formData, setFormData] = useState({
        start_date: defaultStartDate || new Date().toISOString().split('T')[0],
        first_name: defaultFirstName || '',
        last_name: defaultLastName || '',
        personal_email: defaultEmail || '',
        present_address: defaultAddress || '',
        personal_phone: defaultPhone || '',
        date_of_birth: '',
        gender: '',
        civil_status: 'SINGLE',
        salary_grade_id: salaryGradeId ?? undefined,
        notes: '',
    })

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault()

        if (!formData.gender) {
            toast.error('Please select a gender before executing hire.')
            return
        }

        hire.mutate(formData, {
            onSuccess: () => {
                toast.success('Hire executed successfully.')
                onSuccess()
                onClose()
            },
            onError: (error) => {
                toast.error(firstErrorMessage(error) || 'Unable to execute hire. Please review the form and try again.')
            },
        })
    }

    return (
        <div className="fixed inset-0 z-50 overflow-y-auto bg-black/50 p-4">
            <div className="mx-auto my-4 w-full max-w-md rounded-lg bg-white p-6 shadow-xl dark:bg-neutral-800 max-h-[92dvh] flex flex-col">
                <h2 className="mb-4 text-xl font-bold text-neutral-900 dark:text-white">
                    Hire {candidateName}
                </h2>
                <p className="mb-6 text-sm text-neutral-500">
                    This will convert the candidate into an employee and create their record.
                </p>
                {(postingTitle || salaryGradeLabel) && (
                    <div className="mb-4 rounded-md border border-neutral-200 bg-neutral-50 p-3 text-xs text-neutral-600 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-300">
                        {postingTitle && <p>Posting: {postingTitle}</p>}
                        {salaryGradeLabel && <p>Salary Grade: {salaryGradeLabel}</p>}
                    </div>
                )}

                <form onSubmit={handleSubmit} className="flex min-h-0 flex-1 flex-col">
                    <div className="space-y-4 overflow-y-auto pr-1">
                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <label className="block text-xs font-medium text-neutral-500 uppercase">First Name</label>
                            <input
                                type="text"
                                required
                                value={formData.first_name}
                                onChange={(e) => setFormData({ ...formData, first_name: e.target.value })}
                                className="mt-1 w-full rounded-md border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-600 dark:bg-neutral-700"
                            />
                        </div>
                        <div>
                            <label className="block text-xs font-medium text-neutral-500 uppercase">Last Name</label>
                            <input
                                type="text"
                                required
                                value={formData.last_name}
                                onChange={(e) => setFormData({ ...formData, last_name: e.target.value })}
                                className="mt-1 w-full rounded-md border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-600 dark:bg-neutral-700"
                            />
                        </div>
                    </div>

                    <div>
                        <label className="block text-xs font-medium text-neutral-500 uppercase">Actual Start Date</label>
                        <input
                            type="date"
                            required
                            value={formData.start_date}
                            onChange={(e) => setFormData({ ...formData, start_date: e.target.value })}
                            className="mt-1 w-full rounded-md border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-600 dark:bg-neutral-700"
                        />
                    </div>

                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <label className="block text-xs font-medium text-neutral-500 uppercase">Date of Birth</label>
                            <input
                                type="date"
                                required
                                value={formData.date_of_birth}
                                onChange={(e) => setFormData({ ...formData, date_of_birth: e.target.value })}
                                className="mt-1 w-full rounded-md border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-600 dark:bg-neutral-700"
                            />
                        </div>
                        <div>
                            <label className="block text-xs font-medium text-neutral-500 uppercase">Gender</label>
                            <select
                                required
                                value={formData.gender}
                                onChange={(e) => setFormData({ ...formData, gender: e.target.value })}
                                className="mt-1 w-full rounded-md border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-600 dark:bg-neutral-700"
                            >
                                <option value="">Select gender</option>
                                <option value="male">Male</option>
                                <option value="female">Female</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                    </div>

                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <label className="block text-xs font-medium text-neutral-500 uppercase">Civil Status</label>
                            <select
                                value={formData.civil_status}
                                onChange={(e) => setFormData({ ...formData, civil_status: e.target.value })}
                                className="mt-1 w-full rounded-md border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-600 dark:bg-neutral-700"
                            >
                                <option value="SINGLE">Single</option>
                                <option value="MARRIED">Married</option>
                                <option value="WIDOWED">Widowed</option>
                                <option value="SEPARATED">Separated</option>
                            </select>
                        </div>
                        <div>
                            <label className="block text-xs font-medium text-neutral-500 uppercase">Personal Phone</label>
                            <input
                                type="text"
                                value={formData.personal_phone}
                                onChange={(e) => setFormData({ ...formData, personal_phone: e.target.value })}
                                className="mt-1 w-full rounded-md border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-600 dark:bg-neutral-700"
                            />
                        </div>
                    </div>

                    <div>
                        <label className="block text-xs font-medium text-neutral-500 uppercase">Personal Email</label>
                        <input
                            type="email"
                            required
                            value={formData.personal_email}
                            onChange={(e) => setFormData({ ...formData, personal_email: e.target.value })}
                            className="mt-1 w-full rounded-md border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-600 dark:bg-neutral-700"
                        />
                    </div>

                    <div>
                        <label className="block text-xs font-medium text-neutral-500 uppercase">Present Address</label>
                        <textarea
                            required
                            value={formData.present_address}
                            onChange={(e) => setFormData({ ...formData, present_address: e.target.value })}
                            className="mt-1 w-full rounded-md border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-600 dark:bg-neutral-700"
                            rows={2}
                        />
                    </div>

                    <div>
                        <label className="block text-xs font-medium text-neutral-500 uppercase">Notes</label>
                        <textarea
                            value={formData.notes}
                            onChange={(e) => setFormData({ ...formData, notes: e.target.value })}
                            className="mt-1 w-full rounded-md border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-600 dark:bg-neutral-700"
                            rows={3}
                                placeholder="Any additional hiring notes..."
                        />
                    </div>

                    </div>

                    <div className="flex justify-end gap-3 border-t border-neutral-200 pt-4 mt-4 dark:border-neutral-700">
                        <button
                            type="button"
                            onClick={onClose}
                            className="rounded-md border border-neutral-300 px-4 py-2 text-sm font-medium text-neutral-700 hover:bg-neutral-50 dark:border-neutral-600 dark:text-neutral-300"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            disabled={hire.isPending}
                            className="rounded-md bg-green-600 px-4 py-2 text-sm font-semibold text-white hover:bg-green-500 disabled:opacity-50"
                        >
                            {hire.isPending ? 'Processing...' : 'Execute Hire'}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    )
}
