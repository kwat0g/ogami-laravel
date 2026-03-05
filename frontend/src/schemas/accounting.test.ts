import { describe, it, expect } from 'vitest'
import { journalEntrySchema, journalEntryLineSchema } from '@/schemas/accounting'

// ---------------------------------------------------------------------------
// journalEntryLineSchema
// ---------------------------------------------------------------------------

describe('journalEntryLineSchema', () => {
  it('accepts a valid debit-only line', () => {
    const result = journalEntryLineSchema.safeParse({ account_id: 1, debit: 100, credit: 0 })
    expect(result.success).toBe(true)
  })

  it('accepts a valid credit-only line', () => {
    const result = journalEntryLineSchema.safeParse({ account_id: 1, debit: 0, credit: 100 })
    expect(result.success).toBe(true)
  })

  it('rejects a line with both debit and credit > 0 (contra-line not allowed)', () => {
    const result = journalEntryLineSchema.safeParse({ account_id: 1, debit: 100, credit: 50 })
    expect(result.success).toBe(false)
  })

  it('rejects a line with debit = 0 and credit = 0 (empty line)', () => {
    const result = journalEntryLineSchema.safeParse({ account_id: 1, debit: 0, credit: 0 })
    expect(result.success).toBe(false)
  })

  it('rejects a negative debit', () => {
    const result = journalEntryLineSchema.safeParse({ account_id: 1, debit: -100, credit: 0 })
    expect(result.success).toBe(false)
  })

  it('rejects a negative credit', () => {
    const result = journalEntryLineSchema.safeParse({ account_id: 1, debit: 0, credit: -50 })
    expect(result.success).toBe(false)
  })

  it('rejects missing account_id', () => {
    const result = journalEntryLineSchema.safeParse({ debit: 100, credit: 0 })
    expect(result.success).toBe(false)
  })
})

// ---------------------------------------------------------------------------
// journalEntrySchema — balanced entry
// ---------------------------------------------------------------------------

const balancedEntry = {
  entry_date: '2026-02-01',
  description: 'Test journal entry',
  fiscal_period_id: 1,
  lines: [
    { account_id: 1, debit: 1000, credit: 0 },
    { account_id: 2, debit: 0, credit: 1000 },
  ],
}

describe('journalEntrySchema', () => {
  it('accepts a valid balanced 2-line entry', () => {
    const result = journalEntrySchema.safeParse(balancedEntry)
    expect(result.success).toBe(true)
  })

  it('rejects when total debits ≠ total credits (unbalanced)', () => {
    const result = journalEntrySchema.safeParse({
      ...balancedEntry,
      lines: [
        { account_id: 1, debit: 1000, credit: 0 },
        { account_id: 2, debit: 0, credit: 900 },   // 100 short
      ],
    })
    expect(result.success).toBe(false)
    if (!result.success) {
      const msgs = result.error.flatten().fieldErrors
      expect(JSON.stringify(msgs)).toMatch(/balance|equal/i)
    }
  })

  it('rejects a single-line entry (minimum 2 lines required)', () => {
    const result = journalEntrySchema.safeParse({
      ...balancedEntry,
      lines: [{ account_id: 1, debit: 500, credit: 0 }],
    })
    expect(result.success).toBe(false)
  })

  it('rejects empty lines array', () => {
    const result = journalEntrySchema.safeParse({ ...balancedEntry, lines: [] })
    expect(result.success).toBe(false)
  })

  it('rejects when entry_date is missing', () => {
    const result = journalEntrySchema.safeParse({ ...balancedEntry, entry_date: '' })
    expect(result.success).toBe(false)
  })

  it('rejects when description is empty', () => {
    const result = journalEntrySchema.safeParse({ ...balancedEntry, description: '' })
    expect(result.success).toBe(false)
  })

  it('accepts a 3-line balanced entry (compound entry)', () => {
    const result = journalEntrySchema.safeParse({
      ...balancedEntry,
      lines: [
        { account_id: 1, debit: 500, credit: 0 },
        { account_id: 2, debit: 500, credit: 0 },
        { account_id: 3, debit: 0,   credit: 1000 },
      ],
    })
    expect(result.success).toBe(true)
  })

  it('accepts balanced entry with floating-point amounts within tolerance (0.001)', () => {
    // 0.1 + 0.2 in JS is 0.30000000000000004, but tolerance is 0.001
    const result = journalEntrySchema.safeParse({
      ...balancedEntry,
      lines: [
        { account_id: 1, debit: 0.1,  credit: 0 },
        { account_id: 2, debit: 0.2,  credit: 0 },
        { account_id: 3, debit: 0,    credit: 0.3 },
      ],
    })
    expect(result.success).toBe(true)
  })
})
