import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { toast } from 'sonner'
import axios from 'axios'
import { useQueryClient } from '@tanstack/react-query'
import api from '@/lib/api'
import { getLandingPath } from '@/lib/roleLanding'
import { bumpAuthEpoch } from '@/lib/authEpoch'
import { useAuthStore } from '@/stores/authStore'
import type { ApiSuccess, LoginResult } from '@/types/api'

const schema = z.object({
  email:    z.string().email('Enter a valid email'),
  password: z.string().min(8, 'Minimum 8 characters'),
})

type FormValues = z.infer<typeof schema>

export default function LoginPage() {
  const navigate      = useNavigate()
  const queryClient   = useQueryClient()
  const { setAuth } = useAuthStore()
  const [loading, setLoading] = useState(false)

  const { register, handleSubmit, formState: { errors } } = useForm<FormValues>({
    resolver: zodResolver(schema),
    mode: 'onChange',
  })

  const onSubmit = async (values: FormValues) => {
    setLoading(true)
    try {
      // Fetch CSRF cookie so Sanctum can validate subsequent requests.
      await axios.get('/sanctum/csrf-cookie', { withCredentials: true })

      const res = await api.post<ApiSuccess<LoginResult>>('/auth/login', {
        ...values,
        device_name: navigator.userAgent.slice(0, 100),
      })

      const result = res.data.data

      if (result.user) {
        // Ensure stale in-flight requests from a prior session cannot affect
        // the newly authenticated user context.
        await queryClient.cancelQueries()
        queryClient.clear()
        bumpAuthEpoch()
        queryClient.setQueryData(['auth', 'me'], result.user)
        setAuth(result.user)
        navigate(getLandingPath(result.user))
      }
    } catch (err: unknown) {
      // M12 FIX: Properly type the error response from the API interceptor.
      // The interceptor returns the full ApiError shape, not just { message }.
      const apiErr = err as { message?: string; error_code?: string; errors?: Record<string, string[]> }
      const message = apiErr?.message ?? 'Login failed. Please try again.'
      toast.error(message)
    } finally {
      setLoading(false)
    }
  }

  return (
    <form onSubmit={handleSubmit(onSubmit)} className="space-y-5" noValidate>
      <div>
        <h2 className="text-xl font-semibold text-neutral-900 dark:text-neutral-100">Sign in to your account</h2>
        <p className="text-sm text-neutral-500 dark:text-neutral-400 mt-1">Use your Ogami ERP credentials</p>
      </div>

      <div>
        <label className="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-1">Email</label>
        <input
          type="email"
          autoComplete="email"
          {...register('email')}
          className="w-full border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 rounded px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-neutral-400"
        />
        {errors.email && <p className="text-xs text-red-600 dark:text-red-400 mt-1">{errors.email.message}</p>}
      </div>

      <div>
        <label className="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-1">Password</label>
        <input
          type="password"
          autoComplete="current-password"
          {...register('password')}
          className="w-full border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 rounded px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-neutral-400"
        />
        {errors.password && <p className="text-xs text-red-600 mt-1">{errors.password.message}</p>}
      </div>

      <button
        type="submit"
        disabled={loading}
        className="w-full bg-neutral-900 dark:bg-neutral-100 text-white dark:text-neutral-900 rounded px-4 py-2 text-sm font-medium
                   hover:bg-neutral-800 dark:hover:bg-neutral-200 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
      >
        {loading ? 'Signing in…' : 'Sign in'}
      </button>
    </form>
  )
}
