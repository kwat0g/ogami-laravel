import { useMutation, useQuery, useQueryClient, keepPreviousData } from '@tanstack/react-query';
import api from '@/lib/api';
import { deliveryApiPaths } from '@/lib/deliveryApiPaths';
import type { DeliveryReceipt, CreateDeliveryReceiptPayload, Shipment, CreateShipmentPayload, UpdateShipmentStatusPayload } from '@/types/delivery';

export function useDeliveryReceipts(params?: Record<string, string | boolean>) {
  return useQuery<{ data: DeliveryReceipt[]; meta: unknown }>({
    queryKey: ['delivery-receipts', params],
    queryFn: () => api.get(deliveryApiPaths.receipts, { params }).then(r => r.data),
    placeholderData: keepPreviousData,
  });
}

export function useDeliveryReceipt(ulid: string) {
  return useQuery<{ data: DeliveryReceipt }>({
    queryKey: ['delivery-receipts', ulid],
    queryFn: () => api.get(deliveryApiPaths.receiptByUlid(ulid)).then(r => r.data),
    enabled: !!ulid,
  });
}

export function useCreateDeliveryReceipt() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (payload: CreateDeliveryReceiptPayload) =>
      api.post(deliveryApiPaths.receipts, payload).then(r => r.data),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['delivery-receipts'] }),
  });
}

export function useConfirmDeliveryReceipt() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (ulid: string) =>
      api.patch(deliveryApiPaths.confirmReceipt(ulid)).then(r => r.data),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['delivery-receipts'] }),
  });
}

export function useMarkDispatched() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (ulid: string) =>
      api.patch(deliveryApiPaths.dispatchReceipt(ulid)).then(r => r.data),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['delivery-receipts'] }),
  });
}

export function useMarkPartiallyDelivered() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (ulid: string) =>
      api.patch(deliveryApiPaths.partialDeliverReceipt(ulid)).then(r => r.data),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['delivery-receipts'] }),
  });
}

export function useMarkDelivered() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (ulid: string) =>
      api.patch(deliveryApiPaths.deliverReceipt(ulid)).then(r => r.data),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['delivery-receipts'] }),
  });
}

export function usePrepareShipment() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({ ulid, payload }: { ulid: string; payload: {
      vehicle_id?: number;
      driver_name?: string;
      carrier?: string;
      tracking_number?: string;
      estimated_arrival?: string;
      notes?: string;
    }}) =>
      api.post(deliveryApiPaths.prepareShipment(ulid), payload).then(r => r.data),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['delivery-receipts'] });
      qc.invalidateQueries({ queryKey: ['shipments'] });
    },
  });
}

export function useRecordPod() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({ ulid, payload }: { ulid: string; payload: {
      receiver_name: string;
      receiver_designation?: string;
      signature_base64?: string;
      photo_base64?: string;
      latitude?: number;
      longitude?: number;
      delivery_notes?: string;
    }}) =>
      api.post(deliveryApiPaths.recordPod(ulid), payload).then(r => r.data),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['delivery-receipts'] }),
  });
}

export function useVehicles() {
  return useQuery<{ data: Array<{ id: number; code: string; name: string; type: string; make_model: string; plate_number: string; status: string }> }>({
    queryKey: ['vehicles'],
    queryFn: () => api.get(deliveryApiPaths.vehicles).then(r => r.data),
  });
}

export interface VehicleDeliveryHistory {
  ulid: string
  dr_reference: string
  status: string
  direction: string
  driver_name: string | null
  receipt_date: string | null
  created_at: string
  updated_at: string
  customer_name: string | null
  sales_order: { ulid: string; reference: string } | null
  client_order: { ulid: string; reference: string; status: string } | null
}

export function useVehicleHistory(vehicleId: number | null) {
  return useQuery<{ data: VehicleDeliveryHistory[] }>({
    queryKey: ['vehicle-history', vehicleId],
    queryFn: () => api.get(deliveryApiPaths.vehicleHistory(vehicleId!)).then(r => r.data),
    enabled: !!vehicleId,
  });
}

export function useShipments(params?: Record<string, string | boolean>) {
  return useQuery<{ data: Shipment[]; meta: unknown }>({
    queryKey: ['shipments', params],
    queryFn: () => api.get(deliveryApiPaths.shipments, { params }).then(r => r.data),
    placeholderData: keepPreviousData,
  });
}

export function useCreateShipment() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (payload: CreateShipmentPayload) =>
      api.post(deliveryApiPaths.shipments, payload).then(r => r.data),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['shipments'] }),
  });
}

export function useUpdateShipmentStatus() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({ ulid, payload }: { ulid: string; payload: UpdateShipmentStatusPayload }) =>
      api.patch(deliveryApiPaths.shipmentStatus(ulid), payload).then(r => r.data),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['shipments'] }),
  });
}
