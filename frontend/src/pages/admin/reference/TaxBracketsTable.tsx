import { firstErrorMessage } from '@/lib/errorHandler'
import { useState } from 'react'
import { useEditMode } from '../ReferenceTablesPage'
import { 
  useTaxBrackets, 
  useActiveTaxBrackets,
  useCreateTaxBracket,
  useUpdateTaxBracket,
  useDeleteTaxBracket,
  type TaxBracket 
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
import { Plus, MoreHorizontal, Pencil, Trash2, Eye, Calendar, AlertTriangle, Lock } from 'lucide-react'
import { toast } from 'sonner'
import { formatCurrency, formatPercent } from '@/lib/formatters'
import { cn } from '@/lib/utils'

export default function TaxBracketsTable(): JSX.Element {
  const { isEditMode } = useEditMode()
  const { data, isLoading } = useTaxBrackets()
  const { data: activeData } = useActiveTaxBrackets()
  const createMutation = useCreateTaxBracket()
  const updateMutation = useUpdateTaxBracket()
  const deleteMutation = useDeleteTaxBracket()

  const [isDialogOpen, setIsDialogOpen] = useState(false)
  const [editingBracket, setEditingBracket] = useState<TaxBracket | null>(null)
  const [selectedVersion, setSelectedVersion] = useState<string | 'all'>('all')
  const [showDeleteConfirm, setShowDeleteConfirm] = useState<TaxBracket | null>(null)
  const [formData, setFormData] = useState({
    effective_date: '',
    income_from: '',
    income_to: '',
    base_tax: '',
    excess_rate: '',
    notes: '',
  })

  const handleOpenDialog = (bracket?: TaxBracket) => {
    if (!isEditMode) {
      toast.info('Enable editing to modify tax brackets')
      return
    }
    
    if (bracket) {
      setEditingBracket(bracket)
      setFormData({
        effective_date: bracket.effective_date?.substring(0, 10),
        income_from: bracket.income_from.toString(),
        income_to: bracket.income_to?.toString() || '',
        base_tax: bracket.base_tax.toString(),
        excess_rate: (bracket.excess_rate * 100).toString(),
        notes: bracket.notes || '',
      })
    } else {
      setEditingBracket(null)
      setFormData({
        effective_date: new Date().toISOString().split('T')[0],
        income_from: '',
        income_to: '',
        base_tax: '',
        excess_rate: '',
        notes: '',
      })
    }
    setIsDialogOpen(true)
  }

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    
    const payload = {
      effective_date: formData.effective_date,
      income_from: parseFloat(formData.income_from),
      income_to: formData.income_to ? parseFloat(formData.income_to) : null as unknown as undefined,
      base_tax: parseFloat(formData.base_tax),
      excess_rate: parseFloat(formData.excess_rate) / 100,
      notes: formData.notes || null as unknown as undefined,
    }

    try {
      if (editingBracket) {
        await updateMutation.mutateAsync({ id: editingBracket.id, data: payload })
        toast.success('Tax bracket updated successfully')
      } else {
        await createMutation.mutateAsync(payload as Omit<TaxBracket, 'id' | 'created_at' | 'updated_at'>)
        toast.success('Tax bracket created successfully')
      }
      setIsDialogOpen(false)
    } catch (_error) {
      toast.error(firstErrorMessage(_error, 'Failed to save tax bracket'))
    }
  }

  const handleDeleteClick = (bracket: TaxBracket) => {
    if (!isEditMode) {
      toast.info('Enable editing to delete records')
      return
    }
    setShowDeleteConfirm(bracket)
  }

  const handleDeleteConfirm = async () => {
    if (!showDeleteConfirm) return
    
    try {
      await deleteMutation.mutateAsync(showDeleteConfirm.id)
      toast.success('Tax bracket deleted successfully')
      setShowDeleteConfirm(null)
    } catch (_error) {
      toast.error(firstErrorMessage(_error, 'Failed to delete tax bracket'))
    }
  }

  const versions = data?.versions || []
  const groupedData = data?.grouped || {}
  const displayData = selectedVersion === 'all' 
    ? data?.data || []
    : groupedData[selectedVersion] || []

  const activeEffectiveDate = activeData?.effective_as_of

  if (isLoading) {
    return <div className="text-center py-8 text-neutral-500">Loading tax brackets...</div>
  }

  return (
    <div className="space-y-4">
      {/* Active Version Info */}
      {activeEffectiveDate && (
        <div className="flex items-center gap-2 text-sm text-neutral-700 bg-neutral-50 px-4 py-2 rounded">
          <Eye className="h-4 w-4" />
          <span>Currently active version: <strong>{activeEffectiveDate?.substring(0, 10)}</strong></span>
          <span className="text-neutral-500 ml-2">(used for payroll computation)</span>
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
              Add Bracket
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
              <TableHead className="font-semibold text-neutral-600 text-xs">Effective Date</TableHead>
              <TableHead className="font-semibold text-neutral-600 text-xs">Annual Income From</TableHead>
              <TableHead className="font-semibold text-neutral-600 text-xs">Annual Income To</TableHead>
              <TableHead className="font-semibold text-neutral-600 text-xs">Base Tax</TableHead>
              <TableHead className="font-semibold text-neutral-600 text-xs">Excess Rate</TableHead>
              <TableHead className="w-20 font-semibold text-neutral-600 text-xs">Actions</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {displayData.length === 0 ? (
              <TableRow>
                <TableCell colSpan={6} className="text-center py-8 text-neutral-500">
                  No tax brackets found
                </TableCell>
              </TableRow>
            ) : (
              displayData.map((bracket) => (
                <TableRow 
                  key={bracket.id}
                  className={cn(
                    'hover:bg-neutral-50',
                    bracket.effective_date === activeEffectiveDate && 'bg-neutral-50'
                  )}
                >
                  <TableCell>
                    <div className="flex items-center gap-2">
                      <Calendar className="h-4 w-4 text-neutral-400 shrink-0" />
                      <span className="font-medium text-neutral-900">{bracket.effective_date?.substring(0, 10)}</span>
                      {bracket.effective_date === activeEffectiveDate && (
                        <Badge variant="outline" className="text-[10px] px-1.5 py-0 border-neutral-300 text-neutral-700">Active</Badge>
                      )}
                    </div>
                  </TableCell>
                  <TableCell>{formatCurrency(bracket.income_from, 'PHP', 0)}</TableCell>
                  <TableCell>
                    {bracket.income_to ? formatCurrency(bracket.income_to, 'PHP', 0) : 'No limit'}
                  </TableCell>
                  <TableCell>{formatCurrency(bracket.base_tax, 'PHP', 0)}</TableCell>
                  <TableCell>{formatPercent(bracket.excess_rate)}</TableCell>
                  <TableCell>
                    {isEditMode ? (
                      <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                          <Button variant="ghost" size="sm">
                            <MoreHorizontal className="h-4 w-4" />
                          </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                          <DropdownMenuItem onClick={() => handleOpenDialog(bracket)}>
                            <Pencil className="h-4 w-4 mr-2" />
                            Edit
                          </DropdownMenuItem>
                          <DropdownMenuItem 
                            onClick={() => handleDeleteClick(bracket)}
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

      {/* Form Dialog */}
      <Dialog open={isDialogOpen} onOpenChange={setIsDialogOpen}>
        <DialogContent className="max-w-xl">
          <DialogHeader>
            <DialogTitle>{editingBracket ? 'Edit' : 'Add'} Tax Bracket</DialogTitle>
            <DialogDescription>
              Tax brackets are versioned by effective date. Newer dates supersede older ones.
            </DialogDescription>
          </DialogHeader>
          
          <form onSubmit={handleSubmit} className="p-6 pt-2 space-y-5">
            <div className="grid grid-cols-2 gap-4">
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
                <p className="text-xs text-neutral-500">Payroll on/after this date uses this bracket</p>
              </div>
              
              <div className="space-y-1.5">
                <Label htmlFor="base_tax" className="text-sm font-medium text-neutral-700">
                  Base Tax (₱) <span className="text-red-500">*</span>
                </Label>
                <Input
                  id="base_tax"
                  type="number"
                  step="0.01"
                  min="0"
                  placeholder="0.00"
                  value={formData.base_tax}
                  onChange={(e) => setFormData({ ...formData, base_tax: e.target.value })}
                  required
                  className="h-11"
                />
              </div>
            </div>

            <div className="grid grid-cols-2 gap-4">
              <div className="space-y-1.5">
                <Label htmlFor="income_from" className="text-sm font-medium text-neutral-700">
                  Income From (₱) <span className="text-red-500">*</span>
                </Label>
                <Input
                  id="income_from"
                  type="number"
                  step="0.01"
                  min="0"
                  placeholder="0.00"
                  value={formData.income_from}
                  onChange={(e) => setFormData({ ...formData, income_from: e.target.value })}
                  required
                  className="h-11"
                />
              </div>
              
              <div className="space-y-1.5">
                <Label htmlFor="income_to" className="text-sm font-medium text-neutral-700">
                  Income To (₱)
                </Label>
                <Input
                  id="income_to"
                  type="number"
                  step="0.01"
                  min="0"
                  placeholder="No limit"
                  value={formData.income_to}
                  onChange={(e) => setFormData({ ...formData, income_to: e.target.value })}
                  className="h-11"
                />
                <p className="text-xs text-neutral-500">Leave blank for no upper limit (top bracket)</p>
              </div>
            </div>

            <div className="space-y-1.5">
              <Label htmlFor="excess_rate" className="text-sm font-medium text-neutral-700">
                Excess Rate (%) <span className="text-red-500">*</span>
              </Label>
              <Input
                id="excess_rate"
                type="number"
                step="0.01"
                min="0"
                max="100"
                placeholder="e.g., 15"
                value={formData.excess_rate}
                onChange={(e) => setFormData({ ...formData, excess_rate: e.target.value })}
                required
                className="h-11"
              />
              <p className="text-xs text-neutral-500">
                Percentage applied to income above the threshold
              </p>
            </div>

            <div className="space-y-1.5">
              <Label htmlFor="notes" className="text-sm font-medium text-neutral-700">
                Notes
              </Label>
              <Input
                id="notes"
                value={formData.notes}
                onChange={(e) => setFormData({ ...formData, notes: e.target.value })}
                placeholder="Legal basis or additional info (e.g., TRAIN Law §24(A)(2)(a))"
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
                {editingBracket ? 'Update' : 'Create'} Bracket
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>

      {/* Delete Confirmation Dialog */}
      <Dialog open={!!showDeleteConfirm} onOpenChange={() => setShowDeleteConfirm(null)}>
        <DialogContent className="max-w-md">
          <DialogHeader>
            <div className="flex items-center gap-3 text-red-600 mb-2">
              <AlertTriangle className="h-6 w-6" />
              <DialogTitle>Confirm Deletion</DialogTitle>
            </div>
            <DialogDescription className="text-neutral-600">
              Are you sure you want to delete this tax bracket? This action cannot be undone.
              {showDeleteConfirm && (
                <div className="mt-4 p-3 bg-neutral-50 rounded text-sm">
                  <p><strong>Effective Date:</strong> {showDeleteConfirm.effective_date?.substring(0, 10)}</p>
                  <p><strong>Income Range:</strong> {formatCurrency(showDeleteConfirm.income_from, 'PHP', 0)} - {showDeleteConfirm.income_to ? formatCurrency(showDeleteConfirm.income_to, 'PHP', 0) : 'No limit'}</p>
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
    </div>
  )
}
