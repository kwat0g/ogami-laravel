import { describe, expect, it } from 'vitest'
import { deliveryApiPaths } from '@/lib/deliveryApiPaths'

describe('deliveryApiPaths', () => {
  it('uses stable delivery base paths', () => {
    expect(deliveryApiPaths.receipts).toBe('/delivery/receipts')
    expect(deliveryApiPaths.shipments).toBe('/delivery/shipments')
    expect(deliveryApiPaths.routes).toBe('/delivery/routes')
  })

  it('builds DR action paths from ulid', () => {
    const ulid = '01HZY5ABCD1234EFGH5678JKLM'
    expect(deliveryApiPaths.receiptByUlid(ulid)).toBe(`/delivery/receipts/${ulid}`)
    expect(deliveryApiPaths.confirmReceipt(ulid)).toBe(`/delivery/receipts/${ulid}/confirm`)
    expect(deliveryApiPaths.dispatchReceipt(ulid)).toBe(`/delivery/receipts/${ulid}/dispatch`)
    expect(deliveryApiPaths.partialDeliverReceipt(ulid)).toBe(`/delivery/receipts/${ulid}/partial-deliver`)
    expect(deliveryApiPaths.deliverReceipt(ulid)).toBe(`/delivery/receipts/${ulid}/deliver`)
  })

  it('builds shipment status path from ulid', () => {
    const ulid = '01HZY5ABCD1234EFGH5678JKLM'
    expect(deliveryApiPaths.shipmentStatus(ulid)).toBe(`/delivery/shipments/${ulid}/status`)
  })
})
