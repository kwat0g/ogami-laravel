import { useState } from 'react'
import { Plus, X, Trash2, Banknote, RefreshCw } from 'lucide-react'
import { useForm } from 'react-hook-form'
import { z } from 'zod'
import { zodResolver } from '@hookform/resolvers/zod'
import {
  usePayrollAdjustments,
  useCreateAdjustment,
  useDeleteAdjustment,
} from '@/hooks/usePayroll'
import { useEmployees } from '@/hooks/useHr'
import { useAuthStore } from '@/stores/authStore'
import { PERMISSIONS } from '@/lib/permissions'
import CurrencyAmount from '@/components/ui/CurrencyAmount'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import { toast } from 'sonner'
import ConfirmDestructiveDialog from '@/components/ui/ConfirmDestructiveDialog'

// Replicate Zod schema to enforce centavo handling safely (frontend forms capture float)
const adjustmentFormSchema = z.object({
  employee_id: z.coerce.number().min(1, 'Employee is required'),
  type: z.enum(['earning', 'deduction']),
  nature: z.enum(['taxable', 'non_taxable']),
  description: z.string().min(1, 'Description is required').max(200),
  amount: z.coerce.number().min(0.01, 'Amount must be greater than zero'),
})

type FormData = z.infer<typeof adjustmentFormSchema>

export default function ManagePayrollAdjustmentsModal({
  runId,
  isOpen,
  onClose,
}: {
  runId: string
  isOpen: boolean
  onClose: () => void
}) {
  const [isAdding, setIsAdding] = useState(false)
  const hasPermission = useAuthStore((s) => s.hasPermission)

  const canQueryAdjustments =
    hasPermission(PERMISSIONS.payroll.initiate) ||
    hasPermission(PERMISSIONS.payroll.compute) ||
    hasPermission(PERMISSIONS.payroll.hr_approve) ||
    hasPermission(PERMISSIONS.payroll.acctg_approve) ||
    hasPermission(PERMISSIONS.payroll.approve)

  // Remote data
  const { data: adjustmentsData, isLoading: isLoadingAdjustments } = usePayrollAdjustments(
    runId,
    isOpen && canQueryAdjustments,
  )
  const { data: employeesData, isLoading: isLoadingEmployees } = useEmployees({ per_page: 500, active_only: true })
  
  const createMutation = useCreateAdjustment(runId)
  const deleteMutation = useDeleteAdjustment(runId)

  // Form setup
  const {
    register,
    handleSubmit,
    reset,
    formState: { errors },
  } = useForm<FormData>({
    resolver: zodResolver(adjustmentFormSchema),
    defaultValues: {
      type: 'earning',
      nature: 'taxable',
      description: '',
      amount: '' as unknown as number,
    },
  })

  // Handlers
  const onSubmit = async (data: FormData) => {
    try {
      await createMutation.mutateAsync({
        employee_id: data.employee_id,
        type: data.type,
        nature: data.nature,
        description: data.description,
        // MUST convert to centavos
        amount_centavos: Math.round(data.amount * 100),
      })
      toast.success('Adjustment added')
      reset()
      setIsAdding(false)
    } catch {
    }
  }

  const handleDelete = async (id: number) => {
    try {
      await deleteMutation.mutateAsync(id)
      toast.success('Adjustment removed')
    } catch {
    }
  }

  // Effect: reset form state when modal closes
  if (!isOpen) {
    if (isAdding) setIsAdding(false)
    return null
  }

  const adjustments = adjustmentsData?.data ?? []
  const employees = employeesData?.data ?? []

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 overflow-y-auto w-full p-4">
      <div 
        className="bg-white rounded shadow-xl w-full max-w-2xl overflow-hidden flex flex-col max-h-[90vh]"
        onClick={(e: React.MouseEvent) => e.stopPropagation()}
      >
        <div className="px-6 py-4 border-b border-neutral-200 flex items-center justify-between shrink-0">
          <div className="flex items-center gap-2 text-neutral-900">
            <Banknote className="h-5 w-5" />
            <h2 className="text-lg font-semibold">Manage Manual Adjustments</h2>
          </div>
          <button onClick={onClose} className="p-1 hover:bg-neutral-100 rounded text-neutral-400 hover:text-neutral-600">
            <X className="h-5 w-5" />
          </button>
        </div>

        <div className="p-6 overflow-y-auto flex-1 bg-neutral-50/50">
          
          {/* Form panel for new adjustment */}
          {isAdding ? (
             <form onSubmit={handleSubmit(onSubmit)} className="bg-white border text-sm border-neutral-200 rounded p-5 mb-6 shadow-sm">
                <div className="flex items-center justify-between mb-4">
                  <h3 className="font-semibold text-neutral-800">New Adjustment</h3>
                  <button type="button" onClick={() => { setIsAdding(false); reset() }} className="text-neutral-400 hover:text-neutral-600 text-xs font-medium uppercase tracking-wider">Cancel</button>
                </div>

                <div className="grid grid-cols-2 gap-4 mb-4">
                   <div className="col-span-2">
                     <label className="block text-xs font-medium text-neutral-700 mb-1">Employee</label>
                     <select 
                       {...register('employee_id')}
                       className="w-full border border-neutral-300 rounded px-3 py-2 bg-white focus:outline-none focus:ring-1 focus:ring-neutral-900"
                       disabled={isLoadingEmployees}
                     >
                       <option value="">Select an employee...</option>
                       {employees.map(e => (
                         <option key={e.id} value={e.id}>{e.employee_code} — {e.last_name}, {e.first_name}</option>
                       ))}
                     </select>
                     {errors.employee_id && <p className="text-red-500 text-xs mt-1">{errors.employee_id.message}</p>}
                   </div>

                   <div>
                      <label className="block text-xs font-medium text-neutral-700 mb-1">Type</label>
                      <select 
                        {...register('type')}
                        className="w-full border border-neutral-300 rounded px-3 py-2 bg-white focus:outline-none focus:ring-1 focus:ring-neutral-900"
                      >
                        <option value="earning">Earning</option>
                        <option value="deduction">Deduction</option>
                      </select>
                      {errors.type && <p className="text-red-500 text-xs mt-1">{errors.type.message}</p>}
                   </div>

                   <div>
                      <label className="block text-xs font-medium text-neutral-700 mb-1">Nature</label>
                      <select 
                        {...register('nature')}
                        className="w-full border border-neutral-300 rounded px-3 py-2 bg-white focus:outline-none focus:ring-1 focus:ring-neutral-900"
                      >
                        <option value="taxable">Taxable</option>
                        <option value="non_taxable">Non-Taxable</option>
                      </select>
                      {errors.nature && <p className="text-red-500 text-xs mt-1">{errors.nature.message}</p>}
                   </div>

                   <div className="col-span-1">
                      <label className="block text-xs font-medium text-neutral-700 mb-1">Amount (₱)</label>
                      <input 
                        type="number"
                        step="0.01"
                        placeholder="e.g. 500.00"
                        {...register('amount')}
                        className="w-full border border-neutral-300 rounded px-3 py-2 bg-white focus:outline-none focus:ring-1 focus:ring-neutral-900"
                      />
                      {errors.amount && <p className="text-red-500 text-xs mt-1">{errors.amount.message}</p>}
                   </div>

                   <div className="col-span-1">
                      <label className="block text-xs font-medium text-neutral-700 mb-1">Description</label>
                      <input 
                        type="text"
                        placeholder="e.g. Performance Bonus"
                        {...register('description')}
                        className="w-full border border-neutral-300 rounded px-3 py-2 bg-white focus:outline-none focus:ring-1 focus:ring-neutral-900"
                      />
                      {errors.description && <p className="text-red-500 text-xs mt-1">{errors.description.message}</p>}
                   </div>
                </div>

                <div className="flex justify-end gap-2 mt-2">
                  <button 
                    type="submit" 
                    disabled={createMutation.isPending}
                    className="flex items-center gap-2 bg-neutral-900 hover:bg-neutral-800 text-white px-4 py-2 rounded text-sm font-medium transition-colors disabled:opacity-50"
                  >
                    {createMutation.isPending ? <RefreshCw className="h-4 w-4 animate-spin" /> : <Plus className="h-4 w-4" />}
                    Save Adjustment
                  </button>
                </div>
             </form>
          ) : null}

          {/* List of existing adjustments */}
          <div className="flex items-center justify-between mb-4">
             <h3 className="font-semibold text-neutral-800">Current Adjustments</h3>
             {!isAdding && (
               <button 
                 onClick={() => setIsAdding(true)}
                 className="flex items-center gap-1.5 text-sm font-medium border border-neutral-300 bg-white text-neutral-700 px-3 py-1.5 hover:bg-neutral-50 rounded transition-colors"
               >
                 <Plus className="h-3.5 w-3.5" />
                 Add Adjustment
               </button>
             )}
          </div>

          {isLoadingAdjustments ? (
            <SkeletonLoader rows={4} />
          ) : adjustments.length === 0 ? (
            <div className="text-center py-12 bg-white border border-neutral-200 rounded text-neutral-400 text-sm">
              <p>No adjustments found for this run.</p>
              <br/>
              {!isAdding && <p>Click "Add Adjustment" to insert manual earnings or deductions.</p>}
            </div>
          ) : (
            <div className="overflow-hidden border border-neutral-200 rounded bg-white">
              <table className="w-full text-sm">
                <thead className="bg-neutral-50 border-b border-neutral-200">
                  <tr>
                    <th className="px-4 py-3 text-left font-medium text-neutral-600">Employee</th>
                    <th className="px-4 py-3 text-left font-medium text-neutral-600">Description</th>
                    <th className="px-4 py-3 text-left font-medium text-neutral-600">Type</th>
                    <th className="px-4 py-3 text-right font-medium text-neutral-600">Amount</th>
                    <th className="px-4 py-3"></th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-neutral-100">
                  {adjustments.map(adj => {
                    const isEarning = adj.type === 'earning'
                    return (
                      <tr key={adj.id} className="hover:bg-neutral-50/50">
                        <td className="px-4 py-3">
                          <div className="font-medium text-neutral-900">{adj.employee_id}</div>
                        </td>
                        <td className="px-4 py-3 text-neutral-700">{adj.description}</td>
                        <td className="px-4 py-3">
                           <div className="flex flex-col gap-0.5">
                             <span className={`inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium w-max ${
                               isEarning ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'
                             }`}>
                               {isEarning ? 'Earning' : 'Deduction'}
                             </span>
                             <span className="text-xs text-neutral-400 capitalize">{adj.nature.replace('_', '-')}</span>
                           </div>
                        </td>
                        <td className="px-4 py-3 text-right font-medium text-neutral-900">
                           {isEarning ? '+' : '-'}<CurrencyAmount centavos={adj.amount_centavos} />
                        </td>
                        <td className="px-4 py-3 text-right">
                          <ConfirmDestructiveDialog
                            title="Remove Adjustment?"
                            description="Are you sure you want to remove this adjustment? This cannot be undone."
                            onConfirm={() => handleDelete(adj.id)}
                            confirmWord="REMOVE"
                            confirmLabel="Remove Adjustment"
                          >
                           <button className="p-1.5 text-neutral-400 hover:text-red-600 hover:bg-neutral-100 rounded transition-colors" title="Remove">
                             <Trash2 className="h-4 w-4" />
                           </button>
                          </ConfirmDestructiveDialog>
                        </td>
                      </tr>
                    )
                  })}
                </tbody>
              </table>
            </div>
          )}

        </div>
        
        <div className="px-6 py-4 bg-white border-t border-neutral-200 flex justify-end shrink-0">
          <button 
            onClick={onClose}
            className="px-4 py-2 border border-neutral-300 text-neutral-700 font-medium text-sm rounded hover:bg-neutral-50 transition-colors"
          >
            Close
          </button>
        </div>
      </div>
    </div>
  )
}
