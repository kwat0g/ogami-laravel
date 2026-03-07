import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import api from '@/lib/api';
import type { DeliveryReceipt, CreateDeliveryReceiptPayload, Shipment } from '@/types/delivery';

export function useDeliveryReceipts(params?: Record<string, string | boolean>) {
  return useQuery<{ data: DeliveryReceipt[]; meta: unknown }>({
    queryKey: ['delivery-receipts', params],
    queryFn: () => api.get('/delivery/receipts', { params }).then(r => r.data),
  });
}

export function useDeliveryReceipt(ulid: string) {
  return useQuery<{ data: DeliveryReceipt }>({
    queryKey: ['delivery-receipts', ulid],
    queryFn: () => api.get(`/delivery/receipts/${ulid}`).then(r => r.data),
    enabled: !!ulid,
  });
}

export function useCreateDeliveryReceipt() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (payload: CreateDeliveryReceiptPayload) =>
      api.post('/delivery/receipts', payload).then(r => r.data),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['delivery-receipts'] }),
  });
}

export function useConfirmDeliveryReceipt() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (ulid: string) =>
      api.patch(`/delivery/receipts/${ulid}/confirm`).then(r => r.data),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['delivery-receipts'] }),
  });
}

export function useShipments(params?: Record<string, string | boolean>) {
  return useQuery<{ data: Shipment[]; meta: unknown }>({
    queryKey: ['shipments', params],
    queryFn: () => api.get('/delivery/shipments', { params }).then(r => r.data),
  });
}
