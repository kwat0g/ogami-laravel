import { useState } from 'react'
import { useEditMode } from '../ReferenceTablesPage'
import { 
  useChartOfAccounts, 
  useCreateChartOfAccount,
  useUpdateChartOfAccount,
  useDeleteChartOfAccount,
  useArchiveChartOfAccount,
  type ChartOfAccount 
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
import { 
  Plus, 
  MoreHorizontal, 
  Pencil, 
  Trash2, 
  ChevronRight, 
  ChevronDown,
  Archive,
  AlertCircle,
  Folder,
  FileText,
  Lock
} from 'lucide-react'
import { toast } from 'sonner'
import { cn } from '@/lib/utils'

const ACCOUNT_TYPES = ['ASSET', 'LIABILITY', 'EQUITY', 'REVENUE', 'COGS', 'OPEX', 'TAX']
const ACCOUNT_TYPE_COLORS: Record<string, string> = {
  ASSET: 'bg-neutral-100 text-neutral-700',
  LIABILITY: 'bg-neutral-100 text-neutral-700',
  EQUITY: 'bg-neutral-100 text-neutral-700',
  REVENUE: 'bg-neutral-100 text-neutral-700',
  COGS: 'bg-neutral-100 text-neutral-700',
  OPEX: 'bg-neutral-100 text-neutral-700',
  TAX: 'bg-neutral-100 text-neutral-700',
}

interface AccountNodeProps {
  account: ChartOfAccount & { children?: ChartOfAccount[], level?: number }
  allAccounts: ChartOfAccount[]
  expanded: Set<number>
  onToggle: (id: number) => void
  onEdit: (account: ChartOfAccount) => void
  onDelete: (account: ChartOfAccount) => void
  onArchive: (account: ChartOfAccount) => void
  isEditMode: boolean
}

function AccountNode({ 
  account, 
  allAccounts, 
  expanded, 
  onToggle, 
  onEdit, 
  onDelete,
  onArchive,
  isEditMode
}: AccountNodeProps): JSX.Element {
  const hasChildren = account.children && account.children.length > 0
  const isExpanded = expanded.has(account.id)
  const level = account.level || 0
  
  return (
    <>
      <TableRow className={cn(
        !account.is_active && 'opacity-60 bg-neutral-50',
        account.is_system && 'bg-neutral-50'
      )}>
        <TableCell>
          <div 
            className="flex items-center gap-2"
            style={{ paddingLeft: `${level * 24}px` }}
          >
            {hasChildren ? (
              <button
                onClick={() => onToggle(account.id)}
                className="p-1 hover:bg-neutral-100 rounded"
              >
                {isExpanded ? (
                  <ChevronDown className="h-4 w-4 text-neutral-500" />
                ) : (
                  <ChevronRight className="h-4 w-4 text-neutral-500" />
                )}
              </button>
            ) : (
              <span className="w-6" />
            )}
            
            {hasChildren ? (
              <Folder className="h-4 w-4 text-neutral-500" />
            ) : (
              <FileText className="h-4 w-4 text-neutral-400" />
            )}
            
            <span className="font-medium">{account.code}</span>
            {account.is_system && (
              <Badge variant="outline" className="text-neutral-600 border-neutral-300 text-xs">
                System
              </Badge>
            )}
          </div>
        </TableCell>
        <TableCell>{account.name}</TableCell>
        <TableCell>
          <Badge className={cn('text-xs', ACCOUNT_TYPE_COLORS[account.account_type])}>
            {account.account_type}
          </Badge>
        </TableCell>
        <TableCell>
          <Badge variant={account.normal_balance === 'DEBIT' ? 'default' : 'secondary'}>
            {account.normal_balance}
          </Badge>
        </TableCell>
        <TableCell>
          {account.is_active ? (
            <Badge variant="success" className="text-xs">Active</Badge>
          ) : (
            <Badge variant="secondary" className="text-xs">Archived</Badge>
          )}
        </TableCell>
        <TableCell>
          {!account.is_system && isEditMode && (
            <DropdownMenu>
              <DropdownMenuTrigger asChild>
                <Button variant="ghost" size="sm">
                  <MoreHorizontal className="h-4 w-4" />
                </Button>
              </DropdownMenuTrigger>
              <DropdownMenuContent align="end">
                <DropdownMenuItem onClick={() => onEdit(account)}>
                  <Pencil className="h-4 w-4 mr-2" />
                  Edit
                </DropdownMenuItem>
                {!account.children?.length && (
                  <DropdownMenuItem 
                    onClick={() => onArchive(account)}
                    className="text-amber-600"
                  >
                    <Archive className="h-4 w-4 mr-2" />
                    Archive
                  </DropdownMenuItem>
                )}
                {!account.children?.length && (
                  <DropdownMenuItem 
                    onClick={() => onDelete(account)}
                    className="text-red-600"
                  >
                    <Trash2 className="h-4 w-4 mr-2" />
                    Delete
                  </DropdownMenuItem>
                )}
              </DropdownMenuContent>
            </DropdownMenu>
          )}
          {!account.is_system && !isEditMode && (
            <Button variant="ghost" size="sm" disabled className="opacity-50">
              <Lock className="h-4 w-4" />
            </Button>
          )}
        </TableCell>
      </TableRow>
      
      {isExpanded && hasChildren && account.children?.map(child => (
        <AccountNode
          key={child.id}
          account={{ ...child, level: level + 1 }}
          allAccounts={allAccounts}
          expanded={expanded}
          onToggle={onToggle}
          onEdit={onEdit}
          onDelete={onDelete}
          onArchive={onArchive}
          isEditMode={isEditMode}
        />
      ))}
    </>
  )
}

export default function ChartOfAccountsTable(): JSX.Element {
  const { isEditMode } = useEditMode()
  const { data, isLoading } = useChartOfAccounts()
  const createMutation = useCreateChartOfAccount()
  const updateMutation = useUpdateChartOfAccount()
  const deleteMutation = useDeleteChartOfAccount()
  const archiveMutation = useArchiveChartOfAccount()

  const [isDialogOpen, setIsDialogOpen] = useState(false)
  const [editingAccount, setEditingAccount] = useState<ChartOfAccount | null>(null)
  const [expanded, setExpanded] = useState<Set<number>>(new Set())
  const [selectedType, setSelectedType] = useState<string | 'all'>('all')
  const [showArchived, setShowArchived] = useState(false)
  const [formData, setFormData] = useState({
    code: '',
    name: '',
    account_type: 'ASSET',
    parent_id: '',
    normal_balance: 'DEBIT',
    description: '',
  })

  const handleToggle = (id: number) => {
    const newExpanded = new Set(expanded)
    if (newExpanded.has(id)) {
      newExpanded.delete(id)
    } else {
      newExpanded.add(id)
    }
    setExpanded(newExpanded)
  }

  const handleOpenDialog = (account?: ChartOfAccount) => {
    if (!isEditMode) {
      toast.info('Enable editing to modify chart of accounts')
      return
    }
    if (account) {
      setEditingAccount(account)
      setFormData({
        code: account.code,
        name: account.name,
        account_type: account.account_type,
        parent_id: account.parent_id?.toString() || '',
        normal_balance: account.normal_balance,
        description: account.description || '',
      })
    } else {
      setEditingAccount(null)
      setFormData({
        code: '',
        name: '',
        account_type: 'ASSET',
        parent_id: '',
        normal_balance: 'DEBIT',
        description: '',
      })
    }
    setIsDialogOpen(true)
  }

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    
    const payload = {
      code: formData.code,
      name: formData.name,
      account_type: formData.account_type,
      parent_id: formData.parent_id ? parseInt(formData.parent_id) : null as unknown as undefined,
      normal_balance: formData.normal_balance as 'DEBIT' | 'CREDIT',
      description: formData.description || null as unknown as undefined,
    }

    try {
      if (editingAccount) {
        await updateMutation.mutateAsync({ id: editingAccount.id, data: payload })
        toast.success('Account updated successfully')
      } else {
        await createMutation.mutateAsync(payload)
        toast.success('Account created successfully')
      }
      setIsDialogOpen(false)
    } catch (_error) {
      toast.error('Failed to save account')
    }
  }

  const handleDelete = async (account: ChartOfAccount) => {
    if (!isEditMode) {
      toast.info('Enable editing to delete accounts')
      return
    }
    
    try {
      await deleteMutation.mutateAsync(account.id)
      toast.success('Account deleted successfully')
    } catch (_error) {
      toast.error('Failed to delete account')
    }
  }

  const handleArchive = async (account: ChartOfAccount) => {
    if (!isEditMode) {
      toast.info('Enable editing to archive accounts')
      return
    }
    
    try {
      await archiveMutation.mutateAsync(account.id)
      toast.success('Account archived successfully')
    } catch (_error) {
      toast.error('Failed to archive account')
    }
  }

  const treeData = data?.tree || []
  const allAccounts = data?.data || []
  const types = data?.account_types || []

  // Filter tree based on selection
  let displayTree = treeData
  if (selectedType !== 'all') {
    displayTree = treeData.filter(a => a.account_type === selectedType)
  }

  if (isLoading) {
    return <div className="text-center py-8 text-neutral-500">Loading chart of accounts...</div>
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
              <option key={t} value={t}>{t}</option>
            ))}
          </select>
          
          <label className="flex items-center gap-2 text-sm">
            <input
              type="checkbox"
              checked={showArchived}
              onChange={(e) => setShowArchived(e.target.checked)}
              className="rounded border-neutral-300"
            />
            Show archived
          </label>
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
              Add Account
            </>
          ) : (
            <>
              <Lock className="h-4 w-4" />
              Editing Locked
            </>
          )}
        </Button>
      </div>

      {/* Info Banner */}
      <div className="bg-neutral-50 border border-neutral-200 rounded p-3 flex items-start gap-2 text-sm text-neutral-800">
        <AlertCircle className="h-5 w-5 shrink-0 text-neutral-600" />
        <div>
          <p className="font-medium">Chart of Accounts Guidelines:</p>
          <ul className="list-disc list-inside text-neutral-600 mt-1 text-xs">
            <li>System accounts (marked with badge) cannot be modified or deleted</li>
            <li>Only leaf accounts (no children) can be posted to</li>
            <li>Maximum hierarchy depth is 5 levels</li>
          </ul>
        </div>
      </div>

      {/* Table */}
      <div className="border border-neutral-200 rounded overflow-hidden">
        <div className="max-h-[600px] overflow-auto">
          <Table>
            <TableHeader className="sticky top-0 bg-white">
              <TableRow className="bg-neutral-50">
                <TableHead className="text-xs font-semibold text-neutral-600">Code</TableHead>
                <TableHead className="text-xs font-semibold text-neutral-600">Name</TableHead>
                <TableHead className="text-xs font-semibold text-neutral-600">Type</TableHead>
                <TableHead className="text-xs font-semibold text-neutral-600">Normal Balance</TableHead>
                <TableHead className="text-xs font-semibold text-neutral-600">Status</TableHead>
                <TableHead className="w-20 text-xs font-semibold text-neutral-600">Actions</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {displayTree.length === 0 ? (
                <TableRow>
                  <TableCell colSpan={6} className="text-center py-8 text-neutral-500">
                    No accounts found
                  </TableCell>
                </TableRow>
              ) : (
                displayTree
                  .filter(a => showArchived || a.is_active)
                  .map((account) => (
                    <AccountNode
                      key={account.id}
                      account={account}
                      allAccounts={allAccounts}
                      expanded={expanded}
                      onToggle={handleToggle}
                      onEdit={handleOpenDialog}
                      onDelete={handleDelete}
                      onArchive={handleArchive}
                      isEditMode={isEditMode}
                    />
                  ))
              )}
            </TableBody>
          </Table>
        </div>
      </div>

      {/* Form Dialog */}
      <Dialog open={isDialogOpen} onOpenChange={setIsDialogOpen}>
        <DialogContent className="max-w-xl">
          <DialogHeader>
            <DialogTitle>{editingAccount ? 'Edit' : 'Add'} Account</DialogTitle>
            <DialogDescription>
              Create a new general ledger account.
            </DialogDescription>
          </DialogHeader>
          
          <form onSubmit={handleSubmit} className="p-6 pt-2 space-y-5">
            <div className="grid grid-cols-2 gap-4">
              <div className="space-y-1.5">
                <Label htmlFor="code" className="text-sm font-medium text-neutral-700">
                  Account Code <span className="text-red-500">*</span>
                </Label>
                <Input
                  id="code"
                  value={formData.code}
                  onChange={(e) => setFormData({ ...formData, code: e.target.value })}
                  placeholder="e.g., 1001"
                  required
                  disabled={!!editingAccount}
                  className="h-11"
                />
              </div>
              
              <div className="space-y-1.5">
                <Label htmlFor="account_type" className="text-sm font-medium text-neutral-700">
                  Account Type <span className="text-red-500">*</span>
                </Label>
                <select
                  id="account_type"
                  value={formData.account_type}
                  onChange={(e) => setFormData({ ...formData, account_type: e.target.value })}
                  className="w-full h-11 text-sm border border-neutral-300 rounded px-3 py-2 bg-white focus:outline-none focus:ring-1 focus:ring-neutral-400"
                  required
                >
                  {ACCOUNT_TYPES.map(t => (
                    <option key={t} value={t}>{t}</option>
                  ))}
                </select>
              </div>
            </div>

            <div className="space-y-1.5">
              <Label htmlFor="name" className="text-sm font-medium text-neutral-700">
                Account Name <span className="text-red-500">*</span>
              </Label>
              <Input
                id="name"
                value={formData.name}
                onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                placeholder="e.g., Cash on Hand"
                required
                className="h-11"
              />
            </div>

            <div className="grid grid-cols-2 gap-4">
              <div className="space-y-1.5">
                <Label htmlFor="parent_id" className="text-sm font-medium text-neutral-700">
                  Parent Account
                </Label>
                <select
                  id="parent_id"
                  value={formData.parent_id}
                  onChange={(e) => setFormData({ ...formData, parent_id: e.target.value })}
                  className="w-full h-11 text-sm border border-neutral-300 rounded px-3 py-2 bg-white focus:outline-none focus:ring-1 focus:ring-neutral-400"
                >
                  <option value="">None (Top Level)</option>
                  {allAccounts
                    .filter(a => a.id !== editingAccount?.id)
                    .map(a => (
                      <option key={a.id} value={a.id}>{a.code} - {a.name}</option>
                    ))}
                </select>
              </div>
              
              <div className="space-y-1.5">
                <Label htmlFor="normal_balance" className="text-sm font-medium text-neutral-700">
                  Normal Balance <span className="text-red-500">*</span>
                </Label>
                <select
                  id="normal_balance"
                  value={formData.normal_balance}
                  onChange={(e) => setFormData({ ...formData, normal_balance: e.target.value })}
                  className="w-full h-11 text-sm border border-neutral-300 rounded px-3 py-2 bg-white focus:outline-none focus:ring-1 focus:ring-neutral-400"
                  required
                >
                  <option value="DEBIT">DEBIT</option>
                  <option value="CREDIT">CREDIT</option>
                </select>
              </div>
            </div>

            <div className="space-y-1.5">
              <Label htmlFor="description" className="text-sm font-medium text-neutral-700">
                Description
              </Label>
              <Input
                id="description"
                value={formData.description}
                onChange={(e) => setFormData({ ...formData, description: e.target.value })}
                placeholder="Account description..."
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
                {editingAccount ? 'Update' : 'Create'} Account
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>
    </div>
  )
}
