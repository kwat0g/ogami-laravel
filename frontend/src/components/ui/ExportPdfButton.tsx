/**
 * ExportPdfButton - Standardized PDF export link for detail pages
 *
 * Renders a consistent secondary-styled anchor tag that opens a PDF endpoint
 * in a new browser tab. Use this instead of inline <a> tags for PDF exports.
 *
 * Usage:
 *   <ExportPdfButton href={`/api/v1/procurement/purchase-orders/${ulid}/pdf`} />
 */
import { Download } from 'lucide-react'

interface ExportPdfButtonProps {
  /** Full URL path to the PDF endpoint (e.g., /api/v1/ar/invoices/{ulid}/pdf) */
  href: string
  /** Button label (default: 'Export PDF') */
  label?: string
}

export function ExportPdfButton({ href, label = 'Export PDF' }: ExportPdfButtonProps) {
  return (
    <a
      href={href}
      target="_blank"
      rel="noopener noreferrer"
      className="inline-flex items-center gap-1.5 bg-white text-neutral-700 border border-neutral-300 hover:bg-neutral-50 text-sm font-medium px-4 py-2 rounded transition-colors"
    >
      <Download className="w-4 h-4" />
      {label}
    </a>
  )
}

export default ExportPdfButton
