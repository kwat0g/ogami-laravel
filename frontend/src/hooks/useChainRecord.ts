import { useQuery } from '@tanstack/react-query'
import api from '@/lib/api'

export interface ChainNode {
  type: string
  id: number
  reference: string
  status: string
  date: string
  actor: string | null
  url: string | null
}

export function useChainRecord(documentType: string | null, documentId: number | null) {
  return useQuery({
    queryKey: ['chain-record', documentType, documentId],
    queryFn: async () => {
      const res = await api.get<{ data: ChainNode[] }>(
        `/chain-record/${documentType}/${documentId}`,
      )
      return res.data.data
    },
    enabled: documentType !== null && documentId !== null,
    staleTime: 60_000,
  })
}
