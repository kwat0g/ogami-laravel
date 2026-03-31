/**
 * ExportPdfButton - Standardized PDF export for detail pages
 *
 * Uses the authenticated API client to fetch PDFs as blobs, avoiding
 * the "unauthenticated" error that occurs with plain <a href> links
 * (which don't send the X-Requested-With header needed by Sanctum SPA auth).
 *
 * Usage:
 *   <ExportPdfButton href={`/api/v1/procurement/purchase-orders/${ulid}/pdf`} />
 */
import { useState } from 'react'
import { Download, Loader2 } from 'lucide-react'
import api from '@/lib/api'

interface ExportPdfButtonProps {
  /** Full URL path to the PDF endpoint (e.g., /api/v1/ar/invoices/{ulid}/pdf) */
  href: string
  /** Button label (default: 'Export PDF') */
  label?: string
  /** Optional filename for the downloaded file */
  filename?: string
}

export function ExportPdfButton({ href, label = 'Export PDF', filename }: ExportPdfButtonProps) {
  const [loading, setLoading] = useState(false)

  async function handleDownload() {
    if (loading) return
    setLoading(true)

    try {
      // Strip /api/v1 prefix if present (api client adds baseURL)
      const url = href.replace(/^\/api\/v1/, '')

      const response = await api.get(url, {
        responseType: 'blob',
        headers: {
          Accept: 'application/pdf',
        },
      })

      // Create blob URL and trigger download
      const blob = new Blob([response.data], { type: 'application/pdf' })
      const blobUrl = window.URL.createObjectURL(blob)

      // Determine filename from Content-Disposition header or fallback
      let downloadFilename = filename || 'document.pdf'
      const disposition = response.headers['content-disposition']
      if (disposition) {
        const match = disposition.match(/filename[^;=\n]*=((['"]).*?\2|[^;\n]*)/)
        if (match?.[1]) {
          downloadFilename = match[1].replace(/['"]/g, '')
        }
      }

      const link = document.createElement('a')
      link.href = blobUrl
      link.download = downloadFilename
      document.body.appendChild(link)
      link.click()
      document.body.removeChild(link)
      window.URL.revokeObjectURL(blobUrl)
    } catch (error) {
      // If blob download fails, fall back to opening in new tab
      // This handles cases where the response is HTML (rendered PDF view)
      window.open(href, '_blank')
    } finally {
      setLoading(false)
    }
  }

  return (
    <button
      onClick={handleDownload}
      disabled={loading}
      className="inline-flex items-center gap-1.5 bg-white text-neutral-700 border border-neutral-300 hover:bg-neutral-50 text-sm font-medium px-4 py-2 rounded-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
    >
      {loading ? (
        <Loader2 className="w-4 h-4 animate-spin" />
      ) : (
        <Download className="w-4 h-4" />
      )}
      {loading ? 'Downloading...' : label}
    </button>
  )
}

export default ExportPdfButton
