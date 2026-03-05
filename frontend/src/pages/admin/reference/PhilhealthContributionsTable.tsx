import { useState } from 'react'
import { useEditMode } from '../ReferenceTablesPage'
import { 
  usePhilhealthContributions, 
  useActivePhilhealthContribution,
  useCreatePhilhealthContribution,
  useUpdatePhilhealthContribution,
  useDeletePhilhealthContribution,
  type PhilhealthContribution 
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
import { Plus, MoreHorizontal, Pencil, Trash2, Eye, Calendar, Info, Lock, AlertTriangle } from 'lucide-react'
import { toast } from 'sonner'
import { formatCurrency, formatPercent } from '@/lib/formatters'
import { cn } from '@/lib/utils'

export default function PhilhealthContributionsTable(): JSX.Element {
  const { isEditMode } = useEditMode()
  const { data, isLoading } = usePhilhealthContributions()
  const { data: activeData } = useActivePhilhealthContribution()
  const createMutation = useCreatePhilhealthContribution()
  const updateMutation = useUpdatePhilhealthContribution()
  const deleteMutation = useDeletePhilhealthContribution()

  const [isDialogOpen, setIsDialogOpen] = useState(false)
  const [editingRow, setEditingRow] = useState<PhilhealthContribution | null>(null)
  const [showDeleteConfirm, setShowDeleteConfirm] = useState<PhilhealthContribution | null>(null)
  const [formData, setFormData] = useState({
    effective_date: '',
    salary_floor: '',
    salary_ceiling: '',
    premium_rate: '',
    min_monthly_premium: '',
    max_monthly_premium: '',
    legal_basis: '',
  })

  const handleOpenDialog = (row?: PhilhealthContribution) => {
    if (!isEditMode) {
      toast.info('Enable editing to modify PhilHealth premium rates')
      return
    }
    if (row) {
      setEditingRow(row)
      setFormData({
        effective_date: row.effective_date?.substring(0, 10),
        salary_floor: row.salary_floor?.toString() || '',
        salary_ceiling: row.salary_ceiling?.toString() || '',
        premium_rate: (row.premium_rate * 100).toString(),
        min_monthly_premium: row.min_monthly_premium.toString(),
        max_monthly_premium: row.max_monthly_premium.toString(),
        legal_basis: row.legal_basis || '',
      })
    } else {
      setEditingRow(null)
      setFormData({
        effective_date: new Date().toISOString().split('T')[0],
        salary_floor: '',
        salary_ceiling: '',
        premium_rate: '5',
        min_monthly_premium: '500',
        max_monthly_premium: '5000',
        legal_basis: '',
      })
    }
    setIsDialogOpen(true)
  }

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    
    const payload = {
      effective_date: formData.effective_date,
      salary_floor: formData.salary_floor ? parseFloat(formData.salary_floor) : null as unknown as undefined,
      salary_ceiling: formData.salary_ceiling ? parseFloat(formData.salary_ceiling) : null as unknown as undefined,
      premium_rate: parseFloat(formData.premium_rate) / 100,
      min_monthly_premium: parseFloat(formData.min_monthly_premium),
      max_monthly_premium: parseFloat(formData.max_monthly_premium),
      legal_basis: formData.legal_basis || null as unknown as undefined,
    }

    try {
      if (editingRow) {
        await updateMutation.mutateAsync({ id: editingRow.id, data: payload })
        toast.success('PhilHealth premium rate updated successfully')
      } else {
        await createMutation.mutateAsync(payload as Omit<PhilhealthContribution, 'id' | 'created_at' | 'updated_at'>)
        toast.success('PhilHealth premium rate created successfully')
      }
      setIsDialogOpen(false)
    } catch (_error) {
      toast.error('Failed to save PhilHealth premium rate')
    }
  }

  const handleDeleteClick = (row: PhilhealthContribution) => {
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
      toast.success('PhilHealth premium rate deleted successfully')
      setShowDeleteConfirm(null)
    } catch (_error) {
      toast.error('Failed to delete PhilHealth premium rate')
    }
  }

  const rows = data?.data || []
  const activeEffectiveDate = activeData?.effective_as_of
  const activeRate = activeData?.data

  if (isLoading) {
    return <div className="text-center py-8 text-gray-500">Loading PhilHealth premium table...</div>
  }

  return (
    <div className="space-y-4">
      {/* Active Rate Info Card */}
      {activeRate && (
        <div className="bg-green-50 border border-green-200 rounded-lg p-4">
          <div className="flex items-center gap-2 mb-3">
            <Eye className="h-5 w-5 text-green-600" />
            <h3 className="font-semibold text-green-900">Currently Active Rate</h3>
            <Badge variant="success">Active</Badge>
          </div>
          <div className="grid grid-cols-4 gap-4 text-sm">
            <div>
              <p className="text-gray-500">Effective Date</p>
              <p className="font-medium text-green-900">{activeRate.effective_date?.substring(0, 10)}</p>
            </div>
            <div>
              <p className="text-gray-500">Premium Rate</p>
              <p className="font-medium text-green-900">{formatPercent(activeRate.premium_rate)}</p>
            </div>
            <div>
              <p className="text-gray-500">Min Premium</p>
              <p className="font-medium text-green-900">{formatCurrency(activeRate.min_monthly_premium)}</p>
            </div>
            <div>
              <p className="text-gray-500">Max Premium</p>
              <p className="font-medium text-green-900">{formatCurrency(activeRate.max_monthly_premium)}</p>
            </div>
          </div>
          {activeRate.legal_basis && (
            <p className="mt-3 text-xs text-green-700">
              <Info className="h-3 w-3 inline mr-1" />
              {activeRate.legal_basis}
            </p>
          )}
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
      <div className="border rounded-lg overflow-hidden">
        <Table>
          <TableHeader>
            <TableRow className="bg-gray-50">
              <TableHead>Effective Date</TableHead>
              <TableHead>Salary Range</TableHead>
              <TableHead>Premium Rate</TableHead>
              <TableHead>Min Premium</TableHead>
              <TableHead>Max Premium</TableHead>
              <TableHead>Legal Basis</TableHead>
              <TableHead className="w-20">Actions</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {rows.length === 0 ? (
              <TableRow>
                <TableCell colSpan={7} className="text-center py-8 text-gray-500">
                  No PhilHealth premium rates found
                </TableCell>
              </TableRow>
            ) : (
              rows.map((row) => (
                <TableRow 
                  key={row.id}
                  className={cn(
                    row.effective_date === activeEffectiveDate && 'bg-green-50/50'
                  )}
                >
                  <TableCell>
                    <div className="flex items-center gap-2">
                      <Calendar className="h-4 w-4 text-gray-400" />
                      {row.effective_date?.substring(0, 10)}
                      {row.effective_date === activeEffectiveDate && (
                        <Badge variant="success" className="text-xs">Active</Badge>
                      )}
                    </div>
                  </TableCell>
                  <TableCell>
                    {row.salary_floor !== null || row.salary_ceiling !== null ? (
                      <span>
                        {row.salary_floor !== null ? formatCurrency(row.salary_floor) : 'Any'} - {row.salary_ceiling !== null ? formatCurrency(row.salary_ceiling) : 'Any'}
                      </span>
                    ) : (
                      <span className="text-gray-400">All salaries</span>
                    )}
                  </TableCell>
                  <TableCell className="font-medium">{formatPercent(row.premium_rate)}</TableCell>
                  <TableCell>{formatCurrency(row.min_monthly_premium)}</TableCell>
                  <TableCell>{formatCurrency(row.max_monthly_premium)}</TableCell>
                  <TableCell className="max-w-xs truncate text-sm text-gray-500">
                    {row.legal_basis || '-'}
                  </TableCell>
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
            <DialogDescription className="text-gray-600">
              Are you sure you want to delete this PhilHealth premium rate? This action cannot be undone.
              {showDeleteConfirm && (
                <div className="mt-4 p-3 bg-gray-50 rounded-lg text-sm">
                  <p><strong>Effective Date:</strong> {showDeleteConfirm.effective_date?.substring(0, 10)}</p>
                  <p><strong>Premium Rate:</strong> {(showDeleteConfirm.premium_rate * 100).toFixed(2)}%</p>
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
      <div className="bg-blue-50 rounded-lg p-4 text-sm text-blue-800">
        <p className="font-medium mb-1">How PhilHealth Premiums Work:</p>
        <ul className="list-disc list-inside space-y-1 text-blue-700">
          <li>Premium = Basic Salary × Premium Rate</li>
          <li>Employee pays 50%, Employer pays 50%</li>
          <li>Semi-monthly deduction = Monthly premium ÷ 2</li>
          <li>Minimum and maximum premiums apply regardless of salary</li>
        </ul>
      </div>

      {/* Form Dialog */}
      <Dialog open={isDialogOpen} onOpenChange={setIsDialogOpen}>
        <DialogContent className="max-w-xl">
          <DialogHeader>
            <DialogTitle>{editingRow ? 'Edit' : 'Add'} PhilHealth Premium Rate</DialogTitle>
            <DialogDescription>
              Define premium rates for PhilHealth contributions.
            </DialogDescription>
          </DialogHeader>
          
          <form onSubmit={handleSubmit} className="p-6 pt-2 space-y-5">
            <div className="space-y-1.5">
              <Label htmlFor="effective_date" className="text-sm font-medium text-gray-700">
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

            <div className="grid grid-cols-2 gap-4">
              <div className="space-y-1.5">
                <Label htmlFor="salary_floor" className="text-sm font-medium text-gray-700">
                  Salary Floor (₱)
                </Label>
                <Input
                  id="salary_floor"
                  type="number"
                  step="0.01"
                  min="0"
                  placeholder="Optional"
                  value={formData.salary_floor}
                  onChange={(e) => setFormData({ ...formData, salary_floor: e.target.value })}
                  className="h-11"
                />
              </div>
              
              <div className="space-y-1.5">
                <Label htmlFor="salary_ceiling" className="text-sm font-medium text-gray-700">
                  Salary Ceiling (₱)
                </Label>
                <Input
                  id="salary_ceiling"
                  type="number"
                  step="0.01"
                  min="0"
                  placeholder="Optional"
                  value={formData.salary_ceiling}
                  onChange={(e) => setFormData({ ...formData, salary_ceiling: e.target.value })}
                  className="h-11"
                />
              </div>
            </div>

            <div className="space-y-1.5">
              <Label htmlFor="premium_rate" className="text-sm font-medium text-gray-700">
                Premium Rate (%) <span className="text-red-500">*</span>
              </Label>
              <Input
                id="premium_rate"
                type="number"
                step="0.01"
                min="0"
                max="100"
                placeholder="e.g., 5"
                value={formData.premium_rate}
                onChange={(e) => setFormData({ ...formData, premium_rate: e.target.value })}
                required
                className="h-11"
              />
              <p className="text-xs text-gray-500">
                Total premium rate (employee + employer combined)
              </p>
            </div>

            <div className="grid grid-cols-2 gap-4">
              <div className="space-y-1.5">
                <Label htmlFor="min_monthly_premium" className="text-sm font-medium text-gray-700">
                  Min Monthly (₱) <span className="text-red-500">*</span>
                </Label>
                <Input
                  id="min_monthly_premium"
                  type="number"
                  step="0.01"
                  min="0"
                  placeholder="0.00"
                  value={formData.min_monthly_premium}
                  onChange={(e) => setFormData({ ...formData, min_monthly_premium: e.target.value })}
                  required
                  className="h-11"
                />
              </div>
              
              <div className="space-y-1.5">
                <Label htmlFor="max_monthly_premium" className="text-sm font-medium text-gray-700">
                  Max Monthly (₱) <span className="text-red-500">*</span>
                </Label>
                <Input
                  id="max_monthly_premium"
                  type="number"
                  step="0.01"
                  min="0"
                  placeholder="0.00"
                  value={formData.max_monthly_premium}
                  onChange={(e) => setFormData({ ...formData, max_monthly_premium: e.target.value })}
                  required
                  className="h-11"
                />
              </div>
            </div>

            <div className="space-y-1.5">
              <Label htmlFor="legal_basis" className="text-sm font-medium text-gray-700">
                Legal Basis
              </Label>
              <Input
                id="legal_basis"
                value={formData.legal_basis}
                onChange={(e) => setFormData({ ...formData, legal_basis: e.target.value })}
                placeholder="e.g., PhilHealth Circular 2023-0009"
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
