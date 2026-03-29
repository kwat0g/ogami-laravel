import { useState } from 'react'
import { useHire } from '@/hooks/useRecruitment'

interface HiringModalProps {
    applicationUlid: string
    candidateName: string
    defaultStartDate?: string
    onClose: () => void
    onSuccess: () => void
}

export default function HiringModal({
    applicationUlid,
    candidateName,
    defaultStartDate,
    onClose,
    onSuccess,
}: HiringModalProps) {
    const hire = useHire(applicationUlid)
    const [formData, setFormData] = useState({
        start_date: defaultStartDate || new Date().toISOString().split('T')[0],
        date_of_birth: '',
        gender: 'other',
        civil_status: 'SINGLE',
        bir_status: 'M0',
        notes: '',
    })

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault()
        hire.mutate(formData, {
            onSuccess: () => {
                onSuccess()
                onClose()
            },
        })
    }

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div className="w-full max-w-md rounded-lg bg-white p-6 shadow-xl dark:bg-gray-800">
                <h2 className="mb-4 text-xl font-bold text-gray-900 dark:text-white">
                    Hire {candidateName}
                </h2>
                <p className="mb-6 text-sm text-gray-500">
                    This will convert the candidate into an employee and create their record.
                </p>

                <form onSubmit={handleSubmit} className="space-y-4">
                    <div>
                        <label className="block text-xs font-medium text-gray-500 uppercase">Actual Start Date</label>
                        <input
                            type="date"
                            required
                            value={formData.start_date}
                            onChange={(e) => setFormData({ ...formData, start_date: e.target.value })}
                            className="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-700"
                        />
                    </div>

                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <label className="block text-xs font-medium text-gray-500 uppercase">Date of Birth</label>
                            <input
                                type="date"
                                required
                                value={formData.date_of_birth}
                                onChange={(e) => setFormData({ ...formData, date_of_birth: e.target.value })}
                                className="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-700"
                            />
                        </div>
                        <div>
                            <label className="block text-xs font-medium text-gray-500 uppercase">Gender</label>
                            <select
                                value={formData.gender}
                                onChange={(e) => setFormData({ ...formData, gender: e.target.value })}
                                className="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-700"
                            >
                                <option value="male">Male</option>
                                <option value="female">Female</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                    </div>

                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <label className="block text-xs font-medium text-gray-500 uppercase">Civil Status</label>
                            <select
                                value={formData.civil_status}
                                onChange={(e) => setFormData({ ...formData, civil_status: e.target.value })}
                                className="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-700"
                            >
                                <option value="SINGLE">Single</option>
                                <option value="MARRIED">Married</option>
                                <option value="WIDOWED">Widowed</option>
                                <option value="SEPARATED">Separated</option>
                            </select>
                        </div>
                        <div>
                            <label className="block text-xs font-medium text-gray-500 uppercase">BIR Status</label>
                            <select
                                value={formData.bir_status}
                                onChange={(e) => setFormData({ ...formData, bir_status: e.target.value })}
                                className="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-700"
                            >
                                <option value="S">S</option>
                                <option value="S1">S1</option>
                                <option value="S2">S2</option>
                                <option value="S3">S3</option>
                                <option value="S4">S4</option>
                                <option value="M">M</option>
                                <option value="M1">M1</option>
                                <option value="M2">M2</option>
                                <option value="M3">M3</option>
                                <option value="M4">M4</option>
                                <option value="M0">M0</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label className="block text-xs font-medium text-gray-500 uppercase">Notes</label>
                        <textarea
                            value={formData.notes}
                            onChange={(e) => setFormData({ ...formData, notes: e.target.value })}
                            className="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-700"
                            rows={3}
                            placeholder="Any additional hiring notes..."
                        />
                    </div>

                    <div className="flex justify-end gap-3 pt-4">
                        <button
                            type="button"
                            onClick={onClose}
                            className="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300"
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
