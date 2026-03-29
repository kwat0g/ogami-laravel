import { useState, useMemo, useRef, useCallback, useEffect } from 'react'
import { Shield, Search, RefreshCw, ChevronLeft, ChevronRight, Filter, X, LogIn, LogOut, AlertTriangle, Plus, Pencil, Trash2, Eye } from 'lucide-react'
import { useAuditLogs, type AuditLog } from '@/hooks/useAdmin'
import { usePermission } from '@/hooks/usePermission'
import { PageHeader } from '@/components/ui/PageHeader'

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function formatDate(iso: string) {
  return new Date(iso).toLocaleString('en-PH', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
  })
}

function entityLabel(type: string): string {
  return type.split('\\').pop() ?? type
}

/** Convert camelCase / snake_case field name to human-readable. */
function fieldLabel(key: string): string {
  return key
    .replace(/_/g, ' ')
    .replace(/([a-z])([A-Z])/g, '$1 $2')
    .replace(/\b\w/g, c => c.toUpperCase())
}

const EVENT_CONFIG: Record<string, { color: string; icon: typeof Plus; label: string }> = {
  created: { color: 'bg-neutral-100 text-neutral-700 border-neutral-200', icon: Plus, label: 'Created' },
  updated: { color: 'bg-neutral-100 text-neutral-700 border-neutral-200', icon: Pencil, label: 'Updated' },
  deleted: { color: 'bg-neutral-100 text-neutral-700 border-neutral-200', icon: Trash2, label: 'Deleted' },
  retrieved: { color: 'bg-neutral-100 text-neutral-600 border-neutral-200', icon: Eye, label: 'Retrieved' },
  login: { color: 'bg-neutral-100 text-neutral-700 border-neutral-200', icon: LogIn, label: 'Login' },
  logout: { color: 'bg-neutral-100 text-neutral-700 border-neutral-200', icon: LogOut, label: 'Logout' },
  failed_login: { color: 'bg-neutral-100 text-neutral-700 border-neutral-200', icon: AlertTriangle, label: 'Failed Login' },
}

function EventBadge({ event }: { event: string }) {
  const cfg = EVENT_CONFIG[event.toLowerCase()] ?? {
    color: 'bg-neutral-100 text-neutral-700 border-neutral-200',
    icon: Shield,
    label: event,
  }
  const Icon = cfg.icon
  return (
    <span className={`inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-medium border ${cfg.color}`}>
      <Icon className="w-3 h-3" />
      {cfg.label}
    </span>
  )
}

// ---------------------------------------------------------------------------
// Diff viewer — shows old vs new values side by side
// ---------------------------------------------------------------------------

function ValueDiff({ log }: { log: AuditLog }) {
  const [open, setOpen] = useState(false)

  // Auth events show metadata in new_values
  const isAuthEvent = ['login', 'logout', 'failed_login'].includes(log.event)
  const hasChanges = log.old_values || log.new_values

  if (!hasChanges) return <span className="text-xs text-neutral-400 italic">—</span>

  let oldParsed: Record<string, unknown> = {}
  let newParsed: Record<string, unknown> = {}

  try {
    oldParsed = log.old_values ? JSON.parse(log.old_values) : {}
    newParsed = log.new_values ? JSON.parse(log.new_values) : {}
  } catch {
    // malformed JSON — show raw
  }

  const allKeys = Array.from(new Set([...Object.keys(oldParsed), ...Object.keys(newParsed)]))

  if (allKeys.length === 0) return <span className="text-xs text-neutral-400 italic">—</span>

  if (isAuthEvent) {
    return (
      <div>
        <button
          onClick={() => setOpen(v => !v)}
          className="text-xs text-neutral-700 "
        >
          {open ? 'Hide details' : 'View details'}
        </button>
        {open && (
          <div className="mt-2 bg-neutral-50 rounded border border-neutral-200 p-3">
            {allKeys.map(key => (
              <div key={key} className="text-xs">
                <span className="font-medium text-neutral-600">{fieldLabel(key)}:</span>{' '}
                <span className="text-neutral-800">{String(newParsed[key] ?? '')}</span>
              </div>
            ))}
          </div>
        )}
      </div>
    )
  }

  return (
    <div>
      <button
        onClick={() => setOpen(v => !v)}
        className="text-xs text-neutral-700 hover:underline"
      >
        {open ? 'Hide diff' : `View diff (${allKeys.length} field${allKeys.length !== 1 ? 's' : ''})`}
      </button>

      {open && (
        <div className="mt-2 bg-neutral-50 rounded border border-neutral-200 overflow-x-auto max-h-60">
          <table className="min-w-full text-xs">
            <thead className="bg-neutral-100 sticky top-0">
              <tr>
                <th className="px-3 py-1.5 text-left font-semibold text-neutral-600">Field</th>
                <th className="px-3 py-1.5 text-left font-semibold text-red-600">Before</th>
                <th className="px-3 py-1.5 text-left font-semibold text-green-600">After</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-neutral-200">
              {allKeys.map(key => (
                <tr key={key}>
                  <td className="px-3 py-1.5 font-medium text-neutral-700">{fieldLabel(key)}</td>
                  <td className="px-3 py-1.5 font-mono text-neutral-700 break-all">
                    {key in oldParsed ? String(oldParsed[key] ?? 'null') : <span className="text-neutral-400 italic">—</span>}
                  </td>
                  <td className="px-3 py-1.5 font-mono text-neutral-700 break-all">
                    {key in newParsed ? String(newParsed[key] ?? 'null') : <span className="text-neutral-400 italic">—</span>}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </div>
  )
}

// ---------------------------------------------------------------------------
// Page
// ---------------------------------------------------------------------------

export default function AuditLogsPage() {
  const canViewAuditLogs = usePermission('system.view_audit_log')

  const [search, setSearch] = useState('')
  const [debouncedSearch, setDebouncedSearch] = useState('')
  const [page, setPage] = useState(1)
  const [eventFilter, setEventFilter] = useState('')
  const [modelFilter, setModelFilter] = useState('')
  const [dateFrom, setDateFrom] = useState('')
  const [dateTo, setDateTo] = useState('')
  const [showFilters, setShowFilters] = useState(false)
  const perPage = 20

  // Proper ref-based debounce — survives re-renders
  const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null)

  const handleSearch = useCallback((value: string) => {
    setSearch(value)
    if (debounceRef.current) clearTimeout(debounceRef.current)
    debounceRef.current = setTimeout(() => {
      setDebouncedSearch(value)
      setPage(1)
    }, 500)
  }, [])

  // Cleanup on unmount
  useEffect(() => {
    return () => { if (debounceRef.current) clearTimeout(debounceRef.current) }
  }, [])

  const filters = useMemo(() => ({
    search: debouncedSearch || undefined,
    event: eventFilter || undefined,
    auditable_type: modelFilter || undefined,
    date_from: dateFrom || undefined,
    date_to: dateTo || undefined,
    per_page: perPage,
    page,
  }), [debouncedSearch, eventFilter, modelFilter, dateFrom, dateTo, perPage, page])

  const { data, isLoading, isFetching, _refetch } = useAuditLogs(filters)

  const logs = data?.data ?? []
  const meta = data?.meta

  const activeFilterCount = [eventFilter, modelFilter, dateFrom, dateTo].filter(Boolean).length

  const clearFilters = () => {
    setEventFilter('')
    setModelFilter('')
    setDateFrom('')
    setDateTo('')
    setPage(1)
  }

  if (!canViewAuditLogs) {
    return (
      <div className="flex items-center justify-center h-64">
        <div className="text-center">
          <Shield className="w-12 h-12 text-neutral-300 mx-auto mb-3" />
          <p className="text-neutral-500 font-medium">Access Denied</p>
          <p className="text-sm text-neutral-400 mt-1">You need <code>system.view_audit_log</code> permission.</p>
        </div>
      </div>
    )
  }

  return (
    <div className="space-y-6">
      <PageHeader title="Audit Logs" />

      {/* Page header actions */}
      <div className="flex items-center justify-end">
        <button
          onClick={() => void refetch()}
          disabled={isFetching}
          className="inline-flex items-center gap-1.5 px-3 py-2 text-sm border border-neutral-300 rounded hover:bg-neutral-50 disabled:opacity-50"
        >
          <RefreshCw className={`w-4 h-4 ${isFetching ? 'animate-spin' : ''}`} />
          Refresh
        </button>
      </div>

      {/* Search + Filter toggle */}
      <div className="flex items-center gap-3">
        <div className="relative flex-1 max-w-md">
          <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-neutral-400 pointer-events-none" />
          <input
            type="text"
            value={search}
            onChange={e => handleSearch(e.target.value)}
            placeholder="Search by event, model, user, or tag…"
            className="w-full pl-9 pr-4 py-2 text-sm border border-neutral-300 rounded focus:outline-none focus:ring-1 focus:ring-neutral-400"
          />
        </div>

        <button
          onClick={() => setShowFilters(v => !v)}
          className={`inline-flex items-center gap-1.5 px-3 py-2 text-sm border rounded transition-colors ${
            showFilters || activeFilterCount > 0
              ? 'bg-neutral-50 border-neutral-300 text-neutral-700'
              : 'border-neutral-300 text-neutral-700 hover:bg-neutral-50'
          }`}
        >
          <Filter className="w-4 h-4" />
          Filters
          {activeFilterCount > 0 && (
            <span className="bg-neutral-900 text-white text-xs rounded-full px-1.5 py-0.5 min-w-[20px] text-center">
              {activeFilterCount}
            </span>
          )}
        </button>

        {activeFilterCount > 0 && (
          <button
            onClick={clearFilters}
            className="inline-flex items-center gap-1 px-3 py-1.5 text-sm border border-neutral-200 rounded bg-white text-neutral-600 hover:bg-neutral-50 hover:border-neutral-300 hover:text-neutral-900"
          >
            <X className="w-4 h-4" />
            Clear
          </button>
        )}
      </div>

      {/* Filter panel */}
      {showFilters && (
        <div className="bg-neutral-50 rounded border border-neutral-200 p-4 grid grid-cols-1 md:grid-cols-4 gap-4">
          <div>
            <label className="block text-xs font-medium text-neutral-600 mb-1">Event Type</label>
            <select
              value={eventFilter}
              onChange={e => { setEventFilter(e.target.value); setPage(1) }}
              className="w-full text-sm border border-neutral-300 rounded px-3 py-2 focus:outline-none focus:ring-1 focus:ring-neutral-400"
            >
              <option value="">All Events</option>
              <option value="created">Created</option>
              <option value="updated">Updated</option>
              <option value="deleted">Deleted</option>
              <option value="login">Login</option>
              <option value="logout">Logout</option>
              <option value="failed_login">Failed Login</option>
            </select>
          </div>

          <div>
            <label className="block text-xs font-medium text-neutral-600 mb-1">Model Type</label>
            <select
              value={modelFilter}
              onChange={e => { setModelFilter(e.target.value); setPage(1) }}
              className="w-full text-sm border border-neutral-300 rounded px-3 py-2 focus:outline-none focus:ring-1 focus:ring-neutral-400"
            >
              <option value="">All Models</option>
              <option value="User">User</option>
              <option value="Employee">Employee</option>
              <option value="Department">Department</option>
              <option value="Position">Position</option>
              <option value="PayrollRun">Payroll Run</option>
              <option value="PayPeriod">Pay Period</option>
              <option value="Loan">Loan</option>
              <option value="LeaveRequest">Leave Request</option>
              <option value="LeaveBalance">Leave Balance</option>
              <option value="AttendanceLog">Attendance Log</option>
              <option value="OvertimeRequest">Overtime Request</option>
              <option value="JournalEntry">Journal Entry</option>
              <option value="ChartOfAccount">Chart of Account</option>
              <option value="FiscalPeriod">Fiscal Period</option>
              <option value="BankAccount">Bank Account</option>
              <option value="BankReconciliation">Bank Reconciliation</option>
              <option value="Vendor">Vendor</option>
              <option value="VendorInvoice">Vendor Invoice</option>
              <option value="VendorPayment">Vendor Payment</option>
              <option value="Customer">Customer</option>
              <option value="CustomerInvoice">Customer Invoice</option>
            </select>
          </div>

          <div>
            <label className="block text-xs font-medium text-neutral-600 mb-1">Date From</label>
            <input
              type="date"
              value={dateFrom}
              onChange={e => { setDateFrom(e.target.value); setPage(1) }}
              className="w-full text-sm border border-neutral-300 rounded px-3 py-2 focus:outline-none focus:ring-1 focus:ring-neutral-400"
            />
          </div>

          <div>
            <label className="block text-xs font-medium text-neutral-600 mb-1">Date To</label>
            <input
              type="date"
              value={dateTo}
              onChange={e => { setDateTo(e.target.value); setPage(1) }}
              className="w-full text-sm border border-neutral-300 rounded px-3 py-2 focus:outline-none focus:ring-1 focus:ring-neutral-400"
            />
          </div>
        </div>
      )}

      {/* Stats bar */}
      {meta && (
        <p className="text-sm text-neutral-500">
          Showing {logs.length > 0 ? ((page - 1) * perPage) + 1 : 0}–{Math.min(page * perPage, meta.total)} of{' '}
          <span className="font-semibold text-neutral-700">{meta.total.toLocaleString()}</span> log entries
        </p>
      )}

      {/* Table */}
      <div className="bg-white rounded border border-neutral-200 overflow-hidden">
        {isLoading ? (
          <div className="p-8 space-y-3">
            {Array.from({ length: 8 }).map((_, i) => (
              <div key={i} className="h-14 bg-neutral-100 rounded animate-pulse" />
            ))}
          </div>
        ) : logs.length === 0 ? (
          <div className="p-12 text-center">
            <Shield className="w-10 h-10 text-neutral-300 mx-auto mb-3" />
            <p className="text-neutral-500">No audit logs found.</p>
            {activeFilterCount > 0 && (
              <button
                onClick={clearFilters}
                className="mt-2 text-sm text-neutral-700 hover:underline"
              >
                Clear filters
              </button>
            )}
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="min-w-full divide-y divide-neutral-200">
              <thead className="bg-neutral-50">
                <tr>
                  {['Timestamp', 'User', 'Event', 'Model', 'ID', 'IP Address', 'Changes'].map(h => (
                    <th
                      key={h}
                      className="px-3 py-2.5 text-left text-xs font-semibold text-neutral-600"
                    >
                      {h}
                    </th>
                  ))}
                </tr>
              </thead>
              <tbody className="bg-white divide-y divide-neutral-100">
                {logs.map(log => (
                  <tr key={log.id} className="even:bg-neutral-100 hover:bg-neutral-50 transition-colors align-top">
                    <td className="px-3 py-2 text-sm text-neutral-600 whitespace-nowrap">
                      {formatDate(log.created_at)}
                    </td>
                    <td className="px-3 py-2">
                      <div className="text-sm font-medium text-neutral-900">{log.user_name}</div>
                      <div className="text-xs text-neutral-500">{log.user_email}</div>
                    </td>
                    <td className="px-3 py-2">
                      <EventBadge event={log.event} />
                      {log.tags && (
                        <span className="ml-1 inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-neutral-100 text-neutral-500 border border-neutral-200">
                          {log.tags}
                        </span>
                      )}
                    </td>
                    <td className="px-3 py-2 text-sm text-neutral-700 whitespace-nowrap">
                      {entityLabel(log.auditable_type)}
                    </td>
                    <td className="px-3 py-2 text-sm text-neutral-500">
                      #{log.auditable_id}
                    </td>
                    <td className="px-3 py-2 text-sm text-neutral-500 font-mono whitespace-nowrap">
                      {log.ip_address ?? <span className="text-neutral-300 italic">—</span>}
                    </td>
                    <td className="px-3 py-2 min-w-[200px]">
                      <ValueDiff log={log} />
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>

      {/* Pagination */}
      {meta && meta.last_page > 1 && (
        <div className="flex items-center justify-between">
          <p className="text-sm text-neutral-500">
            Page {meta.current_page} of {meta.last_page}
          </p>

          <div className="flex items-center gap-2">
            <button
              onClick={() => setPage(p => Math.max(1, p - 1))}
              disabled={page <= 1 || isFetching}
              className="p-2 rounded border border-neutral-300 hover:bg-neutral-50 disabled:opacity-40 disabled:cursor-not-allowed"
            >
              <ChevronLeft className="w-4 h-4" />
            </button>

            {/* Page numbers — show at most 7 */}
            {Array.from({ length: Math.min(7, meta.last_page) }, (_, i) => {
              const offset = Math.max(0, Math.min(meta.last_page - 7, page - 4))
              const p = i + 1 + offset
              return (
                <button
                  key={p}
                  onClick={() => setPage(p)}
                  disabled={isFetching}
                  className={`px-3 py-1.5 rounded text-sm font-medium transition-colors ${
                    p === page
                      ? 'bg-neutral-900 text-white'
                      : 'border border-neutral-300 text-neutral-700 hover:bg-neutral-50'
                  }`}
                >
                  {p}
                </button>
              )
            })}

            <button
              onClick={() => setPage(p => Math.min(meta.last_page, p + 1))}
              disabled={page >= meta.last_page || isFetching}
              className="p-2 rounded border border-neutral-300 hover:bg-neutral-50 disabled:opacity-40 disabled:cursor-not-allowed"
            >
              <ChevronRight className="w-4 h-4" />
            </button>
          </div>
        </div>
      )}
    </div>
  )
}
