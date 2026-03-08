import { useState } from 'react'
import { useEditMode } from '../ReferenceTablesPage'
import { 
  useSssContributions, 
  useActiveSssContributions,
  useCreateSssContribution,
  useUpdateSssContribution,
  useDeleteSssContribution,
  type SssContribution 
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
import { formatCurrency } from '@/lib/formatters'
import { cn } from '@/lib/utils'

export default function SssContributionsTable(): JSX.Element {
  const { isEditMode } = useEditMode()
  const { data, isLoading } = useSssContributions()
  const { data: activeData } = useActiveSssContributions()
  const createMutation = useCreateSssContribution()
  const updateMutation = useUpdateSssContribution()
  const deleteMutation = useDeleteSssContribution()

  const [isDialogOpen, setIsDialogOpen] = useState(false)
  const [editingRow, setEditingRow] = useState<SssContribution | null>(null)
  const [selectedVersion, setSelectedVersion] = useState<string | 'all'>('all')
  const [showDeleteConfirm, setShowDeleteConfirm] = useState<SssContribution | null>(null)
  const [formData, setFormData] = useState({
    effective_date: '',
    salary_range_from: '',
    salary_range_to: '',
    monthly_salary_credit: '',
    employee_contribution: '',
    employer_contribution: '',
    ec_contribution: '',
  })

  const handleOpenDialog = (row?: SssContribution) => {
    if (!isEditMode) {
      toast.info('Enable editing to modify SSS contribution rows')
      return
    }
    if (row) {
      setEditingRow(row)
      setFormData({
        effective_date: row.effective_date?.substring(0, 10),
        salary_range_from: row.salary_range_from.toString(),
        salary_range_to: row.salary_range_to?.toString() || '',
        monthly_salary_credit: row.monthly_salary_credit.toString(),
        employee_contribution: row.employee_contribution.toString(),
        employer_contribution: row.employer_contribution.toString(),
        ec_contribution: row.ec_contribution.toString(),
      })
    } else {
      setEditingRow(null)
      setFormData({
        effective_date: new Date().toISOString().split('T')[0],
        salary_range_from: '',
        salary_range_to: '',
        monthly_salary_credit: '',
        employee_contribution: '',
        employer_contribution: '',
        ec_contribution: '30',
      })
    }
    setIsDialogOpen(true)
  }

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    
    const payload = {
      effective_date: formData.effective_date,
      salary_range_from: parseFloat(formData.salary_range_from),
      salary_range_to: formData.salary_range_to ? parseFloat(formData.salary_range_to) : null as unknown as undefined,
      monthly_salary_credit: parseFloat(formData.monthly_salary_credit),
      employee_contribution: parseFloat(formData.employee_contribution),
      employer_contribution: parseFloat(formData.employer_contribution),
      ec_contribution: parseFloat(formData.ec_contribution),
    }

    try {
      if (editingRow) {
        await updateMutation.mutateAsync({ id: editingRow.id, data: payload })
        toast.success('SSS contribution row updated successfully')
      } else {
        await createMutation.mutateAsync(payload as Omit<SssContribution, 'id' | 'created_at' | 'updated_at'>)
        toast.success('SSS contribution row created successfully')
      }
      setIsDialogOpen(false)
    } catch (_error) {
      toast.error('Failed to save SSS contribution row')
    }
  }

  const handleDeleteClick = (row: SssContribution) => {
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
      toast.success('SSS contribution row deleted successfully')
      setShowDeleteConfirm(null)
    } catch (_error) {
      toast.error('Failed to delete SSS contribution row')
    }
  }

  const versions = data?.versions || []
  const groupedData = data?.grouped || {}
  const displayData = selectedVersion === 'all' 
    ? data?.data || []
    : groupedData[selectedVersion] || []

  const activeEffectiveDate = activeData?.effective_as_of

  if (isLoading) {
    return <div className="text-center py-8 text-neutral-500">Loading SSS contribution table...</div>
  }

  return (
    <div className="space-y-4">
      {/* Active Version Info */}
      {activeEffectiveDate && (
        <div className="flex items-center justify-between text-sm">
          <div className="flex items-center gap-2 text-neutral-700 bg-neutral-50 px-4 py-2 rounded">
            <Eye className="h-4 w-4" />
            <span>Currently active: <strong>{activeEffectiveDate?.substring(0, 10)}</strong></span>
          </div>
          <span className="text-neutral-500">
            {activeData?.total_brackets} brackets
          </span>
        </div>
      )}

      {/* Toolbar */}
      <div className="flex items-center justify-between bg-neutral-50 p-3 rounded border border-neutral-200">
        <div className="flex items-center gap-3">
          <span className="text-sm text-neutral-500">Filter by version:</span>
          <select
            value={selectedVersion}
            onChange={(e) => setSelectedVersion(e.target.value)}
            className="text-sm border border-neutral-300 rounded px-3 py-1.5 bg-white focus:ring-1 focus:ring-neutral-400"
          >
            <option value="all">All Versions</option>
            {versions.map((v) => (
              <option key={v} value={v}>{v?.substring(0, 10)}</option>
            ))}
          </select>
        </div>
        <Button 
          onClick={() => handleOpenDialog()} 
          className="gap-2 h-9"
          disabled={!isEditMode}
          variant={isEditMode ? 'default' : 'outline'}
        >
          {isEditMode ? (
            <>
              <Plus className="h-4 w-4" />
              Add Row
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
        <div className="max-h-[500px] overflow-auto">
          <Table>
            <TableHeader className="sticky top-0 bg-white z-10">
              <TableRow className="bg-neutral-50">
                <TableHead className="font-semibold text-neutral-600 text-xs">Date</TableHead>
                <TableHead className="font-semibold text-neutral-600 text-xs">Salary Range</TableHead>
                <TableHead className="font-semibold text-neutral-600 text-xs">MSC</TableHead>
                <TableHead className="font-semibold text-neutral-600 text-xs">Employee</TableHead>
                <TableHead className="font-semibold text-neutral-600 text-xs">Employer</TableHead>
                <TableHead className="font-semibold text-neutral-600 text-xs">EC</TableHead>
                <TableHead className="w-20 font-semibold text-neutral-600 text-xs">Actions</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {displayData.length === 0 ? (
                <TableRow>
                  <TableCell colSpan={7} className="text-center py-8 text-neutral-500">
                    No SSS contribution rows found
                  </TableCell>
                </TableRow>
              ) : (
                displayData.map((row) => (
                  <TableRow 
                    key={row.id}
                    className={cn(
                      row.effective_date === activeEffectiveDate && 'bg-neutral-50'
                    )}
                  >
                    <TableCell className="whitespace-nowrap">
                      <div className="flex items-center gap-2">
                        <Calendar className="h-3 w-3 text-neutral-400" />
                        <span className="text-xs">{row.effective_date?.substring(0, 10)}</span>
                        {row.effective_date === activeEffectiveDate && (
                          <Badge variant="outline" className="text-[10px] px-1 border-neutral-300 text-neutral-700">Active</Badge>
                        )}
                      </div>
                    </TableCell>
                    <TableCell className="whitespace-nowrap text-sm">
                      {formatCurrency(row.salary_range_from)} - {row.salary_range_to ? formatCurrency(row.salary_range_to) : 'up'}
                    </TableCell>
                    <TableCell className="font-medium">{formatCurrency(row.monthly_salary_credit)}</TableCell>
                    <TableCell>{formatCurrency(row.employee_contribution)}</TableCell>
                    <TableCell>{formatCurrency(row.employer_contribution)}</TableCell>
                    <TableCell>{formatCurrency(row.ec_contribution)}</TableCell>
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
      </div>

      {/* Legend */}
      <div className="flex items-center gap-6 text-xs text-neutral-500">
        <div className="flex items-center gap-2">
          <span className="font-medium">MSC:</span> Monthly Salary Credit
        </div>
        <div className="flex items-center gap-2">
          <span className="font-medium">EC:</span> Employee Compensation
        </div>
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
              Are you sure you want to delete this SSS contribution row? This action cannot be undone.
              {showDeleteConfirm && (
                <div className="mt-4 p-3 bg-neutral-50 rounded text-sm">
                  <p><strong>Effective Date:</strong> {showDeleteConfirm.effective_date?.substring(0, 10)}</p>
                  <p><strong>Salary Range:</strong> {formatCurrency(showDeleteConfirm.salary_range_from)} - {showDeleteConfirm.salary_range_to ? formatCurrency(showDeleteConfirm.salary_range_to) : 'up'}</p>
                  <p><strong>MSC:</strong> {formatCurrency(showDeleteConfirm.monthly_salary_credit)}</p>
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

      {/* Form Dialog */}
      <Dialog open={isDialogOpen} onOpenChange={setIsDialogOpen}>
        <DialogContent className="max-w-xl">
          <DialogHeader>
            <DialogTitle>{editingRow ? 'Edit' : 'Add'} SSS Contribution Row</DialogTitle>
            <DialogDescription>
              Define a salary bracket with corresponding contributions.
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
              <p className="text-xs text-neutral-500">Payroll on/after this date uses this contribution table</p>
            </div>

            <div className="grid grid-cols-2 gap-4">
              <div className="space-y-1.5">
                <Label htmlFor="salary_range_from" className="text-sm font-medium text-neutral-700">
                  Salary From (₱) <span className="text-red-500">*</span>
                </Label>
                <Input
                  id="salary_range_from"
                  type="number"
                  step="0.01"
                  min="0"
                  placeholder="0.00"
                  value={formData.salary_range_from}
                  onChange={(e) => setFormData({ ...formData, salary_range_from: e.target.value })}
                  required
                  className="h-11"
                />
              </div>
              
              <div className="space-y-1.5">
                <Label htmlFor="salary_range_to" className="text-sm font-medium text-neutral-700">
                  Salary To (₱)
                </Label>
                <Input
                  id="salary_range_to"
                  type="number"
                  step="0.01"
                  min="0"
                  placeholder="No limit"
                  value={formData.salary_range_to}
                  onChange={(e) => setFormData({ ...formData, salary_range_to: e.target.value })}
                  className="h-11"
                />
                <p className="text-xs text-neutral-500">Leave blank for no upper limit</p>
              </div>
            </div>

            <div className="space-y-1.5">
              <Label htmlFor="monthly_salary_credit" className="text-sm font-medium text-neutral-700">
                Monthly Salary Credit (MSC) <span className="text-red-500">*</span>
              </Label>
              <Input
                id="monthly_salary_credit"
                type="number"
                step="0.01"
                min="0"
                placeholder="0.00"
                value={formData.monthly_salary_credit}
                onChange={(e) => setFormData({ ...formData, monthly_salary_credit: e.target.value })}
                required
                className="h-11"
              />
            </div>

            <div className="grid grid-cols-3 gap-4">
              <div className="space-y-1.5">
                <Label htmlFor="employee_contribution" className="text-sm font-medium text-neutral-700">
                  Employee (₱) <span className="text-red-500">*</span>
                </Label>
                <Input
                  id="employee_contribution"
                  type="number"
                  step="0.01"
                  min="0"
                  placeholder="0.00"
                  value={formData.employee_contribution}
                  onChange={(e) => setFormData({ ...formData, employee_contribution: e.target.value })}
                  required
                  className="h-11"
                />
              </div>
              
              <div className="space-y-1.5">
                <Label htmlFor="employer_contribution" className="text-sm font-medium text-neutral-700">
                  Employer (₱) <span className="text-red-500">*</span>
                </Label>
                <Input
                  id="employer_contribution"
                  type="number"
                  step="0.01"
                  min="0"
                  placeholder="0.00"
                  value={formData.employer_contribution}
                  onChange={(e) => setFormData({ ...formData, employer_contribution: e.target.value })}
                  required
                  className="h-11"
                />
              </div>
              
              <div className="space-y-1.5">
                <Label htmlFor="ec_contribution" className="text-sm font-medium text-neutral-700">
                  EC (₱) <span className="text-red-500">*</span>
                </Label>
                <Input
                  id="ec_contribution"
                  type="number"
                  step="0.01"
                  min="0"
                  placeholder="0.00"
                  value={formData.ec_contribution}
                  onChange={(e) => setFormData({ ...formData, ec_contribution: e.target.value })}
                  required
                  className="h-11"
                />
              </div>
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
                {editingRow ? 'Update' : 'Create'} Row
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>
    </div>
  )
}
