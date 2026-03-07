import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import api from '@/lib/api';
import type {
  ControlledDocument, InternalAudit,
  CreateDocumentPayload, CreateAuditPayload, CreateFindingPayload,
} from '@/types/iso';

export function useDocuments(params?: Record<string, string | boolean>) {
  return useQuery<{ data: ControlledDocument[]; meta: unknown }>({
    queryKey: ['iso-documents', params],
    queryFn: () => api.get('/iso/documents', { params }).then(r => r.data),
  });
}

export function useDocument(ulid: string) {
  return useQuery<{ data: ControlledDocument }>({
    queryKey: ['iso-documents', ulid],
    queryFn: () => api.get(`/iso/documents/${ulid}`).then(r => r.data),
    enabled: !!ulid,
  });
}

export function useCreateDocument() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (payload: CreateDocumentPayload) =>
      api.post('/iso/documents', payload).then(r => r.data),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['iso-documents'] }),
  });
}

export function useAudits(params?: Record<string, string | boolean>) {
  return useQuery<{ data: InternalAudit[]; meta: unknown }>({
    queryKey: ['iso-audits', params],
    queryFn: () => api.get('/iso/audits', { params }).then(r => r.data),
  });
}

export function useAudit(ulid: string) {
  return useQuery<{ data: InternalAudit }>({
    queryKey: ['iso-audits', ulid],
    queryFn: () => api.get(`/iso/audits/${ulid}`).then(r => r.data),
    enabled: !!ulid,
  });
}

export function useCreateAudit() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (payload: CreateAuditPayload) =>
      api.post('/iso/audits', payload).then(r => r.data),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['iso-audits'] }),
  });
}

export function useStartAudit() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (ulid: string) =>
      api.patch(`/iso/audits/${ulid}/start`).then(r => r.data),
    onSuccess: (_d, ulid) => {
      qc.invalidateQueries({ queryKey: ['iso-audits'] });
      qc.invalidateQueries({ queryKey: ['iso-audits', ulid] });
    },
  });
}

export function useCompleteAudit() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({ ulid, summary }: { ulid: string; summary?: string }) =>
      api.patch(`/iso/audits/${ulid}/complete`, { summary }).then(r => r.data),
    onSuccess: (_d, { ulid }) => {
      qc.invalidateQueries({ queryKey: ['iso-audits'] });
      qc.invalidateQueries({ queryKey: ['iso-audits', ulid] });
    },
  });
}

export function useCreateFinding(auditUlid: string) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (payload: CreateFindingPayload) =>
      api.post(`/iso/audits/${auditUlid}/findings`, payload).then(r => r.data),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['iso-audits', auditUlid] }),
  });
}
