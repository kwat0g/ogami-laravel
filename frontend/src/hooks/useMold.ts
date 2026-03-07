import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import api from '@/lib/api';
import type { MoldMaster, CreateMoldPayload, LogShotsPayload } from '@/types/mold';
import type { Paginated } from '@/types/production';

export function useMolds(params?: Record<string, string | number | boolean>) {
  return useQuery<Paginated<MoldMaster>>({
    queryKey: ['molds', params],
    queryFn: () => api.get('/mold/molds', { params }).then(r => r.data),
  });
}

export function useMold(ulid: string) {
  return useQuery<{ data: MoldMaster }>({
    queryKey: ['molds', ulid],
    queryFn: () => api.get(`/mold/molds/${ulid}`).then(r => r.data),
    enabled: !!ulid,
  });
}

export function useCreateMold() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (payload: CreateMoldPayload) =>
      api.post('/mold/molds', payload).then(r => r.data),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['molds'] }),
  });
}

export function useUpdateMold(ulid: string) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (payload: Partial<CreateMoldPayload>) =>
      api.put(`/mold/molds/${ulid}`, payload).then(r => r.data),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['molds'] });
      qc.invalidateQueries({ queryKey: ['molds', ulid] });
    },
  });
}

export function useLogShots(ulid: string) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (payload: LogShotsPayload) =>
      api.post(`/mold/molds/${ulid}/shots`, payload).then(r => r.data),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['molds'] });
      qc.invalidateQueries({ queryKey: ['molds', ulid] });
    },
  });
}
