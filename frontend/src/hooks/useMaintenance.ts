import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import api from '@/lib/api';
import type {
  Equipment,
  MaintenanceWorkOrder,
  CreateEquipmentPayload,
  CreateWorkOrderPayload,
  CompleteWorkOrderPayload,
} from '@/types/maintenance';

// ── Equipment ──────────────────────────────────────────────────────────────

export function useEquipment(params?: Record<string, string | number>) {
  return useQuery<{ data: Equipment[]; meta: unknown }>({
    queryKey: ['equipment', params],
    queryFn: () => api.get('/maintenance/equipment', { params }).then(r => r.data),
  });
}

export function useEquipmentDetail(ulid: string) {
  return useQuery<{ data: Equipment }>({
    queryKey: ['equipment', ulid],
    queryFn: () => api.get(`/maintenance/equipment/${ulid}`).then(r => r.data),
    enabled: !!ulid,
  });
}

export function useCreateEquipment() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (payload: CreateEquipmentPayload) =>
      api.post('/maintenance/equipment', payload).then(r => r.data),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['equipment'] }),
  });
}

export function useUpdateEquipment(ulid: string) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (payload: Partial<CreateEquipmentPayload>) =>
      api.put(`/maintenance/equipment/${ulid}`, payload).then(r => r.data),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['equipment'] });
      qc.invalidateQueries({ queryKey: ['equipment', ulid] });
    },
  });
}

// ── Work Orders ────────────────────────────────────────────────────────────

export function useWorkOrders(params?: Record<string, string | number>) {
  return useQuery<{ data: MaintenanceWorkOrder[]; meta: unknown }>({
    queryKey: ['work-orders', params],
    queryFn: () => api.get('/maintenance/work-orders', { params }).then(r => r.data),
  });
}

export function useWorkOrderDetail(ulid: string) {
  return useQuery<{ data: MaintenanceWorkOrder }>({
    queryKey: ['work-orders', ulid],
    queryFn: () => api.get(`/maintenance/work-orders/${ulid}`).then(r => r.data),
    enabled: !!ulid,
  });
}

export function useCreateWorkOrder() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (payload: CreateWorkOrderPayload) =>
      api.post('/maintenance/work-orders', payload).then(r => r.data),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['work-orders'] }),
  });
}

export function useStartWorkOrder() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (ulid: string) =>
      api.patch(`/maintenance/work-orders/${ulid}/start`).then(r => r.data),
    onSuccess: (_d, ulid) => {
      qc.invalidateQueries({ queryKey: ['work-orders'] });
      qc.invalidateQueries({ queryKey: ['work-orders', ulid] });
    },
  });
}

export function useCompleteWorkOrder() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({ ulid, payload }: { ulid: string; payload: CompleteWorkOrderPayload }) =>
      api.patch(`/maintenance/work-orders/${ulid}/complete`, payload).then(r => r.data),
    onSuccess: (_d, { ulid }) => {
      qc.invalidateQueries({ queryKey: ['work-orders'] });
      qc.invalidateQueries({ queryKey: ['work-orders', ulid] });
      qc.invalidateQueries({ queryKey: ['equipment'] });
    },
  });
}
