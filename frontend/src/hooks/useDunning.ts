import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import api from '@/lib/api'

export interface DunningNotice {
  id: number; ulid: string; customer_id: number
  customer?: { id: number; name: string }
  customer_invoice_id: number; dunning_level_id: number
  dunningLevel?: { id: number; level: number; name: string }
  amount_due_centavos: number; days_overdue: number
  status: string; sent_at: string | null; notes: string | null
  created_at: string
}

export function useDunningNotices(filters: Record<string, unknown> = {}) {
  return useQuery({
    queryKey: ['dunning-notices', filters],
    queryFn: async () => {
      const { data } = await api.get('/ar/dunning/notices', { params: filters })
      return { data: data.data, meta: { current_page: data.current_page, last_page: data.last_page, per_page: data.per_page, total: data.total } }
    },
  })
}

export function useGenerateDunning() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async () => { const { data } = await api.post('/ar/dunning/generate'); return data },
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['dunning-notices'] }) },
  })
}

export function useSendDunning(ulid: string) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async () => { const { data } = await api.patch(`/ar/dunning/notices/${ulid}/send`); return data.data },
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['dunning-notices'] }) },
  })
}

export function useResolveDunning(ulid: string) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (payload: { notes: string }) => { const { data } = await api.patch(`/ar/dunning/notices/${ulid}/resolve`, payload); return data.data },
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['dunning-notices'] }) },
  })
}
