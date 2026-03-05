import * as React from 'react'
import { X } from 'lucide-react'
import { cn } from '@/lib/utils'
import { motion, AnimatePresence } from 'framer-motion'

interface SheetContextValue {
  open: boolean
  onOpenChange: (open: boolean) => void
}

const SheetContext = React.createContext<SheetContextValue>({
  open: false,
  onOpenChange: () => {},
})

const useSheet = () => React.useContext(SheetContext)

interface SheetProps {
  children: React.ReactNode
  open?: boolean
  onOpenChange?: (open: boolean) => void
}

const Sheet = ({ children, open, onOpenChange }: SheetProps) => {
  const [internalOpen, setInternalOpen] = React.useState(false)
  const isOpen = open !== undefined ? open : internalOpen
  const handleOpenChange = onOpenChange || setInternalOpen

  // Close on escape key
  React.useEffect(() => {
    const handleEscape = (e: KeyboardEvent) => {
      if (e.key === 'Escape' && isOpen) {
        handleOpenChange(false)
      }
    }
    document.addEventListener('keydown', handleEscape)
    return () => document.removeEventListener('keydown', handleEscape)
  }, [isOpen, handleOpenChange])

  // Prevent body scroll when open
  React.useEffect(() => {
    if (isOpen) {
      document.body.style.overflow = 'hidden'
    } else {
      document.body.style.overflow = ''
    }
    return () => {
      document.body.style.overflow = ''
    }
  }, [isOpen])

  return (
    <SheetContext.Provider value={{ open: isOpen, onOpenChange: handleOpenChange }}>
      {children}
    </SheetContext.Provider>
  )
}

interface SheetTriggerProps {
  children: React.ReactNode
  asChild?: boolean
}

const SheetTrigger = React.forwardRef<HTMLButtonElement, SheetTriggerProps & React.ButtonHTMLAttributes<HTMLButtonElement>>(
  ({ children, asChild, ...props }, ref) => {
    const { onOpenChange } = useSheet()

    if (asChild) {
      return React.cloneElement(children as React.ReactElement, {
        onClick: () => onOpenChange(true),
        ...props,
      })
    }

    return (
      <button ref={ref} onClick={() => onOpenChange(true)} {...props}>
        {children}
      </button>
    )
  }
)
SheetTrigger.displayName = 'SheetTrigger'

interface SheetContentProps extends React.HTMLAttributes<HTMLDivElement> {
  side?: 'left' | 'right' | 'top' | 'bottom'
}

const SheetContent = React.forwardRef<HTMLDivElement, SheetContentProps>(
  ({ className, children, side = 'right', ...props }, ref) => {
    const { open, onOpenChange } = useSheet()

    const sideVariants = {
      left: {
        initial: { x: '-100%' },
        animate: { x: 0 },
        exit: { x: '-100%' },
      },
      right: {
        initial: { x: '100%' },
        animate: { x: 0 },
        exit: { x: '100%' },
      },
      top: {
        initial: { y: '-100%' },
        animate: { y: 0 },
        exit: { y: '-100%' },
      },
      bottom: {
        initial: { y: '100%' },
        animate: { y: 0 },
        exit: { y: '100%' },
      },
    }

    const sideClasses = {
      left: 'inset-y-0 left-0 h-full w-3/4 max-w-sm border-r',
      right: 'inset-y-0 right-0 h-full w-3/4 max-w-sm border-l',
      top: 'inset-x-0 top-0 w-full h-auto max-h-[50vh] border-b',
      bottom: 'inset-x-0 bottom-0 w-full h-auto max-h-[50vh] border-t',
    }

    return (
      <AnimatePresence>
        {open && (
          <div className="fixed inset-0 z-50">
            {/* Backdrop */}
            <motion.div
              initial={{ opacity: 0 }}
              animate={{ opacity: 1 }}
              exit={{ opacity: 0 }}
              transition={{ duration: 0.2 }}
              className="absolute inset-0 bg-black/50 backdrop-blur-sm"
              onClick={() => onOpenChange(false)}
            />

            {/* Content */}
            <motion.div
              ref={ref}
              initial={sideVariants[side].initial}
              animate={sideVariants[side].animate}
              exit={sideVariants[side].exit}
              transition={{ type: 'spring', damping: 25, stiffness: 200 }}
              className={cn(
                'fixed bg-white shadow-2xl',
                sideClasses[side],
                className
              )}
              {...props}
            >
              {children}
            </motion.div>
          </div>
        )}
      </AnimatePresence>
    )
  }
)
SheetContent.displayName = 'SheetContent'

const SheetHeader = React.forwardRef<
  HTMLDivElement,
  React.HTMLAttributes<HTMLDivElement>
>(({ className, ...props }, ref) => (
  <div
    ref={ref}
    className={cn(
      'flex flex-col space-y-1.5 p-6 border-b border-gray-100',
      className
    )}
    {...props}
  />
))
SheetHeader.displayName = 'SheetHeader'

const SheetFooter = React.forwardRef<
  HTMLDivElement,
  React.HTMLAttributes<HTMLDivElement>
>(({ className, ...props }, ref) => (
  <div
    ref={ref}
    className={cn(
      'flex flex-col-reverse sm:flex-row sm:justify-end sm:gap-2 p-6 border-t border-gray-100',
      className
    )}
    {...props}
  />
))
SheetFooter.displayName = 'SheetFooter'

const SheetTitle = React.forwardRef<
  HTMLHeadingElement,
  React.HTMLAttributes<HTMLHeadingElement>
>(({ className, ...props }, ref) => (
  <h2
    ref={ref}
    className={cn(
      'text-lg font-semibold text-gray-900',
      className
    )}
    {...props}
  />
))
SheetTitle.displayName = 'SheetTitle'

const SheetDescription = React.forwardRef<
  HTMLParagraphElement,
  React.HTMLAttributes<HTMLParagraphElement>
>(({ className, ...props }, ref) => (
  <p
    ref={ref}
    className={cn('text-sm text-gray-500', className)}
    {...props}
  />
))
SheetDescription.displayName = 'SheetDescription'

const SheetClose = React.forwardRef<
  HTMLButtonElement,
  React.ButtonHTMLAttributes<HTMLButtonElement>
>(({ className, ...props }, ref) => {
  const { onOpenChange } = useSheet()
  return (
    <button
      ref={ref}
      onClick={() => onOpenChange(false)}
      className={cn(
        'absolute right-4 top-4 p-2 rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition-colors',
        className
      )}
      {...props}
    >
      <X className="h-4 w-4" />
    </button>
  )
})
SheetClose.displayName = 'SheetClose'

export {
  Sheet,
  SheetTrigger,
  SheetContent,
  SheetHeader,
  SheetFooter,
  SheetTitle,
  SheetDescription,
  SheetClose,
  // eslint-disable-next-line react-refresh/only-export-components
  useSheet,
}
