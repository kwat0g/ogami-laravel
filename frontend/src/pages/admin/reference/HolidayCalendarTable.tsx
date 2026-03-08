import { useState } from 'react'
import { useEditMode } from '../ReferenceTablesPage'
import { 
  useHolidays, 
  useCreateHoliday,
  useUpdateHoliday,
  useDeleteHoliday,
  type Holiday 
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
import { Plus, MoreHorizontal, Pencil, Trash2, Calendar, Sun, Moon, Globe, Lock, AlertTriangle } from 'lucide-react'
import { toast } from 'sonner'
import { cn } from '@/lib/utils'

const HOLIDAY_TYPES = [
  { value: 'REGULAR', label: 'Regular Holiday', icon: Sun, color: 'bg-neutral-100 text-neutral-700' },
  { value: 'SPECIAL_NON_WORKING', label: 'Special Non-Working', icon: Moon, color: 'bg-neutral-100 text-neutral-700' },
  { value: 'SPECIAL_WORKING', label: 'Special Working', icon: Calendar, color: 'bg-neutral-100 text-neutral-700' },
]

export default function HolidayCalendarTable(): JSX.Element {
  const { isEditMode } = useEditMode()
  const { data, isLoading } = useHolidays()
  const createMutation = useCreateHoliday()
  const updateMutation = useUpdateHoliday()
  const deleteMutation = useDeleteHoliday()

  const [isDialogOpen, setIsDialogOpen] = useState(false)
  const [editingHoliday, setEditingHoliday] = useState<Holiday | null>(null)
  const [selectedYear, setSelectedYear] = useState<number | 'all'>('all')
  const [selectedType, setSelectedType] = useState<string | 'all'>('all')
  const [showDeleteConfirm, setShowDeleteConfirm] = useState<Holiday | null>(null)
  const [formData, setFormData] = useState({
    holiday_date: '',
    name: '',
    type: 'REGULAR',
    is_nationwide: true,
    region: '',
    proclamation_reference: '',
  })

  const handleOpenDialog = (holiday?: Holiday) => {
    if (!isEditMode) {
      toast.info('Enable editing to modify holidays')
      return
    }
    if (holiday) {
      setEditingHoliday(holiday)
      setFormData({
        holiday_date: holiday.holiday_date,
        name: holiday.name,
        type: holiday.type,
        is_nationwide: holiday.is_nationwide,
        region: holiday.region || '',
        proclamation_reference: holiday.proclamation_reference || '',
      })
    } else {
      setEditingHoliday(null)
      setFormData({
        holiday_date: '',
        name: '',
        type: 'REGULAR',
        is_nationwide: true,
        region: '',
        proclamation_reference: '',
      })
    }
    setIsDialogOpen(true)
  }

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    
    const payload = {
      holiday_date: formData.holiday_date,
      name: formData.name,
      type: formData.type as Holiday['type'],
      is_nationwide: formData.is_nationwide,
      region: formData.region || null as unknown as undefined,
      proclamation_reference: formData.proclamation_reference || null as unknown as undefined,
    }

    try {
      if (editingHoliday) {
        await updateMutation.mutateAsync({ id: editingHoliday.id, data: payload })
        toast.success('Holiday updated successfully')
      } else {
        await createMutation.mutateAsync(payload)
        toast.success('Holiday created successfully')
      }
      setIsDialogOpen(false)
    } catch (_error) {
      toast.error('Failed to save holiday')
    }
  }

  const handleDeleteClick = (holiday: Holiday) => {
    if (!isEditMode) {
      toast.info('Enable editing to delete records')
      return
    }
    setShowDeleteConfirm(holiday)
  }

  const handleDeleteConfirm = async () => {
    if (!showDeleteConfirm) return
    
    try {
      await deleteMutation.mutateAsync(showDeleteConfirm.id)
      toast.success('Holiday deleted successfully')
      setShowDeleteConfirm(null)
    } catch (_error) {
      toast.error('Failed to delete holiday')
    }
  }

  const years = data?.years || []
  let displayData = data?.data || []
  
  if (selectedYear !== 'all') {
    displayData = displayData.filter(h => h.year === selectedYear)
  }
  if (selectedType !== 'all') {
    displayData = displayData.filter(h => h.type === selectedType)
  }

  const getTypeConfig = (type: string) => HOLIDAY_TYPES.find(t => t.value === type) || HOLIDAY_TYPES[0]

  if (isLoading) {
    return <div className="text-center py-8 text-neutral-500">Loading holidays...</div>
  }

  return (
    <div className="space-y-4">
      {/* Toolbar */}
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-3">
          <span className="text-sm text-neutral-500">Filter:</span>
          <select
            value={selectedYear}
            onChange={(e) => setSelectedYear(e.target.value === 'all' ? 'all' : parseInt(e.target.value))}
            className="text-sm border border-neutral-300 rounded px-3 py-2 focus:ring-1 focus:ring-neutral-400"
          >
            <option value="all">All Years</option>
            {years.map((y) => (
              <option key={y} value={y}>{y}</option>
            ))}
          </select>
          
          <select
            value={selectedType}
            onChange={(e) => setSelectedType(e.target.value)}
            className="text-sm border border-neutral-300 rounded px-3 py-2 focus:ring-1 focus:ring-neutral-400"
          >
            <option value="all">All Types</option>
            {HOLIDAY_TYPES.map((t) => (
              <option key={t.value} value={t.value}>{t.label}</option>
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
              Add Holiday
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
            <TableHeader className="sticky top-0 bg-white">
              <TableRow className="bg-neutral-50">
                <TableHead className="text-xs font-semibold text-neutral-600">Date</TableHead>
                <TableHead className="text-xs font-semibold text-neutral-600">Name</TableHead>
                <TableHead className="text-xs font-semibold text-neutral-600">Type</TableHead>
                <TableHead className="text-xs font-semibold text-neutral-600">Scope</TableHead>
                <TableHead className="w-20 text-xs font-semibold text-neutral-600">Actions</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {displayData.length === 0 ? (
                <TableRow>
                  <TableCell colSpan={5} className="text-center py-8 text-neutral-500">
                    No holidays found
                  </TableCell>
                </TableRow>
              ) : (
                displayData.map((holiday) => {
                  const typeConfig = getTypeConfig(holiday.type)
                  const TypeIcon = typeConfig.icon
                  
                  return (
                    <TableRow key={holiday.id}>
                      <TableCell className="whitespace-nowrap">
                        <div className="flex flex-col">
                          <span className="font-medium">
                            {new Date(holiday.holiday_date).toLocaleDateString('en-US', { 
                              month: 'short', day: 'numeric', year: 'numeric' 
                            })}
                          </span>
                          <span className="text-xs text-neutral-500">
                            {new Date(holiday.holiday_date).toLocaleDateString('en-US', { weekday: 'long' })}
                          </span>
                        </div>
                      </TableCell>
                      <TableCell className="font-medium">{holiday.name}</TableCell>
                      <TableCell>
                        <Badge className={cn('gap-1', typeConfig.color)}>
                          <TypeIcon className="h-3 w-3" />
                          {typeConfig.label}
                        </Badge>
                      </TableCell>
                      <TableCell>
                        {holiday.is_nationwide ? (
                          <span className="flex items-center gap-1 text-sm text-neutral-600">
                            <Globe className="h-3 w-3" />
                            Nationwide
                          </span>
                        ) : (
                          <Badge variant="outline" className="border-neutral-300 text-neutral-600">Region {holiday.region}</Badge>
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
                              <DropdownMenuItem onClick={() => handleOpenDialog(holiday)}>
                                <Pencil className="h-4 w-4 mr-2" />
                                Edit
                              </DropdownMenuItem>
                              <DropdownMenuItem 
                                onClick={() => handleDeleteClick(holiday)}
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
                  )
                })
              )}
            </TableBody>
          </Table>
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
              Are you sure you want to delete this holiday? This action cannot be undone.
              {showDeleteConfirm && (
                <div className="mt-4 p-3 bg-neutral-50 rounded text-sm">
                  <p><strong>Name:</strong> {showDeleteConfirm.name}</p>
                  <p><strong>Date:</strong> {showDeleteConfirm.holiday_date}</p>
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
            <DialogTitle>{editingHoliday ? 'Edit' : 'Add'} Holiday</DialogTitle>
            <DialogDescription>
              Define a regular or special holiday for the calendar.
            </DialogDescription>
          </DialogHeader>
          
          <form onSubmit={handleSubmit} className="p-6 pt-2 space-y-5">
            <div className="grid grid-cols-2 gap-4">
              <div className="space-y-1.5">
                <Label htmlFor="holiday_date" className="text-sm font-medium text-neutral-700">
                  Date <span className="text-red-500">*</span>
                </Label>
                <Input
                  id="holiday_date"
                  type="date"
                  value={formData.holiday_date}
                  onChange={(e) => setFormData({ ...formData, holiday_date: e.target.value })}
                  required
                  className="h-11"
                />
              </div>
              
              <div className="space-y-1.5">
                <Label htmlFor="type" className="text-sm font-medium text-neutral-700">
                  Type <span className="text-red-500">*</span>
                </Label>
                <select
                  id="type"
                  value={formData.type}
                  onChange={(e) => setFormData({ ...formData, type: e.target.value })}
                  className="w-full h-11 text-sm border border-neutral-300 rounded px-3 py-2 bg-white focus:outline-none focus:ring-1 focus:ring-neutral-400"
                  required
                >
                  {HOLIDAY_TYPES.map(t => (
                    <option key={t.value} value={t.value}>{t.label}</option>
                  ))}
                </select>
              </div>
            </div>

            <div className="space-y-1.5">
              <Label htmlFor="name" className="text-sm font-medium text-neutral-700">
                Holiday Name <span className="text-red-500">*</span>
              </Label>
              <Input
                id="name"
                value={formData.name}
                onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                placeholder="e.g., New Year's Day"
                required
                className="h-11"
              />
            </div>

            <div className="flex items-center gap-4">
              <label className="flex items-center gap-2.5 cursor-pointer">
                <input
                  type="checkbox"
                  checked={formData.is_nationwide}
                  onChange={(e) => setFormData({ ...formData, is_nationwide: e.target.checked })}
                  className="h-4 w-4 rounded border-neutral-300 text-neutral-600 focus:ring-neutral-400"
                />
                <span className="text-sm font-medium text-neutral-700">Nationwide</span>
              </label>
              
              {!formData.is_nationwide && (
                <div className="flex-1">
                  <Input
                    placeholder="Region code"
                    value={formData.region}
                    onChange={(e) => setFormData({ ...formData, region: e.target.value })}
                    className="h-9 text-sm"
                  />
                </div>
              )}
            </div>

            <div className="space-y-1.5">
              <Label htmlFor="proclamation_reference" className="text-sm font-medium text-neutral-700">
                Proclamation Reference
              </Label>
              <Input
                id="proclamation_reference"
                value={formData.proclamation_reference}
                onChange={(e) => setFormData({ ...formData, proclamation_reference: e.target.value })}
                placeholder="e.g., Proclamation No. 727 (2025)"
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
                {editingHoliday ? 'Update' : 'Create'} Holiday
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>
    </div>
  )
}
