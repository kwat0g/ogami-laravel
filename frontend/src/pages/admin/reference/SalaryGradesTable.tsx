import { firstErrorMessage } from '@/lib/errorHandler'
import { useState } from 'react'
import { useEditMode } from '../ReferenceTablesPage'
import { 
  useSalaryGrades, 
  useCreateSalaryGrade,
  useUpdateSalaryGrade,
  useDeleteSalaryGrade,
  type SalaryGrade 
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
import { Plus, MoreHorizontal, Pencil, Trash2, GraduationCap, Lock, AlertTriangle } from 'lucide-react'
import { toast } from 'sonner'
import { formatCurrency } from '@/lib/formatters'
import { cn } from '@/lib/utils'

const EMPLOYMENT_TYPES = [
  { value: 'regular', label: 'Regular' },
  { value: 'contractual', label: 'Contractual' },
  { value: 'project_based', label: 'Project Based' },
  { value: 'casual', label: 'Casual' },
]

export default function SalaryGradesTable(): JSX.Element {
  const { isEditMode } = useEditMode()
  const { data, isLoading } = useSalaryGrades()
  const createMutation = useCreateSalaryGrade()
  const updateMutation = useUpdateSalaryGrade()
  const deleteMutation = useDeleteSalaryGrade()

  const [isDialogOpen, setIsDialogOpen] = useState(false)
  const [editingGrade, setEditingGrade] = useState<SalaryGrade | null>(null)
  const [selectedType, setSelectedType] = useState<string | 'all'>('all')
  const [showDeleteConfirm, setShowDeleteConfirm] = useState<SalaryGrade | null>(null)
  const [formData, setFormData] = useState({
    code: '',
    name: '',
    level: '1',
    min_monthly_rate: '',
    max_monthly_rate: '',
    employment_type: 'regular',
    is_active: true,
  })

  const handleOpenDialog = (grade?: SalaryGrade) => {
    if (!isEditMode) {
      toast.info('Enable editing to modify salary grades')
      return
    }
    if (grade) {
      setEditingGrade(grade)
      setFormData({
        code: grade.code,
        name: grade.name,
        level: grade.level.toString(),
        min_monthly_rate: (grade.min_monthly_rate / 100).toString(),
        max_monthly_rate: (grade.max_monthly_rate / 100).toString(),
        employment_type: grade.employment_type,
        is_active: grade.is_active,
      })
    } else {
      setEditingGrade(null)
      setFormData({
        code: '',
        name: '',
        level: '1',
        min_monthly_rate: '',
        max_monthly_rate: '',
        employment_type: 'regular',
        is_active: true,
      })
    }
    setIsDialogOpen(true)
  }

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    
    const payload = {
      code: editingGrade ? formData.code : `SG-${String(formData.level || '').padStart(2, '0')}`,
      name: formData.name,
      level: parseInt(formData.level),
      min_monthly_rate: Math.round(parseFloat(formData.min_monthly_rate) * 100),
      max_monthly_rate: Math.round(parseFloat(formData.max_monthly_rate) * 100),
      employment_type: formData.employment_type,
      is_active: formData.is_active,
    }

    try {
      if (editingGrade) {
        await updateMutation.mutateAsync({ id: editingGrade.id, data: payload })
        toast.success('Salary grade updated successfully')
      } else {
        await createMutation.mutateAsync(payload as Omit<SalaryGrade, 'id' | 'created_at' | 'updated_at'>)
        toast.success('Salary grade created successfully')
      }
      setIsDialogOpen(false)
    } catch (_error) {
      toast.error(firstErrorMessage(_error, 'Failed to save salary grade'))
    }
  }

  const handleDeleteClick = (grade: SalaryGrade) => {
    if (!isEditMode) {
      toast.info('Enable editing to delete records')
      return
    }
    setShowDeleteConfirm(grade)
  }

  const handleDeleteConfirm = async () => {
    if (!showDeleteConfirm) return
    
    try {
      await deleteMutation.mutateAsync(showDeleteConfirm.id)
      toast.success('Salary grade deleted successfully')
      setShowDeleteConfirm(null)
    } catch (_error) {
      toast.error(firstErrorMessage(_error, 'Failed to delete salary grade'))
    }
  }

  const types = data?.types || []
  const displayData = selectedType === 'all' 
    ? data?.data || []
    : data?.data?.filter(g => g.employment_type === selectedType) || []

  if (isLoading) {
    return <div className="text-center py-8 text-neutral-500">Loading salary grades...</div>
  }

  return (
    <div className="space-y-4">
      {/* Toolbar */}
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-3">
          <span className="text-sm text-neutral-500">Filter by type:</span>
          <select
            value={selectedType}
            onChange={(e) => setSelectedType(e.target.value)}
            className="text-sm border border-neutral-300 rounded px-3 py-2 focus:ring-1 focus:ring-neutral-400"
          >
            <option value="all">All Types</option>
            {types.map((t) => (
              <option key={t} value={t}>{t.replace('_', ' ')}</option>
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
              Add Grade
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
              <TableHead className="text-xs font-semibold text-neutral-600">Code</TableHead>
              <TableHead className="text-xs font-semibold text-neutral-600">Name</TableHead>
              <TableHead className="text-xs font-semibold text-neutral-600">Level</TableHead>
              <TableHead className="text-xs font-semibold text-neutral-600">Type</TableHead>
              <TableHead className="text-xs font-semibold text-neutral-600">Min Monthly</TableHead>
              <TableHead className="text-xs font-semibold text-neutral-600">Max Monthly</TableHead>
              <TableHead className="text-xs font-semibold text-neutral-600">Status</TableHead>
              <TableHead className="w-20 text-xs font-semibold text-neutral-600">Actions</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {displayData.length === 0 ? (
              <TableRow>
                <TableCell colSpan={8} className="text-center py-8 text-neutral-500">
                  No salary grades found
                </TableCell>
              </TableRow>
            ) : (
              displayData.map((grade) => (
                <TableRow 
                  key={grade.id}
                  className={cn(!grade.is_active && 'opacity-60')}
                >
                  <TableCell className="font-medium">{grade.code}</TableCell>
                  <TableCell>{grade.name}</TableCell>
                  <TableCell>
                    <div className="flex items-center gap-1">
                      <GraduationCap className="h-4 w-4 text-neutral-400" />
                      {grade.level}
                    </div>
                  </TableCell>
                  <TableCell>
                    <Badge variant="outline" className="border-neutral-300 text-neutral-600">
                      {grade.employment_type?.replace('_', ' ') || '—'}
                    </Badge>
                  </TableCell>
                  <TableCell>{formatCurrency(grade.min_monthly_rate / 100)}</TableCell>
                  <TableCell>{formatCurrency(grade.max_monthly_rate / 100)}</TableCell>
                  <TableCell>
                    {grade.is_active ? (
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
                          <DropdownMenuItem onClick={() => handleOpenDialog(grade)}>
                            <Pencil className="h-4 w-4 mr-2" />
                            Edit
                          </DropdownMenuItem>
                          <DropdownMenuItem 
                            onClick={() => handleDeleteClick(grade)}
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
              Are you sure you want to delete this salary grade? This action cannot be undone.
              {showDeleteConfirm && (
                <div className="mt-4 p-3 bg-neutral-50 rounded text-sm">
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
            <DialogTitle>{editingGrade ? 'Edit' : 'Add'} Salary Grade</DialogTitle>
            <DialogDescription>
              Define a salary grade with pay range.
            </DialogDescription>
          </DialogHeader>
          
          <form onSubmit={handleSubmit} className="p-6 pt-2 space-y-5">
            <div className="grid grid-cols-2 gap-4">
              <div className="space-y-1.5">
                <Label htmlFor="code" className="text-sm font-medium text-neutral-700">
                  Code {editingGrade ? '' : <span className="text-neutral-400 text-xs font-normal">(auto-generated)</span>}
                </Label>
                <Input
                  id="code"
                  value={editingGrade ? formData.code : `SG-${String(formData.level || '').padStart(2, '0')}`}
                  readOnly
                  disabled
                  className="h-11 bg-neutral-50 text-neutral-500 font-mono"
                />
              </div>
              
              <div className="space-y-1.5">
                <Label htmlFor="level" className="text-sm font-medium text-neutral-700">
                  Level (1-20) <span className="text-red-500">*</span>
                </Label>
                <Input
                  id="level"
                  type="number"
                  min="1"
                  max="20"
                  placeholder="1"
                  value={formData.level}
                  onChange={(e) => setFormData({ ...formData, level: e.target.value })}
                  required
                  className="h-11"
                />
              </div>
            </div>

            <div className="space-y-1.5">
              <Label htmlFor="name" className="text-sm font-medium text-neutral-700">
                Name <span className="text-red-500">*</span>
              </Label>
              <Input
                id="name"
                value={formData.name}
                onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                placeholder="e.g., Entry Level I"
                required
                className="h-11"
              />
            </div>

            <div className="space-y-1.5">
              <Label htmlFor="employment_type" className="text-sm font-medium text-neutral-700">
                Employment Type <span className="text-red-500">*</span>
              </Label>
              <select
                id="employment_type"
                value={formData.employment_type}
                onChange={(e) => setFormData({ ...formData, employment_type: e.target.value })}
                className="w-full h-11 text-sm border border-neutral-300 rounded px-3 py-2 bg-white focus:outline-none focus:ring-1 focus:ring-neutral-400"
                required
              >
                {EMPLOYMENT_TYPES.map(t => (
                  <option key={t.value} value={t.value}>{t.label}</option>
                ))}
              </select>
            </div>

            <div className="grid grid-cols-2 gap-4">
              <div className="space-y-1.5">
                <Label htmlFor="min_monthly_rate" className="text-sm font-medium text-neutral-700">
                  Min Monthly (₱) <span className="text-red-500">*</span>
                </Label>
                <Input
                  id="min_monthly_rate"
                  type="number"
                  step="0.01"
                  min="0"
                  placeholder="0.00"
                  value={formData.min_monthly_rate}
                  onChange={(e) => setFormData({ ...formData, min_monthly_rate: e.target.value })}
                  required
                  className="h-11"
                />
              </div>
              
              <div className="space-y-1.5">
                <Label htmlFor="max_monthly_rate" className="text-sm font-medium text-neutral-700">
                  Max Monthly (₱) <span className="text-red-500">*</span>
                </Label>
                <Input
                  id="max_monthly_rate"
                  type="number"
                  step="0.01"
                  min="0"
                  placeholder="0.00"
                  value={formData.max_monthly_rate}
                  onChange={(e) => setFormData({ ...formData, max_monthly_rate: e.target.value })}
                  required
                  className="h-11"
                />
              </div>
            </div>

            <label className="flex items-center gap-2.5 cursor-pointer">
              <input
                type="checkbox"
                checked={formData.is_active}
                onChange={(e) => setFormData({ ...formData, is_active: e.target.checked })}
                className="h-4 w-4 rounded border-neutral-300 text-neutral-600 focus:ring-neutral-400"
              />
              <span className="text-sm font-medium text-neutral-700">Active</span>
            </label>

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
                {editingGrade ? 'Update' : 'Create'} Grade
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>
    </div>
  )
}
