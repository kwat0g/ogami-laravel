import { cn } from '@/lib/utils'

interface CardProps {
  children: React.ReactNode
  className?: string
  hoverable?: boolean
}

export function Card({ children, className, hoverable }: CardProps) {
  return (
    <div className={cn(
      'bg-white border border-neutral-200 rounded-lg shadow-sm overflow-hidden',
      hoverable && 'hover:border-neutral-300 hover:shadow-md transition-all duration-150',
      className
    )}>
      {children}
    </div>
  )
}

interface CardHeaderProps {
  children: React.ReactNode
  className?: string
  action?: React.ReactNode
}

export function CardHeader({ children, className, action }: CardHeaderProps) {
  return (
    <div className={cn(
      'px-5 py-4 border-b border-neutral-100 flex items-center justify-between',
      className
    )}>
      <div className="text-sm font-medium text-neutral-900">{children}</div>
      {action}
    </div>
  )
}

interface CardBodyProps {
  children: React.ReactNode
  className?: string
}

export function CardBody({ children, className }: CardBodyProps) {
  return (
    <div className={cn('p-5', className)}>
      {children}
    </div>
  )
}

interface CardFooterProps {
  children: React.ReactNode
  className?: string
}

export function CardFooter({ children, className }: CardFooterProps) {
  return (
    <div className={cn(
      'px-5 py-4 border-t border-neutral-100 bg-neutral-50/50',
      className
    )}>
      {children}
    </div>
  )
}

export default Card
