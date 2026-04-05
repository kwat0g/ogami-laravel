import { Navigate, Outlet, Link } from 'react-router-dom'
import { useAuth } from '@/hooks/useAuth'
import { getLandingPath } from '@/lib/roleLanding'
import { ArrowLeft } from 'lucide-react'

/**
 * Wraps unauthenticated routes (login).
 * Redirects to dashboard if user is already authenticated.
 */
export default function AuthLayout() {
  const { isAuthenticated, isLoading, user } = useAuth()

  // Only redirect once we know for certain the user is authenticated.
  // Do NOT show a skeleton here — it unmounts <Outlet /> which wipes
  // the login form's react-hook-form state (typed email/password lost).
  if (!isLoading && isAuthenticated) {
    return <Navigate to={getLandingPath(user)} replace />
  }

  return (
    <div className="min-h-screen flex bg-[#ffffff] text-[#0f172a] relative">
      {/* Back to Home floating button */}
            <Link 
        to="/" 
        className="absolute top-6 left-6 lg:left-[unset] lg:right-6 z-50 inline-flex items-center text-sm font-medium text-[#475569] hover:text-accent transition-colors bg-[#ffffff]/80 backdrop-blur-md px-4 py-2 rounded-full shadow-subtle border border-[#f1f5f9]"
      >
        <ArrowLeft className="w-4 h-4 mr-2" />
        Back to Home
      </Link>

      {/* Left decorative panel */}
      <div className="hidden lg:flex lg:w-1/2 relative flex-col justify-between bg-primary overflow-hidden">
        {/* Self-hosted decorative background for production-safe rendering */}
        <div className="absolute inset-0 bg-[radial-gradient(circle_at_top_left,_rgba(255,255,255,0.18),_transparent_32%),linear-gradient(180deg,_rgba(15,23,42,0.12)_0%,_rgba(15,23,42,0.48)_100%)]"></div>
        <div className="absolute -top-20 -left-24 h-72 w-72 rounded-full bg-white/10 blur-3xl"></div>
        <div className="absolute bottom-0 right-0 h-80 w-80 translate-x-16 translate-y-16 rounded-full bg-primary-900/40 blur-3xl"></div>
        <div className="absolute inset-0 bg-[linear-gradient(135deg,rgba(255,255,255,0.08)_0%,transparent_38%,transparent_62%,rgba(255,255,255,0.06)_100%)]"></div>
        <div className="absolute inset-0 bg-gradient-to-t from-primary-900 via-primary-900/60 to-transparent"></div>

        {/* Brand Header */}
        <div className="relative z-10 p-12 lg:p-16 flex items-center space-x-3">
          <img src="/build/logo.svg" alt="Ogami ERP" className="h-10 w-auto brightness-0 invert opacity-90" />
        </div>

        {/* Hero Quote */}
        <div className="relative z-10 p-12 lg:p-16 mt-auto">
          <blockquote className="space-y-6 text-white">
            <p className="text-2xl md:text-3xl font-semibold leading-snug">
              "Powering precision manufacturing and integrated digital operations."
            </p>
            <footer className="text-sm font-medium tracking-wide text-[#94a3b8] uppercase">
              Ogami Manufacturing Philippines Corp.
            </footer>
          </blockquote>
        </div>
      </div>

      {/* Right Login forms container */}
      <div className="w-full lg:w-1/2 flex items-center justify-center p-8 sm:p-12 lg:p-16">
        <div className="w-full max-w-sm">
          {/* Mobile Header (Hidden on generic desktop) */}
          <div className="lg:hidden flex items-center justify-center space-x-3 mb-12">
            <img src="/build/logo.svg" alt="Ogami ERP" className="h-8 w-auto" />
          </div>

          <Outlet />
        </div>
      </div>
    </div>
  )
}
