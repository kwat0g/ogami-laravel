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
import { CheckCircle2, XCircle } from 'lucide-react'

interface WorkflowStep {
  id: number
  document_type: string
  step_order: number
  step_name: string
  required_permission: string
  target_status: string
  amount_threshold_centavos: number | null
  department_id: number | null
  sod_with_creator: boolean
  sod_with_previous_step: boolean
  is_active: boolean
}

export default function ApprovalWorkflowTable(): JSX.Element {
  const { isEditMode } = useEditMode()

  const { data, isLoading } = useQuery<{ data: WorkflowStep[] }>({
    queryKey: ['admin', 'approval-workflows'],
    queryFn: () => api.get('/admin/approval-workflow-configs').then(r => r.data),
  })

  const steps = data?.data ?? []

  if (isLoading) {
    return (
      <div className="space-y-2">
        {Array.from({ length: 8 }).map((_, i) => (
          <Skeleton key={i} className="h-10 w-full" />
        ))}
      </div>
    )
  }

  // Group by document_type
  const grouped = steps.reduce<Record<string, WorkflowStep[]>>((acc, s) => {
    acc[s.document_type] = acc[s.document_type] || []
    acc[s.document_type].push(s)
    return acc
  }, {})

  const docTypeLabels: Record<string, string> = {
    leave_request: 'Leave Requests',
    loan: 'Loan Applications',
    purchase_request: 'Purchase Requests',
    payroll_run: 'Payroll Runs',
    budget: 'Budget Approvals',
    overtime_request: 'Overtime Requests',
  }

  return (
    <div className="space-y-6">
      <p className="text-sm text-muted-foreground">
        Approval workflow configurations define the multi-step approval chains for each
        document type. Each step specifies the required permission, target status, and
        Separation of Duties (SoD) constraints.
      </p>

      {Object.entries(grouped)
        .sort(([a], [b]) => a.localeCompare(b))
        .map(([docType, items]) => (
        <div key={docType}>
          <h3 className="text-sm font-semibold uppercase tracking-wider text-muted-foreground mb-2">
            {docTypeLabels[docType] ?? docType}
          </h3>
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead className="w-12">Step</TableHead>
                <TableHead>Step Name</TableHead>
                <TableHead>Permission Required</TableHead>
                <TableHead>Target Status</TableHead>
                <TableHead>Amount Threshold</TableHead>
                <TableHead className="text-center">SoD: Creator</TableHead>
                <TableHead className="text-center">SoD: Prev Step</TableHead>
                <TableHead>Active</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {items
                .sort((a, b) => a.step_order - b.step_order)
                .map((s) => (
                <TableRow key={s.id}>
                  <TableCell className="font-mono text-center">{s.step_order}</TableCell>
                  <TableCell className="font-medium">{s.step_name}</TableCell>
                  <TableCell className="font-mono text-xs">{s.required_permission}</TableCell>
                  <TableCell>
                    <Badge variant="outline">{s.target_status}</Badge>
                  </TableCell>
                  <TableCell>
                    {s.amount_threshold_centavos
                      ? `>= PHP ${(s.amount_threshold_centavos / 100).toLocaleString()}`
                      : '-'}
                  </TableCell>
                  <TableCell className="text-center">
                    {s.sod_with_creator
                      ? <CheckCircle2 className="h-4 w-4 text-green-600 inline" />
                      : <XCircle className="h-4 w-4 text-muted-foreground inline" />}
                  </TableCell>
                  <TableCell className="text-center">
                    {s.sod_with_previous_step
                      ? <CheckCircle2 className="h-4 w-4 text-green-600 inline" />
                      : <XCircle className="h-4 w-4 text-muted-foreground inline" />}
                  </TableCell>
                  <TableCell>
                    <Badge variant={s.is_active ? 'default' : 'destructive'}>
                      {s.is_active ? 'Yes' : 'No'}
                    </Badge>
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </div>
      ))}

      {steps.length === 0 && (
        <p className="text-center text-muted-foreground py-8">
          No approval workflow configurations found. Run the ApprovalWorkflowConfigSeeder to populate defaults.
        </p>
      )}
    </div>
  )
}
