import { firstErrorMessage } from '@/lib/errorHandler'
import { useState } from 'react'
import { useEditMode } from '../ReferenceTablesPage'
import { 
  useMinimumWageRates, 
  useCreateMinimumWageRate,
  useUpdateMinimumWageRate,
  useDeleteMinimumWageRate,
  type MinimumWageRate 
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
import { Plus, MoreHorizontal, Pencil, Trash2, MapPin, Lock, AlertTriangle } from 'lucide-react'
import { toast } from 'sonner'
import { formatCurrency } from '@/lib/formatters'

const REGIONS = ['NCR', 'CAR', 'I', 'II', 'III', 'IV-A', 'IV-B', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII', 'XIII', 'BARMM']

export default function MinimumWageRatesTable(): JSX.Element {
  const { isEditMode } = useEditMode()
  const { data, isLoading } = useMinimumWageRates()
  const createMutation = useCreateMinimumWageRate()
  const updateMutation = useUpdateMinimumWageRate()
  const deleteMutation = useDeleteMinimumWageRate()

  const [isDialogOpen, setIsDialogOpen] = useState(false)
  const [editingRate, setEditingRate] = useState<MinimumWageRate | null>(null)
  const [selectedRegion, setSelectedRegion] = useState<string | 'all'>('all')
  const [showDeleteConfirm, setShowDeleteConfirm] = useState<MinimumWageRate | null>(null)
  const [formData, setFormData] = useState({
    effective_date: '',
    region: '',
    daily_rate: '',
    wage_order_reference: '',
  })

  const handleOpenDialog = (rate?: MinimumWageRate) => {
    if (!isEditMode) {
      toast.info('Enable editing to modify minimum wage rates')
      return
    }
    if (rate) {
      setEditingRate(rate)
      setFormData({
        effective_date: rate.effective_date?.substring(0, 10),
        region: rate.region,
        daily_rate: rate.daily_rate.toString(),
        wage_order_reference: rate.wage_order_reference || '',
      })
    } else {
      setEditingRate(null)
      setFormData({
        effective_date: new Date().toISOString().split('T')[0],
        region: 'NCR',
        daily_rate: '',
        wage_order_reference: '',
      })
    }
    setIsDialogOpen(true)
  }

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    
    const payload = {
      effective_date: formData.effective_date,
      region: formData.region,
      daily_rate: parseFloat(formData.daily_rate),
      wage_order_reference: formData.wage_order_reference || null as unknown as undefined,
    }

    try {
      if (editingRate) {
        await updateMutation.mutateAsync({ id: editingRate.id, data: payload })
        toast.success('Minimum wage rate updated successfully')
      } else {
        await createMutation.mutateAsync(payload as Omit<MinimumWageRate, 'id' | 'created_at' | 'updated_at'>)
        toast.success('Minimum wage rate created successfully')
      }
      setIsDialogOpen(false)
    } catch (_error) {
      toast.error(firstErrorMessage(_error, 'Failed to save minimum wage rate'))
    }
  }

  const handleDeleteClick = (rate: MinimumWageRate) => {
    if (!isEditMode) {
      toast.info('Enable editing to delete records')
      return
    }
    setShowDeleteConfirm(rate)
  }

  const handleDeleteConfirm = async () => {
    if (!showDeleteConfirm) return
    
    try {
      await deleteMutation.mutateAsync(showDeleteConfirm.id)
      toast.success('Minimum wage rate deleted successfully')
      setShowDeleteConfirm(null)
    } catch (_error) {
      toast.error(firstErrorMessage(_error, 'Failed to delete minimum wage rate'))
    }
  }

  const rows = data?.data || []
  const regions = data?.regions || []
  const displayData = selectedRegion === 'all' 
    ? rows 
    : rows.filter(r => r.region === selectedRegion)

  if (isLoading) {
    return <div className="text-center py-8 text-neutral-500">Loading minimum wage rates...</div>
  }

  return (
    <div className="space-y-4">
      {/* Toolbar */}
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-3">
          <span className="text-sm text-neutral-500">Filter by region:</span>
          <select
            value={selectedRegion}
            onChange={(e) => setSelectedRegion(e.target.value)}
            className="text-sm border border-neutral-300 rounded px-3 py-2 focus:ring-1 focus:ring-neutral-400"
          >
            <option value="all">All Regions</option>
            {regions.map((r) => (
              <option key={r} value={r}>Region {r}</option>
            ))}
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
              Add Rate
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
              <TableHead className="text-xs font-semibold text-neutral-600">Region</TableHead>
              <TableHead className="text-xs font-semibold text-neutral-600">Effective Date</TableHead>
              <TableHead className="text-xs font-semibold text-neutral-600">Daily Rate</TableHead>
              <TableHead className="text-xs font-semibold text-neutral-600">Wage Order Reference</TableHead>
              <TableHead className="w-20 text-xs font-semibold text-neutral-600">Actions</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {displayData.length === 0 ? (
              <TableRow>
                <TableCell colSpan={5} className="text-center py-8 text-neutral-500">
                  No minimum wage rates found
                </TableCell>
              </TableRow>
            ) : (
              displayData.map((rate) => (
                <TableRow key={rate.id}>
                  <TableCell>
                    <div className="flex items-center gap-2">
                      <MapPin className="h-4 w-4 text-neutral-400" />
                      <Badge variant="outline" className="border-neutral-300 text-neutral-600">Region {rate.region}</Badge>
                    </div>
                  </TableCell>
                  <TableCell>{rate.effective_date?.substring(0, 10)}</TableCell>
                  <TableCell className="font-medium text-lg">{formatCurrency(rate.daily_rate)}</TableCell>
                  <TableCell className="text-neutral-500">{rate.wage_order_reference || '-'}</TableCell>
                  <TableCell>
                    {isEditMode ? (
                      <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                          <Button variant="ghost" size="sm">
                            <MoreHorizontal className="h-4 w-4" />
                          </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                          <DropdownMenuItem onClick={() => handleOpenDialog(rate)}>
                            <Pencil className="h-4 w-4 mr-2" />
                            Edit
                          </DropdownMenuItem>
                          <DropdownMenuItem 
                            onClick={() => handleDeleteClick(rate)}
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
              Are you sure you want to delete this minimum wage rate? This action cannot be undone.
              {showDeleteConfirm && (
                <div className="mt-4 p-3 bg-neutral-50 rounded text-sm">
                  <p><strong>Region:</strong> {showDeleteConfirm.region}</p>
                  <p><strong>Effective Date:</strong> {showDeleteConfirm.effective_date?.substring(0, 10)}</p>
                  <p><strong>Daily Rate:</strong> {formatCurrency(showDeleteConfirm.daily_rate)}</p>
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
            <DialogTitle>{editingRate ? 'Edit' : 'Add'} Minimum Wage Rate</DialogTitle>
            <DialogDescription>
              Set the minimum daily wage rate for a region.
            </DialogDescription>
          </DialogHeader>
          
          <form onSubmit={handleSubmit} className="p-6 pt-2 space-y-5">
            <div className="grid grid-cols-2 gap-4">
              <div className="space-y-1.5">
                <Label htmlFor="region" className="text-sm font-medium text-neutral-700">
                  Region <span className="text-red-500">*</span>
                </Label>
                <select
                  id="region"
                  value={formData.region}
                  onChange={(e) => setFormData({ ...formData, region: e.target.value })}
                  className="w-full h-11 text-sm border border-neutral-300 rounded px-3 py-2 bg-white focus:outline-none focus:ring-1 focus:ring-neutral-400"
                  required
                  disabled={!!editingRate}
                >
                  {REGIONS.map(r => (
                    <option key={r} value={r}>Region {r}</option>
                  ))}
                </select>
              </div>
              
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
            </div>

            <div className="space-y-1.5">
              <Label htmlFor="daily_rate" className="text-sm font-medium text-neutral-700">
                Daily Rate (₱) <span className="text-red-500">*</span>
              </Label>
              <Input
                id="daily_rate"
                type="number"
                step="0.01"
                min="0"
                placeholder="0.00"
                value={formData.daily_rate}
                onChange={(e) => setFormData({ ...formData, daily_rate: e.target.value })}
                required
                className="h-11"
              />
            </div>

            <div className="space-y-1.5">
              <Label htmlFor="wage_order_reference" className="text-sm font-medium text-neutral-700">
                Wage Order Reference
              </Label>
              <Input
                id="wage_order_reference"
                value={formData.wage_order_reference}
                onChange={(e) => setFormData({ ...formData, wage_order_reference: e.target.value })}
                placeholder="e.g., NCR-25"
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
                {editingRate ? 'Update' : 'Create'} Rate
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>
    </div>
  )
}
