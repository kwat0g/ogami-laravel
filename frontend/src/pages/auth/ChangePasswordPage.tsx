import { useNavigate } from 'react-router-dom'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { toast } from 'sonner'
import { Lock } from 'lucide-react'
import api from '@/lib/api'
import { useAuthStore } from '@/stores/authStore'
import { changePasswordSchema, type ChangePasswordFormValues } from '@/schemas/auth'
import { parseApiError } from '@/lib/errorHandler'
import PageHeader from '@/components/ui/PageHeader'
import FormField from '@/components/ui/FormField'

export default function ChangePasswordPage() {
  const navigate    = useNavigate()
  const _clearAuth   = useAuthStore((s) => s.clearAuth)

  const {
    register,
    handleSubmit,
    setError,
    formState: { errors, isSubmitting },
  } = useForm<ChangePasswordFormValues>({
    resolver: zodResolver(changePasswordSchema),
    mode: 'onBlur',
  })

  const onSubmit = async (values: ChangePasswordFormValues) => {
    try {
      await api.post('/auth/change-password', {
        current_password:      values.current_password,
        password:              values.new_password,
        password_confirmation: values.new_password_confirmation,
      })

      // Update the Zustand store so AppLayout's guard no longer redirects back
      const currentUser = useAuthStore.getState().user
      if (currentUser) {
        useAuthStore.getState().setAuth({
          ...currentUser,
          must_change_password: false,
        })
      }

      toast.success('Password changed successfully.')
      navigate('/', { replace: true })
    } catch (err) {
      const { message, fieldErrors } = parseApiError(err)

      // Map API field names back to form field names
      if (fieldErrors.current_password?.length) {
        setError('current_password', { message: fieldErrors.current_password[0] })
      }
      if (fieldErrors.password?.length) {
        setError('new_password', { message: fieldErrors.password[0] })
      }

      if (!fieldErrors.current_password && !fieldErrors.password) {
      }
    }
  }

  const inputCls =
    'w-full border border-neutral-300 rounded px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-neutral-400'

  return (
    <div className="max-w-md mx-auto">
      <PageHeader
        title="Change Password"
        subtitle="Your new password must differ from the current one and meet the complexity requirements."
      />

      <form onSubmit={handleSubmit(onSubmit)} className="bg-white rounded border border-neutral-200 p-6 space-y-5">
        {/* Current password */}
        <FormField
          label="Current Password"
          required
          error={errors.current_password?.message}
          htmlFor="current_password"
        >
          <input
            id="current_password"
            type="password"
            autoComplete="current-password"
            className={inputCls}
            {...register('current_password')}
          />
        </FormField>

        {/* New password */}
        <FormField
          label="New Password"
          required
          error={errors.new_password?.message}
          htmlFor="new_password"
          hint="Minimum 8 characters — uppercase, lowercase, number, and special character."
        >
          <input
            id="new_password"
            type="password"
            autoComplete="new-password"
            className={inputCls}
            {...register('new_password')}
          />
        </FormField>

        {/* Confirm new password */}
        <FormField
          label="Confirm New Password"
          required
          error={errors.new_password_confirmation?.message}
          htmlFor="new_password_confirmation"
        >
          <input
            id="new_password_confirmation"
            type="password"
            autoComplete="new-password"
            className={inputCls}
            {...register('new_password_confirmation')}
          />
        </FormField>

        {/* Actions */}
        <div className="flex items-center justify-between pt-2">
          <button
            type="button"
            onClick={() => navigate(-1)}
            className="text-sm text-neutral-500 hover:text-neutral-700 transition-colors"
          >
            Cancel
          </button>
          <button
            type="submit"
            disabled={isSubmitting}
            className="inline-flex items-center gap-2 bg-neutral-900 hover:bg-neutral-800 disabled:opacity-50 disabled:cursor-not-allowed text-white text-sm font-medium px-5 py-2 rounded transition-colors"
          >
            <Lock className="h-4 w-4" />
            {isSubmitting ? 'Saving…' : 'Update Password'}
          </button>
        </div>
      </form>
    </div>
  )
}
