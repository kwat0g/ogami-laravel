import { useMutation, useQuery } from '@tanstack/react-query'
import api from '@/lib/api'
import type { JobPostingListItem, Paginated } from '@/types/recruitment'

const KEYS = {
  postings: ['public-recruitment', 'postings'] as const,
}

export function usePublicRecruitmentPostings() {
  return useQuery({
    queryKey: KEYS.postings,
    queryFn: async () => {
      const { data } = await api.get<Paginated<JobPostingListItem>>('/public/recruitment/postings')
      return data
    },
  })
}

export function useSubmitPublicApplication() {
  return useMutation({
    mutationFn: async (payload: FormData) => {
      const { data } = await api.post('/public/recruitment/applications', payload, {
        headers: { 'Content-Type': 'multipart/form-data' },
      })
      return data
    },
  })
}
