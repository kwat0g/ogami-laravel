export const deliveryApiPaths = {
  receipts: '/delivery/receipts',
  receiptByUlid: (ulid: string) => `/delivery/receipts/${ulid}`,
  confirmReceipt: (ulid: string) => `/delivery/receipts/${ulid}/confirm`,
  prepareShipment: (ulid: string) => `/delivery/receipts/${ulid}/prepare-shipment`,
  dispatchReceipt: (ulid: string) => `/delivery/receipts/${ulid}/dispatch`,
  partialDeliverReceipt: (ulid: string) => `/delivery/receipts/${ulid}/partial-deliver`,
  deliverReceipt: (ulid: string) => `/delivery/receipts/${ulid}/deliver`,
  recordPod: (ulid: string) => `/enhancements/delivery/receipts/${ulid}/pod`,
  vehicles: '/delivery/vehicles',
  vehicleHistory: (vehicleId: number) => `/delivery/vehicles/${vehicleId}/history`,
  shipments: '/delivery/shipments',
  shipmentStatus: (ulid: string) => `/delivery/shipments/${ulid}/status`,
} as const;
