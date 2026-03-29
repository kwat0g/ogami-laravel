import { firstErrorMessage } from '@/lib/errorHandler'
import { useState } from 'react'
import { useEditMode } from '../ReferenceTablesPage'
import { 
  usePagibigContributions, 
  useActivePagibigContribution,
  useCreatePagibigContribution,
  useUpdatePagibigContribution,
  useDeletePagibigContribution,
  type PagibigContribution 
} from '@/hooks/useReferenceTables'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Badge } from '@/components/ui/badge'
import { 
  Table, 
  TableBody, 
  TableCell, 
  TableHead, 
  TableHeader, 
  TableRow 
} from '@/components/ui/table'
import { 
  Dialog, 
  DialogContent, 
  DialogHeader, 
  DialogTitle, 
  DialogFooter,
  DialogDescription
} from '@/components/ui/dialog'
import { 
  DropdownMenu, 
  DropdownMenuContent, 
  DropdownMenuItem, 
  DropdownMenuTrigger 
} from '@/components/ui/dropdown-menu'
import { Plus, MoreHorizontal, Pencil, Trash2, Eye, Calendar, Lock, AlertTriangle } from 'lucide-react'
import { toast } from 'sonner'
import { formatCurrency, formatPercent } from '@/lib/formatters'
import { cn } from '@/lib/utils'

export default function PagibigContributionsTable(): JSX.Element {
  const { isEditMode } = useEditMode()
  const { data, isLoading } = usePagibigContributions()
  const { data: activeData } = useActivePagibigContribution()
  const createMutation = useCreatePagibigContribution()
  const updateMutation = useUpdatePagibigContribution()
  const deleteMutation = useDeletePagibigContribution()

  const [isDialogOpen, setIsDialogOpen] = useState(false)
  const [editingRow, setEditingRow] = useState<PagibigContribution | null>(null)
  const [showDeleteConfirm, setShowDeleteConfirm] = useState<PagibigContribution | null>(null)
  const [formData, setFormData] = useState({
    effective_date: '',
    salary_threshold: '',
    employee_rate_below: '',
    employee_rate_above: '',
    employee_cap_monthly: '',
    employer_rate: '',
    legal_basis: '',
  })

  const handleOpenDialog = (row?: PagibigContribution) => {
    if (!isEditMode) {
      toast.info('Enable editing to modify Pag-IBIG contribution rates')
      return
    }
    if (row) {
      setEditingRow(row)
      setFormData({
        effective_date: row.effective_date?.substring(0, 10),
        salary_threshold: row.salary_threshold.toString(),
        employee_rate_below: (row.employee_rate_below * 100).toString(),
        employee_rate_above: (row.employee_rate_above * 100).toString(),
        employee_cap_monthly: row.employee_cap_monthly.toString(),
        employer_rate: (row.employer_rate * 100).toString(),
        legal_basis: row.legal_basis || '',
      })
    } else {
      setEditingRow(null)
      setFormData({
        effective_date: new Date().toISOString().split('T')[0],
        salary_threshold: '1500',
        employee_rate_below: '1',
        employee_rate_above: '2',
        employee_cap_monthly: '100',
        employer_rate: '2',
        legal_basis: '',
      })
    }
    setIsDialogOpen(true)
  }

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    
    const payload = {
      effective_date: formData.effective_date,
      salary_threshold: parseFloat(formData.salary_threshold),
      employee_rate_below: parseFloat(formData.employee_rate_below) / 100,
      employee_rate_above: parseFloat(formData.employee_rate_above) / 100,
      employee_cap_monthly: parseFloat(formData.employee_cap_monthly),
      employer_rate: parseFloat(formData.employer_rate) / 100,
      legal_basis: formData.legal_basis || null as unknown as undefined,
    }

    try {
      if (editingRow) {
        await updateMutation.mutateAsync({ id: editingRow.id, data: payload })
        toast.success('Pag-IBIG contribution rate updated successfully')
      } else {
        await createMutation.mutateAsync(payload as Omit<PagibigContribution, 'id' | 'created_at' | 'updated_at'>)
        toast.success('Pag-IBIG contribution rate created successfully')
      }
      setIsDialogOpen(false)
    } catch (_error) {
      toast.error(firstErrorMessage(_error, 'Failed to save Pag-IBIG contribution rate'))
    }
  }

  const handleDeleteClick = (row: PagibigContribution) => {
    if (!isEditMode) {
      toast.info('Enable editing to delete records')
      return
    }
    setShowDeleteConfirm(row)
  }

  const handleDeleteConfirm = async () => {
    if (!showDeleteConfirm) return
    
    try {
      await deleteMutation.mutateAsync(showDeleteConfirm.id)
      toast.success('Pag-IBIG contribution rate deleted successfully')
      setShowDeleteConfirm(null)
    } catch (_error) {
      toast.error(firstErrorMessage(_error, 'Failed to delete Pag-IBIG contribution rate'))
    }
  }

  const rows = data?.data || []
  const activeEffectiveDate = activeData?.effective_as_of
  const activeRate = activeData?.data

  if (isLoading) {
    return <div className="text-center py-8 text-neutral-500">Loading Pag-IBIG contribution table...</div>
  }

  return (
    <div className="space-y-4">
      {/* Active Rate Info Card */}
      {activeRate && (
        <div className="bg-neutral-50 border border-neutral-200 rounded p-4">
          <div className="flex items-center gap-2 mb-3">
            <Eye className="h-5 w-5 text-neutral-600" />
            <h3 className="font-medium text-neutral-900">Currently Active Rate</h3>
            <Badge variant="outline" className="border-neutral-300 text-neutral-700">Active</Badge>
          </div>
          <div className="grid grid-cols-3 gap-4 text-sm">
            <div>
              <p className="text-neutral-500">Effective Date</p>
              <p className="font-medium text-neutral-900">{activeRate.effective_date?.substring(0, 10)}</p>
            </div>
            <div>
              <p className="text-neutral-500">Salary Threshold</p>
              <p className="font-medium text-neutral-900">{formatCurrency(activeRate.salary_threshold)}</p>
            </div>
            <div>
              <p className="text-neutral-500">Employee Cap</p>
              <p className="font-medium text-neutral-900">{formatCurrency(activeRate.employee_cap_monthly)}/mo</p>
            </div>
          </div>
          <div className="mt-3 grid grid-cols-3 gap-4 text-sm">
            <div>
              <p className="text-neutral-500">Employee Rate ({'≤'} threshold)</p>
              <p className="font-medium text-neutral-900">{formatPercent(activeRate.employee_rate_below)}</p>
            </div>
            <div>
              <p className="text-neutral-500">Employee Rate ({'>'} threshold)</p>
              <p className="font-medium text-neutral-900">{formatPercent(activeRate.employee_rate_above)}</p>
            </div>
            <div>
              <p className="text-neutral-500">Employer Rate</p>
              <p className="font-medium text-neutral-900">{formatPercent(activeRate.employer_rate)}</p>
            </div>
          </div>
        </div>
      )}

      {/* Toolbar */}
      <div className="flex items-center justify-end">
        <Button 
          onClick={() => handleOpenDialog()} 
          className="gap-2"
          disabled={!isEditMode}
          variant={isEditMode ? 'default' : 'outline'}
        >
          {isEditMode ? (
            <>
              <Plus className="h-4 w-4" />
              Add Rate Schedule
            </>
          ) : (
            <>
              <Lock className="h-4 w-4" />
              Editing Locked
            </>
          )}
        </Button>
      </div>

      {/* Table */}
      <div className="border border-neutral-200 rounded overflow-hidden">
        <Table>
          <TableHeader>
            <TableRow className="bg-neutral-50">
              <TableHead className="text-xs font-semibold text-neutral-600">Effective Date</TableHead>
              <TableHead className="text-xs font-semibold text-neutral-600">Salary Threshold</TableHead>
              <TableHead className="text-xs font-semibold text-neutral-600">Employee {'≤'} Threshold</TableHead>
              <TableHead className="text-xs font-semibold text-neutral-600">Employee {'>'} Threshold</TableHead>
              <TableHead className="text-xs font-semibold text-neutral-600">Employee Cap</TableHead>
              <TableHead className="text-xs font-semibold text-neutral-600">Employer Rate</TableHead>
              <TableHead className="w-20 text-xs font-semibold text-neutral-600">Actions</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {rows.length === 0 ? (
              <TableRow>
                <TableCell colSpan={7} className="text-center py-8 text-neutral-500">
                  No Pag-IBIG contribution rates found
                </TableCell>
              </TableRow>
            ) : (
              rows.map((row) => (
                <TableRow 
                  key={row.id}
                  className={cn(
                    row.effective_date === activeEffectiveDate && 'bg-neutral-50'
                  )}
                >
                  <TableCell>
                    <div className="flex items-center gap-2">
                      <Calendar className="h-4 w-4 text-neutral-400" />
                      {row.effective_date?.substring(0, 10)}
                      {row.effective_date === activeEffectiveDate && (
                        <Badge variant="outline" className="text-xs border-neutral-300 text-neutral-700">Active</Badge>
                      )}
                    </div>
                  </TableCell>
                  <TableCell className="font-medium">{formatCurrency(row.salary_threshold)}</TableCell>
                  <TableCell>{formatPercent(row.employee_rate_below)}</TableCell>
                  <TableCell>{formatPercent(row.employee_rate_above)}</TableCell>
                  <TableCell>{formatCurrency(row.employee_cap_monthly)}/mo</TableCell>
                  <TableCell>{formatPercent(row.employer_rate)}</TableCell>
                  <TableCell>
                    {isEditMode ? (
                      <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                          <Button variant="ghost" size="sm">
                            <MoreHorizontal className="h-4 w-4" />
                          </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                          <DropdownMenuItem onClick={() => handleOpenDialog(row)}>
                            <Pencil className="h-4 w-4 mr-2" />
                            Edit
                          </DropdownMenuItem>
                          <DropdownMenuItem 
                            onClick={() => handleDeleteClick(row)}
                            className="text-red-600"
                          >
                            <Trash2 className="h-4 w-4 mr-2" />
                            Delete
                          </DropdownMenuItem>
                        </DropdownMenuContent>
                      </DropdownMenu>
                    ) : (
                      <Button variant="ghost" size="sm" disabled className="opacity-50">
                        <Lock className="h-4 w-4" />
                      </Button>
                    )}
                  </TableCell>
                </TableRow>
              ))
            )}
          </TableBody>
        </Table>
      </div>

      {/* Delete Confirmation Dialog */}
      <Dialog open={!!showDeleteConfirm} onOpenChange={() => setShowDeleteConfirm(null)}>
        <DialogContent className="max-w-md">
          <DialogHeader>
            <div className="flex items-center gap-3 text-red-600 mb-2">
              <AlertTriangle className="h-6 w-6" />
              <DialogTitle>Confirm Deletion</DialogTitle>
            </div>
            <DialogDescription className="text-neutral-600">
              Are you sure you want to delete this Pag-IBIG contribution rate? This action cannot be undone.
              {showDeleteConfirm && (
                <div className="mt-4 p-3 bg-neutral-50 rounded text-sm">
                  <p><strong>Effective Date:</strong> {showDeleteConfirm.effective_date?.substring(0, 10)}</p>
                  <p><strong>Salary Threshold:</strong> {formatCurrency(showDeleteConfirm.salary_threshold)}</p>
                </div>
              )}
            </DialogDescription>
          </DialogHeader>
          <DialogFooter className="gap-2">
            <Button 
              variant="outline" 
              onClick={() => setShowDeleteConfirm(null)}
              className="h-11"
            >
              Cancel
            </Button>
            <Button 
              variant="destructive"
              onClick={handleDeleteConfirm}
              disabled={deleteMutation.isPending}
              className="h-11"
            >
              {deleteMutation.isPending ? 'Deleting...' : 'Delete'}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {/* Info */}
      <div className="bg-neutral-50 rounded p-4 text-sm text-neutral-800">
        <p className="font-medium mb-1">How Pag-IBIG Contributions Work:</p>
        <ul className="list-disc list-inside space-y-1 text-neutral-700">
          <li>Employee contributes 1% if monthly basic ≤ threshold, 2% if above</li>
          <li>Employee contribution is capped at ₱100/month (₱50 semi-monthly)</li>
          <li>Employer always contributes 2%, no cap</li>
        </ul>
      </div>

      {/* Form Dialog */}
      <Dialog open={isDialogOpen} onOpenChange={setIsDialogOpen}>
        <DialogContent className="max-w-xl">
          <DialogHeader>
            <DialogTitle>{editingRow ? 'Edit' : 'Add'} Pag-IBIG Contribution Rate</DialogTitle>
            <DialogDescription>
              Define contribution rates for Pag-IBIG (HDMF).
            </DialogDescription>
          </DialogHeader>
          
          <form onSubmit={handleSubmit} className="p-6 pt-2 space-y-5">
            <div className="space-y-1.5">
              <Label htmlFor="effective_date" className="text-sm font-medium text-neutral-700">
                Effective Date <span className="text-red-500">*</span>
              </Label>
              <Input
                id="effective_date"
                type="date"
                value={formData.effective_date}
                onChange={(e) => setFormData({ ...formData, effective_date: e.target.value })}
                required
                className="h-11"
              />
            </div>

            <div className="space-y-1.5">
              <Label htmlFor="salary_threshold" className="text-sm font-medium text-neutral-700">
                Salary Threshold (₱) <span className="text-red-500">*</span>
              </Label>
              <Input
                id="salary_threshold"
                type="number"
                step="0.01"
                min="0"
                placeholder="e.g., 1500"
                value={formData.salary_threshold}
                onChange={(e) => setFormData({ ...formData, salary_threshold: e.target.value })}
                required
                className="h-11"
              />
              <p className="text-xs text-neutral-500">
                Monthly basic salary threshold for different employee rates
              </p>
            </div>

            <div className="grid grid-cols-2 gap-4">
              <div className="space-y-1.5">
                <Label htmlFor="employee_rate_below" className="text-sm font-medium text-neutral-700">
                  Employee Rate {'≤'} Threshold (%) <span className="text-red-500">*</span>
                </Label>
                <Input
                  id="employee_rate_below"
                  type="number"
                  step="0.01"
                  min="0"
                  max="100"
                  placeholder="e.g., 1"
                  value={formData.employee_rate_below}
                  onChange={(e) => setFormData({ ...formData, employee_rate_below: e.target.value })}
                  required
                  className="h-11"
                />
              </div>
              
              <div className="space-y-1.5">
                <Label htmlFor="employee_rate_above" className="text-sm font-medium text-neutral-700">
                  Employee Rate {'>'} Threshold (%) <span className="text-red-500">*</span>
                </Label>
                <Input
                  id="employee_rate_above"
                  type="number"
                  step="0.01"
                  min="0"
                  max="100"
                  placeholder="e.g., 2"
                  value={formData.employee_rate_above}
                  onChange={(e) => setFormData({ ...formData, employee_rate_above: e.target.value })}
                  required
                  className="h-11"
                />
              </div>
            </div>

            <div className="grid grid-cols-2 gap-4">
              <div className="space-y-1.5">
                <Label htmlFor="employee_cap_monthly" className="text-sm font-medium text-neutral-700">
                  Employee Cap Monthly (₱) <span className="text-red-500">*</span>
                </Label>
                <Input
                  id="employee_cap_monthly"
                  type="number"
                  step="0.01"
                  min="0"
                  placeholder="e.g., 100"
                  value={formData.employee_cap_monthly}
                  onChange={(e) => setFormData({ ...formData, employee_cap_monthly: e.target.value })}
                  required
                  className="h-11"
                />
              </div>
              
              <div className="space-y-1.5">
                <Label htmlFor="employer_rate" className="text-sm font-medium text-neutral-700">
                  Employer Rate (%) <span className="text-red-500">*</span>
                </Label>
                <Input
                  id="employer_rate"
                  type="number"
                  step="0.01"
                  min="0"
                  max="100"
                  placeholder="e.g., 2"
                  value={formData.employer_rate}
                  onChange={(e) => setFormData({ ...formData, employer_rate: e.target.value })}
                  required
                  className="h-11"
                />
              </div>
            </div>

            <div className="space-y-1.5">
              <Label htmlFor="legal_basis" className="text-sm font-medium text-neutral-700">
                Legal Basis
              </Label>
              <Input
                id="legal_basis"
                value={formData.legal_basis}
                onChange={(e) => setFormData({ ...formData, legal_basis: e.target.value })}
                placeholder="e.g., HDMF Circular No. xxx"
                className="h-11"
              />
            </div>

            <DialogFooter>
              <Button 
                type="button" 
                variant="outline" 
                onClick={() => setIsDialogOpen(false)}
                className="h-11 px-6"
              >
                Cancel
              </Button>
              <Button 
                type="submit" 
                disabled={createMutation.isPending || updateMutation.isPending}
                className="h-11 px-6"
              >
                {editingRow ? 'Update' : 'Create'} Rate
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>
    </div>
  )
}
