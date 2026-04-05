import { describe, it, expect } from 'vitest'
import {
  paginationMetaSchema,
  leaveRequestResponseSchema,
  paginatedLeaveRequestsSchema,
  employeeResponseSchema,
  authUserResponseSchema,
  payrollRunResponseSchema,
  purchaseRequestResponseSchema,
} from './responses'

describe('Response Schemas', () => {
  describe('paginationMetaSchema', () => {
    it('accepts valid pagination meta', () => {
      const result = paginationMetaSchema.safeParse({
        current_page: 1,
        last_page: 5,
        per_page: 15,
        total: 72,
      })
      expect(result.success).toBe(true)
    })

    it('rejects negative total', () => {
      const result = paginationMetaSchema.safeParse({
        current_page: 1,
        last_page: 1,
        per_page: 15,
        total: -1,
      })
      expect(result.success).toBe(false)
    })

    it('rejects missing fields', () => {
      const result = paginationMetaSchema.safeParse({ current_page: 1 })
      expect(result.success).toBe(false)
    })
  })

  describe('leaveRequestResponseSchema', () => {
    it('accepts a full leave request response', () => {
      const result = leaveRequestResponseSchema.safeParse({
        id: 1,
        employee_id: 10,
        leave_type_id: 2,
        date_from: '2026-01-15',
        date_to: '2026-01-17',
        total_days: 3,
        reason: 'Family vacation',
        status: 'pending',
        is_half_day: false,
        half_day_period: null,
        submitted_by: 10,
        reviewed_by: null,
        reviewed_at: null,
        reviewer_remarks: null,
        created_at: '2026-01-10T08:00:00Z',
        employee: {
          id: 10,
          ulid: '01HXYZ',
          employee_code: 'EMP-001',
          full_name: 'Juan dela Cruz',
        },
        leave_type: {
          id: 2,
          code: 'VL',
          name: 'Vacation Leave',
          category: 'earned',
          is_paid: false,
        },
      })
      expect(result.success).toBe(true)
    })

    it('accepts minimal leave request (no optional fields)', () => {
      const result = leaveRequestResponseSchema.safeParse({
        id: 1,
        employee_id: 10,
        leave_type_id: 2,
        date_from: '2026-01-15',
        date_to: '2026-01-17',
        total_days: 3,
        reason: null,
        status: 'pending',
        is_half_day: false,
      })
      expect(result.success).toBe(true)
    })
  })

  describe('paginatedLeaveRequestsSchema', () => {
    it('accepts paginated leave request response', () => {
      const result = paginatedLeaveRequestsSchema.safeParse({
        data: [
          {
            id: 1,
            employee_id: 10,
            leave_type_id: 2,
            date_from: '2026-01-15',
            date_to: '2026-01-17',
            total_days: 3,
            reason: null,
            status: 'approved',
            is_half_day: false,
          },
        ],
        meta: {
          current_page: 1,
          last_page: 1,
          per_page: 15,
          total: 1,
        },
      })
      expect(result.success).toBe(true)
    })
  })

  describe('employeeResponseSchema', () => {
    it('accepts a full employee response', () => {
      const result = employeeResponseSchema.safeParse({
        id: 1,
        ulid: '01HXYZ',
        employee_code: 'EMP-001',
        first_name: 'Juan',
        last_name: 'dela Cruz',
        full_name: 'Juan dela Cruz',
        department_id: 5,
        position_id: 3,
        employment_type: 'regular',
        employment_status: 'active',
        hire_date: '2024-01-15',
        pay_basis: 'monthly',
        basic_monthly_rate: 25000,
        department: { id: 5, code: 'HR', name: 'Human Resources' },
        position: { id: 3, title: 'HR Officer' },
      })
      expect(result.success).toBe(true)
    })
  })

  describe('authUserResponseSchema', () => {
    it('accepts a valid auth user response', () => {
      const result = authUserResponseSchema.safeParse({
        id: 1,
        name: 'Admin User',
        email: 'admin@ogami.test',
        roles: ['super_admin'],
        permissions: ['system.manage'],
        department_ids: [1, 2],
        primary_department_id: 1,
        primary_department_code: 'ADMIN',
        timezone: 'Asia/Manila',
        employee_id: null,
        must_change_password: false,
      })
      expect(result.success).toBe(true)
    })
  })

  describe('payrollRunResponseSchema', () => {
    it('accepts a valid payroll run response', () => {
      const result = payrollRunResponseSchema.safeParse({
        id: 1,
        pay_period_id: 5,
        status: 'computed',
        total_gross: 150000000,
        total_deductions: 30000000,
        total_net: 120000000,
        employee_count: 50,
        computed_by: 1,
        computed_at: '2026-01-31T10:00:00Z',
        approved_by: null,
        approved_at: null,
      })
      expect(result.success).toBe(true)
    })
  })

  describe('purchaseRequestResponseSchema', () => {
    it('accepts a valid PR response with items', () => {
      const result = purchaseRequestResponseSchema.safeParse({
        id: 1,
        pr_reference: 'PR-2026-0001',
        department_id: 3,
        requested_by_id: 10,
        requested_by: { id: 10, name: 'Juan dela Cruz' },
        urgency: 'normal',
        justification: 'Office supplies needed',
        status: 'draft',
        total_estimated_cost: 5000,
        items: [
          {
            id: 1,
            item_description: 'Bond paper A4',
            unit_of_measure: 'ream',
            quantity: 10,
            estimated_unit_cost: 250,
            estimated_total: 2500,
            line_order: 1,
          },
        ],
      })
      expect(result.success).toBe(true)
    })

    it('accepts a minimal PR response (no optional fields)', () => {
      const result = purchaseRequestResponseSchema.safeParse({
        id: 2,
        pr_reference: 'PR-2026-0002',
        department_id: 5,
        requested_by_id: 12,
        urgency: 'urgent',
        justification: 'Urgent production supplies',
        status: 'pending_review',
        total_estimated_cost: 15000,
      })
      expect(result.success).toBe(true)
    })
  })
})
