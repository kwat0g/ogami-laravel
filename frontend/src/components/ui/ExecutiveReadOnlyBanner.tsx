import { Eye } from 'lucide-react'
import { useIsExecutive } from '@/hooks/useIsExecutive'

/**
 * Renders a non-interactive informational banner when the user has the
 * `executive` role.  Returns null for all other roles.
 *
 * Place at the top of any page that should be read-only for executives:
 * ```tsx
 * export default function PayrollRunDetailPage() {
 *   return (
 *     <>
 *       <ExecutiveReadOnlyBanner />
 *       ...
 *     </>
 *   )
 * }
 * ```
 */
export default function ExecutiveReadOnlyBanner() {
  const isExecutive = useIsExecutive()

  if (!isExecutive) return null

  return (
    <div className="flex items-center gap-2 rounded-md border border-blue-200 bg-blue-50 px-4 py-2 mb-4 text-sm text-blue-700">
      <Eye className="h-4 w-4 flex-shrink-0" />
      <span>
        <span className="font-medium">Viewing as Executive</span> — this page is read-only.
        No changes can be made from the executive role.
      </span>
    </div>
  )
}
