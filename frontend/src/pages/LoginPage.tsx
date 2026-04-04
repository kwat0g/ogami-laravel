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
    // Fence off any stale in-flight requests from a previous auth state
    // before we begin a new login attempt.
    bumpAuthEpoch()
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
        queryClient.setQueryData(['auth', 'me'], result.user)
        setAuth(result.user)
        // Fence off any 401 handlers spawned during login transition.
        bumpAuthEpoch()
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
    <div className="space-y-8">
      <div className="text-center lg:text-left">
        <h2 className="text-3xl font-bold text-[#0f172a] tracking-tight">Welcome back</h2>
        <p className="text-sm text-[#475569] mt-2">Sign in to your Ogami ERP portal</p>
      </div>

      <form onSubmit={handleSubmit(onSubmit)} className="space-y-6" noValidate>
        <div className="space-y-1.5">
          <label className="block text-sm font-medium text-[#334155]">Email address</label>
          <input
            type="email"
            autoComplete="email"
            {...register('email')}
            className="w-full !bg-[#f8fafc] border !border-[#e2e8f0] !text-[#0f172a] rounded-lg px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-accent focus:border-transparent transition-all shadow-sm placeholder:!text-[#94a3b8]"
            placeholder="name@company.com"
          />
          {errors.email && <p className="text-xs text-danger mt-1 font-medium">{errors.email.message}</p>}
        </div>

        <div className="space-y-1.5">
          <label className="block text-sm font-medium text-[#334155]">Password</label>
          <input
            type="password"
            autoComplete="current-password"
            {...register('password')}
            className="w-full !bg-[#f8fafc] border !border-[#e2e8f0] !text-[#0f172a] rounded-lg px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-accent focus:border-transparent transition-all shadow-sm placeholder:!text-[#94a3b8]"
            placeholder="••••••••"
          />
          {errors.password && <p className="text-xs text-danger mt-1 font-medium">{errors.password.message}</p>}
        </div>

        <button
          type="submit"
          disabled={loading}
          className="w-full flex justify-center items-center bg-primary hover:bg-primary-700 text-white rounded-lg px-4 py-3 text-sm font-semibold transition-all shadow-subtle hover:shadow-elevated disabled:opacity-70 disabled:cursor-not-allowed"
        >
          {loading ? (
            <>
              <svg className="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
              </svg>
              Authenticating...
            </>
          ) : (
            'Sign in'
          )}
        </button>
      </form>
    </div>
  )
}
