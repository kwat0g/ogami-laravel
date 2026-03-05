import { z } from 'zod'

// ─── Employee Form ────────────────────────────────────────────────────────────

export const employeeFormSchema = z.object({
  // Personal information
  first_name: z.string().trim().min(1, 'First name is required').max(100),
  last_name: z.string().trim().min(1, 'Last name is required').max(100),
  middle_name: z.string().trim().max(100).optional().default(''),
  suffix: z.string().trim().max(20).optional().default(''),
  birth_date: z
    .string()
    .min(1, 'Birth date is required')
    .refine((v) => !isNaN(Date.parse(v)), 'Enter a valid date'),
  gender: z.enum(['male', 'female', 'other'], {
    errorMap: () => ({ message: 'Select a gender' }),
  }),
  civil_status: z.enum(
    ['single', 'married', 'widowed', 'separated', 'legally_separated', 'head_of_family'],
    { errorMap: () => ({ message: 'Select a civil status' }) },
  ),
  qualified_dependents: z.coerce
    .number({ invalid_type_error: 'Enter a whole number' })
    .int()
    .min(0)
    .max(10)
    .default(0),

  // Contact
  personal_email: z.string().trim().email('Enter a valid email').optional().or(z.literal('')),
  mobile_no: z.string().trim().max(20).optional().default(''),

  // Employment
  employee_no: z.string().trim().min(1, 'Employee number is required').max(20),
  department_id: z.coerce.number({ invalid_type_error: 'Select a department' }).positive(),
  position_id: z.coerce.number({ invalid_type_error: 'Select a position' }).positive(),
  salary_grade_id: z.coerce.number().positive().optional(),
  employment_type: z.enum(['regular', 'probationary', 'contractual', 'seasonal'], {
    errorMap: () => ({ message: 'Select an employment type' }),
  }),
  hire_date: z
    .string()
    .min(1, 'Hire date is required')
    .refine((v) => !isNaN(Date.parse(v)), 'Enter a valid date'),

  // Pay
  pay_basis: z.enum(['monthly', 'daily', 'hourly'], {
    errorMap: () => ({ message: 'Select a pay basis' }),
  }),
  basic_monthly_rate: z.coerce
    .number({ invalid_type_error: 'Enter an amount' })
    .min(1, 'Monthly rate must be greater than zero'),

  // Government IDs
  sss_no: z.string().trim().max(30).optional().default(''),
  philhealth_no: z.string().trim().max(30).optional().default(''),
  pagibig_no: z.string().trim().max(30).optional().default(''),
  tin: z.string().trim().max(30).optional().default(''),
})

export type EmployeeFormValues = z.infer<typeof employeeFormSchema>

// ─── Position ─────────────────────────────────────────────────────────────────

export const positionFormSchema = z.object({
  title: z.string().trim().min(1, 'Position title is required').max(150),
  department_id: z.coerce.number({ invalid_type_error: 'Select a department' }).positive(),
  salary_grade_id: z.coerce.number().positive().optional(),
  is_active: z.boolean().default(true),
  description: z.string().trim().max(500).optional().default(''),
})

export type PositionFormValues = z.infer<typeof positionFormSchema>

// ─── Department ───────────────────────────────────────────────────────────────

export const departmentFormSchema = z.object({
  name: z.string().trim().min(1, 'Department name is required').max(150),
  code: z
    .string()
    .trim()
    .min(1, 'Code is required')
    .max(10)
    .regex(/^[A-Z0-9-]+$/, 'Code must be uppercase letters, numbers, or hyphens'),
  parent_department_id: z.coerce.number().positive().optional(),
  manager_employee_id: z.coerce.number().positive().optional(),
  is_active: z.boolean().default(true),
})

export type DepartmentFormValues = z.infer<typeof departmentFormSchema>
