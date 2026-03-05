import { useState } from 'react'
import { useEditMode } from '../ReferenceTablesPage'
import { 
  useLoanTypes, 
  useCreateLoanType,
  useUpdateLoanType,
  useDeleteLoanType,
  type LoanType 
} from '@/hooks/useReferenceTables'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Badge } from '@/components/ui/badge'
import { Textarea } from '@/components/ui/textarea'
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
import { Plus, MoreHorizontal, Pencil, Trash2, Building2, Briefcase, Lock, AlertTriangle } from 'lucide-react'
import { toast } from 'sonner'
import { formatCurrency, formatPercent } from '@/lib/formatters'
import { cn } from '@/lib/utils'

export default function LoanTypesTable(): JSX.Element {
  const { isEditMode } = useEditMode()
  const { data, isLoading } = useLoanTypes()
  const createMutation = useCreateLoanType()
  const updateMutation = useUpdateLoanType()
  const deleteMutation = useDeleteLoanType()

  const [isDialogOpen, setIsDialogOpen] = useState(false)
  const [editingType, setEditingType] = useState<LoanType | null>(null)
  const [selectedCategory, setSelectedCategory] = useState<string | 'all'>('all')
  const [showDeleteConfirm, setShowDeleteConfirm] = useState<LoanType | null>(null)
  const [formData, setFormData] = useState({
    code: '',
    name: '',
    category: 'government',
    description: '',
    interest_rate_annual: '',
    max_term_months: '',
    max_amount_centavos: '',
    min_amount_centavos: '',
    subject_to_min_wage_protection: true,
    is_active: true,
  })

  const handleOpenDialog = (type?: LoanType) => {
    if (!isEditMode) {
      toast.info('Enable editing to modify loan types')
      return
    }
    if (type) {
      setEditingType(type)
      setFormData({
        code: type.code,
        name: type.name,
        category: type.category,
        description: type.description || '',
        interest_rate_annual: (type.interest_rate_annual * 100).toString(),
        max_term_months: type.max_term_months.toString(),
        max_amount_centavos: type.max_amount_centavos ? (type.max_amount_centavos / 100).toString() : '',
        min_amount_centavos: (type.min_amount_centavos / 100).toString(),
        subject_to_min_wage_protection: type.subject_to_min_wage_protection,
        is_active: type.is_active,
      })
    } else {
      setEditingType(null)
      setFormData({
        code: '',
        name: '',
        category: 'government',
        description: '',
        interest_rate_annual: '10',
        max_term_months: '24',
        max_amount_centavos: '',
        min_amount_centavos: '1000',
        subject_to_min_wage_protection: true,
        is_active: true,
      })
    }
    setIsDialogOpen(true)
  }

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    
    const payload = {
      code: formData.code,
      name: formData.name,
      category: formData.category as LoanType['category'],
      description: formData.description || null as unknown as undefined,
      interest_rate_annual: parseFloat(formData.interest_rate_annual) / 100,
      max_term_months: parseInt(formData.max_term_months),
      max_amount_centavos: formData.max_amount_centavos ? Math.round(parseFloat(formData.max_amount_centavos) * 100) : null as unknown as undefined,
      min_amount_centavos: Math.round(parseFloat(formData.min_amount_centavos) * 100),
      subject_to_min_wage_protection: formData.subject_to_min_wage_protection,
      is_active: formData.is_active,
    }

    try {
      if (editingType) {
        await updateMutation.mutateAsync({ id: editingType.id, data: payload })
        toast.success('Loan type updated successfully')
      } else {
        await createMutation.mutateAsync(payload as Omit<LoanType, 'id' | 'created_at' | 'updated_at'>)
        toast.success('Loan type created successfully')
      }
      setIsDialogOpen(false)
    } catch (_error) {
      toast.error('Failed to save loan type')
    }
  }

  const handleDeleteClick = (type: LoanType) => {
    if (!isEditMode) {
      toast.info('Enable editing to delete records')
      return
    }
    setShowDeleteConfirm(type)
  }

  const handleDeleteConfirm = async () => {
    if (!showDeleteConfirm) return
    
    try {
      await deleteMutation.mutateAsync(showDeleteConfirm.id)
      toast.success('Loan type deleted successfully')
      setShowDeleteConfirm(null)
    } catch (_error) {
      toast.error('Failed to delete loan type')
    }
  }

  const _categories = data?.categories || []
  const displayData = selectedCategory === 'all' 
    ? data?.data || []
    : data?.data?.filter(t => t.category === selectedCategory) || []

  if (isLoading) {
    return <div className="text-center py-8 text-gray-500">Loading loan types...</div>
  }

  return (
    <div className="space-y-4">
      {/* Toolbar */}
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-3">
          <span className="text-sm text-gray-500">Filter by category:</span>
          <select
            value={selectedCategory}
            onChange={(e) => setSelectedCategory(e.target.value)}
            className="text-sm border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500"
          >
            <option value="all">All Categories</option>
            <option value="government">Government</option>
            <option value="company">Company</option>
          </select>
        </div>
        <Button 
          onClick={() => handleOpenDialog()} 
          className="gap-2"
          disabled={!isEditMode}
          variant={isEditMode ? 'default' : 'outline'}
        >
          {isEditMode ? (
            <>
              <Plus className="h-4 w-4" />
              Add Loan Type
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
              <TableHead>Code</TableHead>
              <TableHead>Name</TableHead>
              <TableHead>Category</TableHead>
              <TableHead>Interest Rate</TableHead>
              <TableHead>Max Term</TableHead>
              <TableHead>Amount Range</TableHead>
              <TableHead>Status</TableHead>
              <TableHead className="w-20">Actions</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {displayData.length === 0 ? (
              <TableRow>
                <TableCell colSpan={8} className="text-center py-8 text-gray-500">
                  No loan types found
                </TableCell>
              </TableRow>
            ) : (
              displayData.map((type) => (
                <TableRow 
                  key={type.id}
                  className={cn(!type.is_active && 'opacity-60')}
                >
                  <TableCell className="font-medium">{type.code}</TableCell>
                  <TableCell>
                    <div>
                      <p className="font-medium">{type.name}</p>
                      {type.description && (
                        <p className="text-xs text-gray-500 truncate max-w-xs">{type.description}</p>
                      )}
                    </div>
                  </TableCell>
                  <TableCell>
                    {type.category === 'government' ? (
                      <Badge className="bg-purple-100 text-purple-700 gap-1">
                        <Building2 className="h-3 w-3" />
                        Government
                      </Badge>
                    ) : (
                      <Badge className="bg-blue-100 text-blue-700 gap-1">
                        <Briefcase className="h-3 w-3" />
                        Company
                      </Badge>
                    )}
                  </TableCell>
                  <TableCell>{formatPercent(type.interest_rate_annual)}</TableCell>
                  <TableCell>{type.max_term_months} months</TableCell>
                  <TableCell>
                    {formatCurrency(type.min_amount_centavos / 100)} - {type.max_amount_centavos ? formatCurrency(type.max_amount_centavos / 100) : 'No limit'}
                  </TableCell>
                  <TableCell>
                    {type.is_active ? (
                      <Badge variant="success">Active</Badge>
                    ) : (
                      <Badge variant="secondary">Inactive</Badge>
                    )}
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
                          <DropdownMenuItem onClick={() => handleOpenDialog(type)}>
                            <Pencil className="h-4 w-4 mr-2" />
                            Edit
                          </DropdownMenuItem>
                          <DropdownMenuItem 
                            onClick={() => handleDeleteClick(type)}
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
              Are you sure you want to delete this loan type? This action cannot be undone.
              {showDeleteConfirm && (
                <div className="mt-4 p-3 bg-gray-50 rounded-lg text-sm">
                  <p><strong>Code:</strong> {showDeleteConfirm.code}</p>
                  <p><strong>Name:</strong> {showDeleteConfirm.name}</p>
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
            <DialogTitle>{editingType ? 'Edit' : 'Add'} Loan Type</DialogTitle>
            <DialogDescription>
              Define a loan product with terms and conditions.
            </DialogDescription>
          </DialogHeader>
          
          <form onSubmit={handleSubmit} className="p-6 pt-2 space-y-5">
            <div className="grid grid-cols-2 gap-4">
              <div className="space-y-1.5">
                <Label htmlFor="code" className="text-sm font-medium text-gray-700">
                  Code <span className="text-red-500">*</span>
                </Label>
                <Input
                  id="code"
                  value={formData.code}
                  onChange={(e) => setFormData({ ...formData, code: e.target.value })}
                  placeholder="e.g., SSS_SALARY"
                  required
                  disabled={!!editingType}
                  className="h-11"
                />
              </div>
              
              <div className="space-y-1.5">
                <Label htmlFor="category" className="text-sm font-medium text-gray-700">
                  Category <span className="text-red-500">*</span>
                </Label>
                <select
                  id="category"
                  value={formData.category}
                  onChange={(e) => setFormData({ ...formData, category: e.target.value })}
                  className="w-full h-11 text-sm border border-gray-300 rounded-md px-3 py-2 bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                  required
                >
                  <option value="government">Government</option>
                  <option value="company">Company</option>
                </select>
              </div>
            </div>

            <div className="space-y-1.5">
              <Label htmlFor="name" className="text-sm font-medium text-gray-700">
                Name <span className="text-red-500">*</span>
              </Label>
              <Input
                id="name"
                value={formData.name}
                onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                placeholder="e.g., SSS Salary Loan"
                required
                className="h-11"
              />
            </div>

            <div className="space-y-1.5">
              <Label htmlFor="description" className="text-sm font-medium text-gray-700">
                Description
              </Label>
              <Textarea
                id="description"
                value={formData.description}
                onChange={(e) => setFormData({ ...formData, description: e.target.value })}
                placeholder="Loan description..."
                rows={3}
                className="min-h-[80px]"
              />
            </div>

            <div className="grid grid-cols-2 gap-4">
              <div className="space-y-1.5">
                <Label htmlFor="interest_rate_annual" className="text-sm font-medium text-gray-700">
                  Annual Interest (%) <span className="text-red-500">*</span>
                </Label>
                <Input
                  id="interest_rate_annual"
                  type="number"
                  step="0.01"
                  min="0"
                  max="100"
                  placeholder="e.g., 10"
                  value={formData.interest_rate_annual}
                  onChange={(e) => setFormData({ ...formData, interest_rate_annual: e.target.value })}
                  required
                  className="h-11"
                />
              </div>
              
              <div className="space-y-1.5">
                <Label htmlFor="max_term_months" className="text-sm font-medium text-gray-700">
                  Max Term (months) <span className="text-red-500">*</span>
                </Label>
                <Input
                  id="max_term_months"
                  type="number"
                  min="1"
                  placeholder="e.g., 24"
                  value={formData.max_term_months}
                  onChange={(e) => setFormData({ ...formData, max_term_months: e.target.value })}
                  required
                  className="h-11"
                />
              </div>
            </div>

            <div className="grid grid-cols-2 gap-4">
              <div className="space-y-1.5">
                <Label htmlFor="min_amount_centavos" className="text-sm font-medium text-gray-700">
                  Min Amount (₱) <span className="text-red-500">*</span>
                </Label>
                <Input
                  id="min_amount_centavos"
                  type="number"
                  step="0.01"
                  min="0"
                  placeholder="0.00"
                  value={formData.min_amount_centavos}
                  onChange={(e) => setFormData({ ...formData, min_amount_centavos: e.target.value })}
                  required
                  className="h-11"
                />
              </div>
              
              <div className="space-y-1.5">
                <Label htmlFor="max_amount_centavos" className="text-sm font-medium text-gray-700">
                  Max Amount (₱)
                </Label>
                <Input
                  id="max_amount_centavos"
                  type="number"
                  step="0.01"
                  min="0"
                  placeholder="No limit"
                  value={formData.max_amount_centavos}
                  onChange={(e) => setFormData({ ...formData, max_amount_centavos: e.target.value })}
                  className="h-11"
                />
              </div>
            </div>

            <div className="space-y-3">
              <label className="flex items-center gap-2.5 cursor-pointer">
                <input
                  type="checkbox"
                  checked={formData.subject_to_min_wage_protection}
                  onChange={(e) => setFormData({ ...formData, subject_to_min_wage_protection: e.target.checked })}
                  className="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                />
                <span className="text-sm font-medium text-gray-700">Subject to minimum wage protection (LN-007)</span>
              </label>

              <label className="flex items-center gap-2.5 cursor-pointer">
                <input
                  type="checkbox"
                  checked={formData.is_active}
                  onChange={(e) => setFormData({ ...formData, is_active: e.target.checked })}
                  className="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                />
                <span className="text-sm font-medium text-gray-700">Active</span>
              </label>
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
                {editingType ? 'Update' : 'Create'} Loan Type
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>
    </div>
  )
}
