import { useState } from 'react'
import { useParams, Link } from 'react-router-dom'
import { CheckCircle, Circle, RefreshCw, ClipboardList, AlertCircle } from 'lucide-react'
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

interface OnboardingItem {
  id: number
  label: string
  category: string
  is_required: boolean
  is_completed: boolean
  completed_at: string | null
  completed_by_id: number | null
  notes: string | null
  sort_order: number
}

interface OnboardingProgress {
  total: number
  completed: number
  required_total: number
  required_completed: number
  percentage: number
  can_activate: boolean
}

const CATEGORY_LABELS: Record<string, string> = {
  documents: 'Personal Documents',
  medical: 'Medical Requirements',
  employment: 'Employment Setup',
  access: 'IT / Access',
  orientation: 'Orientation',
}

function useOnboardingChecklist(employeeId: string) {
  return useQuery({
    queryKey: ['onboarding', employeeId],
    queryFn: async () => {
      const { data } = await api.get<{ checklist: Record<string, OnboardingItem[]>; progress: OnboardingProgress }>(`/hr/onboarding/${employeeId}`)
      return data
    },
    enabled: !!employeeId,
  })
}

export default function OnboardingChecklistPage(): React.ReactElement {
  const { ulid } = useParams<{ ulid: string }>()
  const qc = useQueryClient()
  const { data, isLoading } = useOnboardingChecklist(ulid ?? '')
  const canManage = useAuthStore((s) => s.hasPermission(PERMISSIONS.hr.full_access))

  const initMut = useMutation({
    mutationFn: async () => {
      const res = await api.post(`/hr/onboarding/${ulid}/initialize`)
      return res.data
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['onboarding', ulid] })
      toast.success('Onboarding checklist initialized')
    },
    onError: (e: unknown) => toast.error(firstErrorMessage(e)),
  })

  const checkMut = useMutation({
    mutationFn: async (itemId: number) => {
      const res = await api.patch(`/hr/onboarding/items/${itemId}/check`)
      return res.data
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['onboarding', ulid] })
      toast.success('Item checked')
    },
    onError: (e: unknown) => toast.error(firstErrorMessage(e)),
  })

  const uncheckMut = useMutation({
    mutationFn: async (itemId: number) => {
      const res = await api.patch(`/hr/onboarding/items/${itemId}/uncheck`)
      return res.data
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['onboarding', ulid] })
    },
    onError: (e: unknown) => toast.error(firstErrorMessage(e)),
  })

  if (isLoading) return <SkeletonLoader rows={10} />

  const checklist = data?.checklist ?? {}
  const progress = data?.progress
  const categories = Object.entries(checklist)
  const isEmpty = categories.length === 0 || categories.every(([_, items]) => items.length === 0)

  return (
    <div className="space-y-6">
      <PageHeader
        title="Onboarding Checklist"
        subtitle="Track new employee onboarding progress"
        actions={
          <div className="flex gap-2">
            <Link to={`/hr/employees/${ulid}`} className="btn btn-ghost btn-sm">
              Back to Employee
            </Link>
            {canManage && isEmpty && (
              <button
                onClick={() => initMut.mutate()}
                className="btn btn-primary btn-sm inline-flex items-center gap-2"
                disabled={initMut.isPending}
              >
                <RefreshCw className={`h-4 w-4 ${initMut.isPending ? 'animate-spin' : ''}`} />
                Initialize Checklist
              </button>
            )}
          </div>
        }
      />

      {/* Progress bar */}
      {progress && progress.total > 0 && (
        <Card className="p-4">
          <div className="flex items-center justify-between mb-2">
            <span className="text-sm font-medium">
              {progress.completed} of {progress.total} items completed ({progress.percentage.toFixed(0)}%)
            </span>
            {progress.can_activate ? (
              <span className="text-xs font-medium text-green-700 bg-green-100 px-2 py-1 rounded-full inline-flex items-center gap-1">
                <CheckCircle className="h-3 w-3" /> Ready to activate
              </span>
            ) : (
              <span className="text-xs font-medium text-amber-700 bg-amber-100 px-2 py-1 rounded-full inline-flex items-center gap-1">
                <AlertCircle className="h-3 w-3" /> {progress.required_total - progress.required_completed} required items remaining
              </span>
            )}
          </div>
          <div className="w-full bg-gray-200 rounded-full h-2">
            <div
              className={`h-2 rounded-full transition-all ${progress.can_activate ? 'bg-green-500' : 'bg-blue-500'}`}
              style={{ width: `${progress.percentage}%` }}
            />
          </div>
        </Card>
      )}

      {isEmpty ? (
        <EmptyState
          icon={<ClipboardList className="h-12 w-12 text-gray-400" />}
          title="No checklist items"
          description="Initialize the onboarding checklist to begin tracking new employee requirements."
        />
      ) : (
        categories.map(([category, items]) => (
          <Card key={category} className="p-4">
            <h3 className="text-lg font-semibold mb-3">{CATEGORY_LABELS[category] ?? category}</h3>
            <div className="space-y-1">
              {(items as OnboardingItem[]).map((item) => (
                <div
                  key={item.id}
                  className={`flex items-center justify-between py-2 px-3 rounded-lg transition-colors ${
                    item.is_completed ? 'bg-green-50' : 'hover:bg-gray-50'
                  }`}
                >
                  <div className="flex items-center gap-3 flex-1">
                    {canManage ? (
                      <button
                        onClick={() => item.is_completed ? uncheckMut.mutate(item.id) : checkMut.mutate(item.id)}
                        className="flex-shrink-0"
                        disabled={checkMut.isPending || uncheckMut.isPending}
                      >
                        {item.is_completed ? (
                          <CheckCircle className="h-5 w-5 text-green-600" />
                        ) : (
                          <Circle className="h-5 w-5 text-gray-300 hover:text-gray-500" />
                        )}
                      </button>
                    ) : (
                      item.is_completed ? (
                        <CheckCircle className="h-5 w-5 text-green-600 flex-shrink-0" />
                      ) : (
                        <Circle className="h-5 w-5 text-gray-300 flex-shrink-0" />
                      )
                    )}
                    <div>
                      <span className={`text-sm ${item.is_completed ? 'line-through text-gray-500' : 'text-gray-900'}`}>
                        {item.label}
                      </span>
                      {item.is_required && !item.is_completed && (
                        <span className="ml-2 text-xs text-red-500 font-medium">Required</span>
                      )}
                      {item.completed_at && (
                        <span className="ml-2 text-xs text-gray-400">
                          {new Date(item.completed_at).toLocaleDateString()}
                        </span>
                      )}
                    </div>
                  </div>
                </div>
              ))}
            </div>
          </Card>
        ))
      )}
    </div>
  )
}
