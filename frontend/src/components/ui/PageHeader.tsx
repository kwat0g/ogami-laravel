import { useNavigate } from 'react-router-dom'
import { ArrowLeft } from 'lucide-react'
import { cn } from '@/lib/utils'

interface PageHeaderProps {
  title: string
  subtitle?: string
  backTo?: string
  icon?: React.ReactNode
  status?: React.ReactNode
  actions?: React.ReactNode
  className?: string
}

export function PageHeader({
  title,
  subtitle,
  backTo,
  icon,
  status,
  actions,
  className,
}: PageHeaderProps) {
  const navigate = useNavigate()

  return (
    <div className={cn('flex items-center justify-between gap-4 mb-6', className)}>
      <div className="flex items-center gap-3">
        {backTo && (
          <button
            type="button"
            onClick={() => navigate(backTo)}
            className="p-2 rounded-md border border-neutral-200 bg-white text-neutral-500 hover:bg-neutral-50 hover:border-neutral-300 hover:text-neutral-700 transition-colors"
            aria-label="Go back"
          >
            <ArrowLeft className="w-5 h-5" />
          </button>
        )}
        
        {icon && (
          <div className="w-10 h-10 rounded-lg bg-neutral-100 flex items-center justify-center">
            {icon}
          </div>
        )}
        
        <div>
          <div className="flex items-center gap-3">
            <h1 className="text-lg font-semibold text-neutral-900">{title}</h1>
            {status}
          </div>
          {subtitle && (
            <p className="text-sm text-neutral-500 mt-0.5">{subtitle}</p>
          )}
        </div>
      </div>
      
      {actions && (
        <div className="flex items-center gap-3">
          {actions}
        </div>
      )}
    </div>
  )
}

export default PageHeader
