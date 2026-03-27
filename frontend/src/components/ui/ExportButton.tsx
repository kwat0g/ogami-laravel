/**
 * ExportButton - Reusable CSV/Excel export for list pages
 *
 * Accepts data rows and column definitions, generates a CSV file,
 * and triggers a browser download. No server-side dependency.
 *
 * Usage:
 *   <ExportButton
 *     data={employees}
 *     columns={[
 *       { key: 'employee_code', label: 'Code' },
 *       { key: 'full_name', label: 'Name' },
 *       { key: 'department.name', label: 'Department' },
 *     ]}
 *     filename="employees"
 *   />
 */
import { useState } from 'react'
import { Download, Loader2 } from 'lucide-react'

export interface ExportColumn<T = Record<string, unknown>> {
  /** Dot-notation path to the value (e.g., 'department.name') */
  key: string
  /** Column header label */
  label: string
  /** Optional value formatter */
  format?: (value: unknown, row: T) => string
}

interface ExportButtonProps<T = Record<string, unknown>> {
  /** Data rows to export */
  data: T[]
  /** Column definitions */
  columns: ExportColumn<T>[]
  /** Filename without extension (default: 'export') */
  filename?: string
  /** Button label (default: 'Export CSV') */
  label?: string
  /** Disabled state */
  disabled?: boolean
  /** Optional async data fetcher -- if provided, fetches all data before export */
  fetchAllData?: () => Promise<T[]>
}

function getNestedValue(obj: unknown, path: string): unknown {
  return path.split('.').reduce<unknown>((acc, key) => {
    if (acc && typeof acc === 'object' && key in (acc as Record<string, unknown>)) {
      return (acc as Record<string, unknown>)[key]
    }
    return undefined
  }, obj)
}

function escapeCSV(value: unknown): string {
  if (value === null || value === undefined) return ''
  const str = String(value)
  // Escape if contains comma, quote, or newline
  if (str.includes(',') || str.includes('"') || str.includes('\n') || str.includes('\r')) {
    return `"${str.replace(/"/g, '""')}"`
  }
  return str
}

function generateCSV<T>(data: T[], columns: ExportColumn<T>[]): string {
  const header = columns.map(c => escapeCSV(c.label)).join(',')

  const rows = data.map(row =>
    columns.map(col => {
      const rawValue = getNestedValue(row, col.key)
      const formatted = col.format ? col.format(rawValue, row) : rawValue
      return escapeCSV(formatted)
    }).join(',')
  )

  return [header, ...rows].join('\n')
}

function downloadFile(content: string, filename: string) {
  const blob = new Blob(['\uFEFF' + content], { type: 'text/csv;charset=utf-8;' }) // BOM for Excel
  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url
  link.download = `${filename}_${new Date().toISOString().split('T')[0]}.csv`
  document.body.appendChild(link)
  link.click()
  document.body.removeChild(link)
  URL.revokeObjectURL(url)
}

export function ExportButton<T = Record<string, unknown>>({
  data,
  columns,
  filename = 'export',
  label = 'Export CSV',
  disabled = false,
  fetchAllData,
}: ExportButtonProps<T>): JSX.Element {
  const [exporting, setExporting] = useState(false)

  const handleExport = async () => {
    setExporting(true)
    try {
      const exportData = fetchAllData ? await fetchAllData() : data
      const csv = generateCSV(exportData, columns)
      downloadFile(csv, filename)
    } catch {
      // Silently fail -- toast could be added here
    } finally {
      setExporting(false)
    }
  }

  const isEmpty = !fetchAllData && data.length === 0

  return (
    <button
      onClick={handleExport}
      disabled={disabled || exporting || isEmpty}
      className="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium border border-neutral-200 rounded-lg bg-white text-neutral-600 hover:bg-neutral-50 hover:border-neutral-300 disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
      title={isEmpty ? 'No data to export' : `Export ${data.length} rows as CSV`}
    >
      {exporting ? (
        <Loader2 className="h-3.5 w-3.5 animate-spin" />
      ) : (
        <Download className="h-3.5 w-3.5" />
      )}
      {label}
    </button>
  )
}

export default ExportButton
