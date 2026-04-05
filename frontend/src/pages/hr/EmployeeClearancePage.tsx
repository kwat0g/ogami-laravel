import { useState } from 'react'
import { useParams, Link } from 'react-router-dom'
import { CheckCircle, XCircle, AlertTriangle, FileText, RefreshCw } from 'lucide-react'
import { toast } from 'sonner'
import { firstErrorMessage } from '@/lib/errorHandler'
import api from '@/lib/api'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { useAuthStore } from '@/stores/authStore'
import { PERMISSIONS } from '@/lib/permissions'
import { PageHeader } from '@/components/ui/PageHeader'
import { Card } from '@/components/ui/Card'
import EmptyState from '@/components/ui/EmptyState'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import StatusBadge from '@/components/ui/StatusBadge'

interface ClearanceItem {
  id: number
  department_code: string
  item_description: string
  status: 'pending' | 'cleared' | 'blocked'
  cleared_by_name: string | null
  cleared_at: string | null
  notes: string | null
}

interface ClearanceSummary {
  [department: string]: {
    total: number
    cleared: number
    pending: number
    blocked: number
    items: ClearanceItem[]
  }
}

function useClearanceSummary(employeeId: string) {
  return useQuery({
    queryKey: ['employee-clearance', employeeId],
    queryFn: async () => {
      const { data } = await api.get<{ data: ClearanceSummary }>(`/hr/clearance/${employeeId}`)
      return data.data
    },
    enabled: !!employeeId,
  })
}

function useGenerateClearance() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (employeeId: string) => {
      const { data } = await api.post(`/hr/clearance/${employeeId}/generate`)
      return data
    },
    onSuccess: (_d, employeeId) => {
      qc.invalidateQueries({ queryKey: ['employee-clearance', employeeId] })
    },
  })
}

function useClearItem() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async ({ clearanceId, notes }: { clearanceId: number; notes?: string }) => {
      const { data } = await api.patch(`/hr/clearance/items/${clearanceId}/clear`, { notes })
      return data.data
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['employee-clearance'] })
    },
  })
}

function useBlockItem() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async ({ clearanceId, reason }: { clearanceId: number; reason: string }) => {
      const { data } = await api.patch(`/hr/clearance/items/${clearanceId}/block`, { reason })
      return data.data
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['employee-clearance'] })
    },
  })
}

const STATUS_COLORS: Record<string, string> = {
  pending: 'bg-yellow-100 text-yellow-700',
  cleared: 'bg-green-100 text-green-700',
  blocked: 'bg-red-100 text-red-700',
}

export default function EmployeeClearancePage(): React.ReactElement {
  const { ulid } = useParams<{ ulid: string }>()
  const { data: summary, isLoading } = useClearanceSummary(ulid ?? '')
  const generateMut = useGenerateClearance()
  const clearMut = useClearItem()
  const blockMut = useBlockItem()
  const canManage = useAuthStore((s) => s.hasPermission(PERMISSIONS.hr.full_access))
  const [blockReason, setBlockReason] = useState('')
  const [activeBlockId, setActiveBlockId] = useState<number | null>(null)

  const handleGenerate = async () => {
    if (!ulid) return
    try {
      await generateMut.mutateAsync(ulid)
      toast.success('Clearance checklist generated')
    } catch (e) {
      toast.error(firstErrorMessage(e))
    }
  }

  const handleClear = async (clearanceId: number) => {
    try {
      await clearMut.mutateAsync({ clearanceId })
      toast.success('Item cleared')
    } catch (e) {
      toast.error(firstErrorMessage(e))
    }
  }

  const handleBlock = async (clearanceId: number) => {
    if (!blockReason.trim()) {
      return
    }
    try {
      await blockMut.mutateAsync({ clearanceId, reason: blockReason })
      toast.success('Item blocked')
      setActiveBlockId(null)
      setBlockReason('')
    } catch (e) {
      toast.error(firstErrorMessage(e))
    }
  }

  if (isLoading) return <SkeletonLoader rows={8} />

  const departments = summary ? Object.entries(summary) : []
  const isEmpty = departments.length === 0 || departments.every(([_, dept]) => dept.items?.length === 0)

  return (
    <div className="space-y-6">
      <PageHeader
        title="Employee Clearance"
        subtitle="Exit clearance checklist for resigning/terminated employee"
        actions={
          <div className="flex gap-2">
            <Link to={`/hr/employees/${ulid}`} className="btn btn-ghost btn-sm">
              Back to Employee
            </Link>
            {canManage && isEmpty && (
              <button
                onClick={handleGenerate}
                className="btn btn-primary btn-sm inline-flex items-center gap-2"
                disabled={generateMut.isPending}
              >
                <RefreshCw className={`h-4 w-4 ${generateMut.isPending ? 'animate-spin' : ''}`} />
                Generate Checklist
              </button>
            )}
          </div>
        }
      />

      {isEmpty ? (
        <EmptyState
          icon={<FileText className="h-12 w-12 text-gray-400" />}
          title="No clearance items"
          description="Generate a clearance checklist to begin the exit process."
        />
      ) : (
        departments.map(([deptCode, dept]) => (
          <Card key={deptCode} className="p-4">
            <div className="flex items-center justify-between mb-3">
              <h3 className="text-lg font-semibold">{deptCode}</h3>
              <span className="text-sm text-gray-500">
                {dept.cleared}/{dept.total} cleared
                {dept.blocked > 0 && <span className="text-red-600 ml-2">({dept.blocked} blocked)</span>}
              </span>
            </div>
            <div className="space-y-2">
              {dept.items?.map((item) => (
                <div key={item.id} className="flex items-center justify-between py-2 border-b border-gray-100 last:border-0">
                  <div className="flex-1">
                    <p className="text-sm font-medium">{item.item_description}</p>
                    {item.notes && <p className="text-xs text-gray-500 mt-1">{item.notes}</p>}
                    {item.cleared_by_name && (
                      <p className="text-xs text-gray-400 mt-1">Cleared by {item.cleared_by_name} on {item.cleared_at}</p>
                    )}
                  </div>
                  <div className="flex items-center gap-2">
                    <span className={`px-2 py-0.5 text-xs rounded-full ${STATUS_COLORS[item.status] ?? ''}`}>
                      {item.status}
                    </span>
                    {canManage && item.status === 'pending' && (
                      <>
                        <button
                          onClick={() => handleClear(item.id)}
                          className="btn btn-xs btn-success"
                          disabled={clearMut.isPending}
                          title="Mark as cleared"
                        >
                          <CheckCircle className="h-3 w-3" />
                        </button>
                        <button
                          onClick={() => setActiveBlockId(item.id)}
                          className="btn btn-xs btn-error"
                          title="Block"
                        >
                          <XCircle className="h-3 w-3" />
                        </button>
                      </>
                    )}
                  </div>
                  {activeBlockId === item.id && (
                    <div className="flex gap-2 mt-2 ml-4">
                      <input
                        type="text"
                        placeholder="Block reason..."
                        value={blockReason}
                        onChange={(e) => setBlockReason(e.target.value)}
                        className="input input-sm flex-1"
                      />
                      <button onClick={() => handleBlock(item.id)} className="btn btn-xs btn-error">
                        Confirm
                      </button>
                      <button onClick={() => { setActiveBlockId(null); setBlockReason('') }} className="btn btn-xs btn-ghost">
                        Cancel
                      </button>
                    </div>
                  )}
                </div>
              ))}
            </div>
          </Card>
        ))
      )}
    </div>
  )
}
