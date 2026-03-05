import { z } from 'zod'

// ─── Login ────────────────────────────────────────────────────────────────────

export const loginSchema = z.object({
  email: z
    .string()
    .trim()
    .min(1, 'Email is required')
    .email('Enter a valid email address'),
  password: z
    .string()
    .min(1, 'Password is required'),
})

export type LoginFormValues = z.infer<typeof loginSchema>

// ─── Change Password ──────────────────────────────────────────────────────────

export const changePasswordSchema = z
  .object({
    current_password: z.string().min(1, 'Current password is required'),
    new_password: z
      .string()
      .min(8, 'Password must be at least 8 characters')
      .regex(/[A-Z]/, 'Password must contain at least one uppercase letter')
      .regex(/[a-z]/, 'Password must contain at least one lowercase letter')
      .regex(/[0-9]/, 'Password must contain at least one number')
      .regex(/[^A-Za-z0-9]/, 'Password must contain at least one special character'),
    new_password_confirmation: z.string().min(1, 'Please confirm your new password'),
  })
  .refine((data) => data.new_password === data.new_password_confirmation, {
    message: 'Passwords do not match',
    path: ['new_password_confirmation'],
  })
  .refine((data) => data.current_password !== data.new_password, {
    message: 'New password must differ from your current password',
    path: ['new_password'],
  })

export type ChangePasswordFormValues = z.infer<typeof changePasswordSchema>
