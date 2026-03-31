export const deliveryApiPaths = {
  receipts: '/delivery/receipts',
  receiptByUlid: (ulid: string) => `/delivery/receipts/${ulid}`,
  confirmReceipt: (ulid: string) => `/delivery/receipts/${ulid}/confirm`,
  dispatchReceipt: (ulid: string) => `/delivery/receipts/${ulid}/dispatch`,
  partialDeliverReceipt: (ulid: string) => `/delivery/receipts/${ulid}/partial-deliver`,
  deliverReceipt: (ulid: string) => `/delivery/receipts/${ulid}/deliver`,
  shipments: '/delivery/shipments',
  shipmentStatus: (ulid: string) => `/delivery/shipments/${ulid}/status`,
  routes: '/delivery/routes',
} as const;
