import { useQuery } from '@tanstack/react-query'
import api from '@/lib/api'
import { useEditMode } from '../ReferenceTablesPage'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import { Badge } from '@/components/ui/badge'
import { Skeleton } from '@/components/ui/skeleton'

interface AccountMapping {
  id: number
  module: string
  event: string
  sub_key: string | null
  side: 'debit' | 'credit'
  account_id: number
  description: string | null
  is_active: boolean
  account?: {
    id: number
    code: string
    name: string
  }
}

export default function AccountMappingsTable(): JSX.Element {
  const { _isEditMode } = useEditMode()

  const { data, isLoading } = useQuery<{ data: AccountMapping[] }>({
    queryKey: ['admin', 'account-mappings'],
    queryFn: () => api.get('/admin/account-mappings').then(r => r.data),
  })

  const mappings = data?.data ?? []

  if (isLoading) {
    return (
      <div className="space-y-2">
        {Array.from({ length: 8 }).map((_, i) => (
          <Skeleton key={i} className="h-10 w-full" />
        ))}
      </div>
    )
  }

  // Group by module for better readability
  const grouped = mappings.reduce<Record<string, AccountMapping[]>>((acc, m) => {
    acc[m.module] = acc[m.module] || []
    acc[m.module].push(m)
    return acc
  }, {})

  return (
    <div className="space-y-6">
      <p className="text-sm text-muted-foreground">
        GL account mappings determine which Chart of Accounts entries are debited/credited
        when the system auto-posts journal entries. Changes here take effect immediately
        for all future postings.
      </p>

      {Object.entries(grouped).map(([module, items]) => (
        <div key={module}>
          <h3 className="text-sm font-semibold uppercase tracking-wider text-muted-foreground mb-2">
            {module}
          </h3>
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Event</TableHead>
                <TableHead>Sub-Key</TableHead>
                <TableHead>Side</TableHead>
                <TableHead>Account Code</TableHead>
                <TableHead>Account Name</TableHead>
                <TableHead>Description</TableHead>
                <TableHead>Active</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {items.map((m) => (
                <TableRow key={m.id}>
                  <TableCell className="font-mono text-xs">{m.event}</TableCell>
                  <TableCell className="text-xs">{m.sub_key ?? '-'}</TableCell>
                  <TableCell>
                    <Badge variant={m.side === 'debit' ? 'default' : 'secondary'}>
                      {m.side}
                    </Badge>
                  </TableCell>
                  <TableCell className="font-mono">{m.account?.code ?? '?'}</TableCell>
                  <TableCell>{m.account?.name ?? 'Unknown'}</TableCell>
                  <TableCell className="text-muted-foreground text-xs">{m.description}</TableCell>
                  <TableCell>
                    <Badge variant={m.is_active ? 'default' : 'destructive'}>
                      {m.is_active ? 'Yes' : 'No'}
                    </Badge>
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </div>
      ))}

      {mappings.length === 0 && (
        <p className="text-center text-muted-foreground py-8">
          No account mappings configured. Run the AccountMappingSeeder to populate defaults.
        </p>
      )}
    </div>
  )
}
