import { z } from 'zod'

// ---------------------------------------------------------------------------
// Shared API response envelope schemas
//
// These Zod schemas validate the shape of API responses coming from the
// Laravel backend.  They match the ApiResponse trait envelope and the
// standard Laravel paginated resource format.
// ---------------------------------------------------------------------------

// ── Pagination meta (matches Laravel's `PaginatedMeta`) ───────────────────

export const paginationMetaSchema = z.object({
  current_page: z.number().int().positive(),
  last_page:    z.number().int().positive(),
  per_page:     z.number().int().positive(),
  total:        z.number().int().nonnegative(),
})

export type PaginationMeta = z.infer<typeof paginationMetaSchema>

// ── Generic paginated response ────────────────────────────────────────────

export function paginatedResponseSchema<T extends z.ZodTypeAny>(itemSchema: T) {
  return z.object({
    data: z.array(itemSchema),
    meta: paginationMetaSchema,
  })
}

// ── Generic single-item response ──────────────────────────────────────────

export function singleResponseSchema<T extends z.ZodTypeAny>(itemSchema: T) {
  return z.object({
    data: itemSchema,
  })
}

// ── Employee summary (embedded in many domain resources) ──────────────────

export const employeeSummarySchema = z.object({
  id:            z.number().int().positive(),
  ulid:          z.string(),
  employee_code: z.string(),
  full_name:     z.string(),
})

// ── Department summary ────────────────────────────────────────────────────

export const departmentSummarySchema = z.object({
  id:   z.number().int().positive(),
  code: z.string(),
  name: z.string(),
})

// ── Leave Type (reference) ────────────────────────────────────────────────

export const leaveTypeSchema = z.object({
  id:       z.number().int().positive(),
  code:     z.string(),
  name:     z.string(),
  category: z.string(),
  is_paid:  z.boolean(),
})

// ── Leave Request response ────────────────────────────────────────────────

export const leaveRequestResponseSchema = z.object({
  id:              z.number().int().positive(),
  ulid:            z.string().optional(),
  employee_id:     z.number().int().positive(),
  leave_type_id:   z.number().int().positive(),
  date_from:       z.string(),
  date_to:         z.string(),
  total_days:      z.number(),
  reason:          z.string().nullable(),
  status:          z.string(),
  is_half_day:     z.boolean(),
  half_day_period: z.enum(['AM', 'PM']).nullable().optional(),
  submitted_by:    z.number().nullable().optional(),
  reviewed_by:     z.number().nullable().optional(),
  reviewed_at:     z.string().nullable().optional(),
  reviewer_remarks: z.string().nullable().optional(),
  action_taken:    z.string().nullable().optional(),
  created_at:      z.string().nullable().optional(),
  updated_at:      z.string().nullable().optional(),
  employee:        employeeSummarySchema.extend({ full_name: z.string() }).partial().nullable().optional(),
  leave_type:      leaveTypeSchema.partial().nullable().optional(),
})

export type LeaveRequestResponse = z.infer<typeof leaveRequestResponseSchema>

export const paginatedLeaveRequestsSchema = paginatedResponseSchema(leaveRequestResponseSchema)

// ── Leave Balance response ────────────────────────────────────────────────

export const leaveBalanceResponseSchema = z.object({
  id:              z.number().int().positive(),
  employee_id:     z.number().int().positive(),
  leave_type_id:   z.number().int().positive(),
  year:            z.number().int(),
  opening_balance: z.number(),
  accrued:         z.number(),
  adjusted:        z.number(),
  used:            z.number(),
  balance:         z.number(),
  created_at:      z.string().nullable().optional(),
  updated_at:      z.string().nullable().optional(),
})

export type LeaveBalanceResponse = z.infer<typeof leaveBalanceResponseSchema>

// ── Employee response ─────────────────────────────────────────────────────

export const employeeResponseSchema = z.object({
  id:                 z.number().int().positive(),
  ulid:               z.string(),
  employee_code:      z.string(),
  first_name:         z.string(),
  last_name:          z.string(),
  middle_name:        z.string().nullable().optional(),
  suffix:             z.string().nullable().optional(),
  full_name:          z.string(),
  birth_date:         z.string().nullable().optional(),
  gender:             z.string().nullable().optional(),
  civil_status:       z.string().nullable().optional(),
  personal_email:     z.string().nullable().optional(),
  mobile_no:          z.string().nullable().optional(),
  department_id:      z.number().nullable().optional(),
  position_id:        z.number().nullable().optional(),
  employment_type:    z.string().nullable().optional(),
  employment_status:  z.string().nullable().optional(),
  hire_date:          z.string().nullable().optional(),
  pay_basis:          z.string().nullable().optional(),
  basic_monthly_rate: z.number().nullable().optional(),
  daily_rate:         z.number().nullable().optional(),
  hourly_rate:        z.number().nullable().optional(),
  department:         departmentSummarySchema.nullable().optional(),
  position:           z.object({
    id:    z.number(),
    code:  z.string().optional(),
    title: z.string(),
  }).nullable().optional(),
  created_at:         z.string().nullable().optional(),
  updated_at:         z.string().nullable().optional(),
})

export type EmployeeResponse = z.infer<typeof employeeResponseSchema>

export const paginatedEmployeesSchema = paginatedResponseSchema(employeeResponseSchema)

// ── Payroll Run response ──────────────────────────────────────────────────

export const payrollRunResponseSchema = z.object({
  id:                z.number().int().positive(),
  ulid:              z.string().optional(),
  pay_period_id:     z.number().int().positive(),
  status:            z.string(),
  total_gross:       z.number(),
  total_deductions:  z.number(),
  total_net:         z.number(),
  employee_count:    z.number().int().nonnegative(),
  computed_by:       z.number().nullable().optional(),
  approved_by:       z.number().nullable().optional(),
  computed_at:       z.string().nullable().optional(),
  approved_at:       z.string().nullable().optional(),
  created_at:        z.string().nullable().optional(),
  updated_at:        z.string().nullable().optional(),
})

export type PayrollRunResponse = z.infer<typeof payrollRunResponseSchema>

export const paginatedPayrollRunsSchema = paginatedResponseSchema(payrollRunResponseSchema)

// ── Purchase Request response ─────────────────────────────────────────────

export const purchaseRequestResponseSchema = z.object({
  id:              z.number().int().positive(),
  ulid:            z.string().optional(),
  pr_number:       z.string(),
  department_id:   z.number().int().positive(),
  requested_by:    z.number().int().positive(),
  status:          z.string(),
  purpose:         z.string().nullable().optional(),
  date_needed:     z.string().nullable().optional(),
  total_amount:    z.number(),
  approved_by:     z.number().nullable().optional(),
  approved_at:     z.string().nullable().optional(),
  department:      departmentSummarySchema.nullable().optional(),
  requester:       employeeSummarySchema.nullable().optional(),
  items:           z.array(z.object({
    id:            z.number().int().positive(),
    item_master_id: z.number().nullable().optional(),
    description:   z.string(),
    quantity:      z.number(),
    unit:          z.string().nullable().optional(),
    unit_cost:     z.number(),
    total_cost:    z.number(),
  })).optional(),
  created_at:      z.string().nullable().optional(),
  updated_at:      z.string().nullable().optional(),
})

export type PurchaseRequestResponse = z.infer<typeof purchaseRequestResponseSchema>

export const paginatedPurchaseRequestsSchema = paginatedResponseSchema(purchaseRequestResponseSchema)

// ── Journal Entry response ────────────────────────────────────────────────

export const journalEntryResponseSchema = z.object({
  id:              z.number().int().positive(),
  ulid:            z.string().optional(),
  entry_no:        z.string().optional(),
  entry_date:      z.string(),
  reference_no:    z.string().nullable().optional(),
  description:     z.string(),
  fiscal_period_id: z.number().int().positive(),
  status:          z.string(),
  total_debit:     z.number(),
  total_credit:    z.number(),
  created_by:      z.number().nullable().optional(),
  approved_by:     z.number().nullable().optional(),
  lines:           z.array(z.object({
    id:            z.number().int().positive(),
    account_id:    z.number().int().positive(),
    debit:         z.number(),
    credit:        z.number(),
    description:   z.string().nullable().optional(),
    account:       z.object({
      id:          z.number(),
      code:        z.string(),
      name:        z.string(),
    }).nullable().optional(),
  })).optional(),
  created_at:      z.string().nullable().optional(),
  updated_at:      z.string().nullable().optional(),
})

export type JournalEntryResponse = z.infer<typeof journalEntryResponseSchema>

export const paginatedJournalEntriesSchema = paginatedResponseSchema(journalEntryResponseSchema)

// ── Vendor response ───────────────────────────────────────────────────────

export const vendorResponseSchema = z.object({
  id:              z.number().int().positive(),
  ulid:            z.string().optional(),
  vendor_code:     z.string(),
  name:            z.string(),
  contact_person:  z.string().nullable().optional(),
  email:           z.string().nullable().optional(),
  phone:           z.string().nullable().optional(),
  address:         z.string().nullable().optional(),
  tin:             z.string().nullable().optional(),
  status:          z.string(),
  payment_terms:   z.string().nullable().optional(),
  created_at:      z.string().nullable().optional(),
  updated_at:      z.string().nullable().optional(),
})

export type VendorResponse = z.infer<typeof vendorResponseSchema>

export const paginatedVendorsSchema = paginatedResponseSchema(vendorResponseSchema)

// ── Inventory Item response ───────────────────────────────────────────────

export const inventoryItemResponseSchema = z.object({
  id:              z.number().int().positive(),
  ulid:            z.string().optional(),
  item_code:       z.string(),
  name:            z.string(),
  description:     z.string().nullable().optional(),
  category_id:     z.number().nullable().optional(),
  unit_of_measure: z.string().nullable().optional(),
  reorder_point:   z.number().nullable().optional(),
  reorder_qty:     z.number().nullable().optional(),
  is_active:       z.boolean(),
  stock_balance:   z.number().nullable().optional(),
  category:        z.object({
    id:   z.number(),
    name: z.string(),
    code: z.string().optional(),
  }).nullable().optional(),
  created_at:      z.string().nullable().optional(),
  updated_at:      z.string().nullable().optional(),
})

export type InventoryItemResponse = z.infer<typeof inventoryItemResponseSchema>

export const paginatedInventoryItemsSchema = paginatedResponseSchema(inventoryItemResponseSchema)

// ── Auth User response ────────────────────────────────────────────────────

export const authUserResponseSchema = z.object({
  id:                     z.number().int().positive(),
  name:                   z.string(),
  email:                  z.string().email(),
  roles:                  z.array(z.string()),
  permissions:            z.array(z.string()),
  department_ids:         z.array(z.number()),
  primary_department_id:  z.number().nullable(),
  primary_department_code: z.string().nullable(),
  timezone:               z.string(),
  employee_id:            z.number().nullable().optional(),
  must_change_password:   z.boolean(),
})

export type AuthUserResponse = z.infer<typeof authUserResponseSchema>
