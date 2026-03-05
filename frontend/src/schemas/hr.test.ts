import { describe, it, expect } from 'vitest'
import { employeeFormSchema, departmentFormSchema } from '@/schemas/hr'

// ---------------------------------------------------------------------------
// employeeFormSchema
// ---------------------------------------------------------------------------

describe('employeeFormSchema', () => {
  const baseValid = {
    first_name: 'Juan',
    last_name: 'dela Cruz',
    birth_date: '1990-01-15',
    gender: 'male',
    civil_status: 'single',
    employee_no: 'EMP-001',
    department_id: 1,
    position_id: 1,
    employment_type: 'regular',
    hire_date: '2020-06-01',
    pay_basis: 'monthly',
    basic_monthly_rate: 20000,
  }

  it('accepts a fully valid employee payload', () => {
    const result = employeeFormSchema.safeParse(baseValid)
    expect(result.success).toBe(true)
  })

  it('rejects when first_name is empty', () => {
    const result = employeeFormSchema.safeParse({ ...baseValid, first_name: '' })
    expect(result.success).toBe(false)
  })

  it('rejects when last_name is empty', () => {
    const result = employeeFormSchema.safeParse({ ...baseValid, last_name: '' })
    expect(result.success).toBe(false)
  })

  it('rejects an invalid civil_status value', () => {
    const result = employeeFormSchema.safeParse({ ...baseValid, civil_status: 'UNKNOWN' })
    expect(result.success).toBe(false)
  })

  it('accepts all valid civil_status enum values', () => {
    const validValues = ['single', 'married', 'widowed', 'separated', 'legally_separated', 'head_of_family']
    for (const civil_status of validValues) {
      const result = employeeFormSchema.safeParse({ ...baseValid, civil_status })
      expect(result.success, `Expected success for civil_status="${civil_status}"`).toBe(true)
    }
  })

  it('rejects qualified_dependents above 10', () => {
    const result = employeeFormSchema.safeParse({ ...baseValid, qualified_dependents: 11 })
    expect(result.success).toBe(false)
  })

  it('accepts qualified_dependents at boundary values 0 and 10', () => {
    expect(employeeFormSchema.safeParse({ ...baseValid, qualified_dependents: 0 }).success).toBe(true)
    expect(employeeFormSchema.safeParse({ ...baseValid, qualified_dependents: 10 }).success).toBe(true)
  })

  it('does NOT have a tax_status field (old field removed — must not exist)', () => {
    // Schema should not accept tax_status — it is ignored or irrelevant
    // If someone passes it, the schema should still parse (extra fields are stripped by Zod)
    // The important thing is that the schema type does NOT include tax_status
    const result = employeeFormSchema.safeParse({ ...baseValid, tax_status: 'ME' })
    // Zod ignores unknown keys by default — parse should succeed, field is stripped
    expect(result.success).toBe(true)
    if (result.success) {
      // tax_status must not leak into the output type
      expect('tax_status' in result.data).toBe(false)
    }
  })

  it('rejects when basic_monthly_rate is zero', () => {
    const result = employeeFormSchema.safeParse({ ...baseValid, basic_monthly_rate: 0 })
    expect(result.success).toBe(false)
  })

  it('rejects invalid hire_date string', () => {
    const result = employeeFormSchema.safeParse({ ...baseValid, hire_date: 'not-a-date' })
    expect(result.success).toBe(false)
  })

  it('rejects invalid birth_date string', () => {
    const result = employeeFormSchema.safeParse({ ...baseValid, birth_date: 'not-a-date' })
    expect(result.success).toBe(false)
  })

  it('rejects invalid employment_type', () => {
    const result = employeeFormSchema.safeParse({ ...baseValid, employment_type: 'intern' })
    expect(result.success).toBe(false)
  })

  it('rejects invalid pay_basis', () => {
    const result = employeeFormSchema.safeParse({ ...baseValid, pay_basis: 'weekly' })
    expect(result.success).toBe(false)
  })

  it('rejects invalid personal_email format', () => {
    const result = employeeFormSchema.safeParse({ ...baseValid, personal_email: 'not-an-email' })
    expect(result.success).toBe(false)
  })

  it('accepts empty personal_email (optional)', () => {
    const result = employeeFormSchema.safeParse({ ...baseValid, personal_email: '' })
    expect(result.success).toBe(true)
  })
})

// ---------------------------------------------------------------------------
// departmentFormSchema
// ---------------------------------------------------------------------------

describe('departmentFormSchema', () => {
  const validDept = {
    name: 'Engineering',
    code: 'ENG',
  }

  it('accepts a valid department', () => {
    expect(departmentFormSchema.safeParse(validDept).success).toBe(true)
  })

  it('rejects code with lowercase letters', () => {
    const result = departmentFormSchema.safeParse({ ...validDept, code: 'eng' })
    expect(result.success).toBe(false)
  })

  it('rejects code longer than 10 characters', () => {
    const result = departmentFormSchema.safeParse({ ...validDept, code: 'TOOLONGCODE' })
    expect(result.success).toBe(false)
  })

  it('rejects empty name', () => {
    const result = departmentFormSchema.safeParse({ ...validDept, name: '' })
    expect(result.success).toBe(false)
  })
})
