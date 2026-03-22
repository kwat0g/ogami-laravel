import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import api from '@/lib/api';
import { firstErrorMessage } from '@/lib/errorHandler';
import type {
  Equipment,
  MaintenanceWorkOrder,
  CreateEquipmentPayload,
  CreateWorkOrderPayload,
  CompleteWorkOrderPayload,
} from '@/types/maintenance';

// ── Equipment ──────────────────────────────────────────────────────────────

export function useEquipment(params?: Record<string, string | number | boolean>) {
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
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['equipment'] });
      toast.success('Equipment created successfully');
    },
    onError: (err) => toast.error(firstErrorMessage(err)),
  });
}

export function useDeleteEquipment() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (ulid: string) =>
      api.delete(`/maintenance/equipment/${ulid}`).then(r => r.data),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['equipment'] });
      toast.success('Equipment deleted successfully');
    },
    onError: (err) => toast.error(firstErrorMessage(err)),
  });
}

// ── Work Orders ────────────────────────────────────────────────────────────

export function useWorkOrders(params?: Record<string, string | number | boolean>) {
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
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['work-orders'] });
      toast.success('Work order created successfully');
    },
    onError: (err) => toast.error(firstErrorMessage(err)),
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
      toast.success('Work order started');
    },
    onError: (err) => toast.error(firstErrorMessage(err)),
  });
}

export function useCancelWorkOrder() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (ulid: string) =>
      api.patch(`/maintenance/work-orders/${ulid}/cancel`).then(r => r.data),
    onSuccess: (_d, ulid) => {
      qc.invalidateQueries({ queryKey: ['work-orders'] });
      qc.invalidateQueries({ queryKey: ['work-orders', ulid] });
      toast.success('Work order cancelled');
    },
    onError: (err) => toast.error(firstErrorMessage(err)),
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
      toast.success('Work order completed');
    },
    onError: (err) => toast.error(firstErrorMessage(err)),
  });
}

// ── PM Schedules ───────────────────────────────────────────────────────────

export function useStorePmSchedule(equipmentUlid: string) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (payload: { task_name: string; frequency_days: number; last_done_on?: string }) =>
      api.post(`/maintenance/equipment/${equipmentUlid}/pm-schedules`, payload).then(r => r.data),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['equipment'] });
      qc.invalidateQueries({ queryKey: ['equipment', equipmentUlid] });
      toast.success('PM schedule added');
    },
    onError: (err) => toast.error(firstErrorMessage(err)),
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
      toast.success('Equipment updated');
    },
    onError: (err) => toast.error(firstErrorMessage(err)),
  });
}
