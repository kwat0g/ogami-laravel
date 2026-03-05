import {
  useReactTable,
  getCoreRowModel,
  getSortedRowModel,
  flexRender,
  type ColumnDef,
  type SortingState,
} from '@tanstack/react-table'
import { useState } from 'react'
import { ChevronUp, ChevronDown, ChevronsUpDown } from 'lucide-react'
import SkeletonLoader from './SkeletonLoader'

// ── Pagination meta ───────────────────────────────────────────────────────────

export interface PaginationMeta {
  current_page: number
  last_page:    number
  per_page:     number
  total:        number
}

// ── Props ─────────────────────────────────────────────────────────────────────

interface DataTableProps<TData> {
  /** TanStack Table column definitions. */
  columns: ColumnDef<TData, unknown>[]
  /** Row data — an empty array is fine (renders EmptyState). */
  data: TData[]
  /** Shows skeleton rows while true. */
  isLoading?: boolean
  /** Pagination metadata from the API. Pass undefined to hide pagination. */
  pagination?: PaginationMeta
  /**
   * Called when the user clicks a page button.
   * Must be provided when `pagination` is set.
   */
  onPageChange?: (page: number) => void
  /** Optional message shown in the empty-state row. */
  emptyMessage?: string
}

// ── Component ─────────────────────────────────────────────────────────────────

/**
 * Thin TanStack Table v8 wrapper with:
 *  - Client-side sorting (click a header to sort)
 *  - Server-side pagination via `pagination` + `onPageChange` props
 *  - Loading skeleton
 *  - Empty-state row
 *
 * Usage:
 * ```tsx
 * const cols: ColumnDef<Employee>[] = [
 *   { accessorKey: 'full_name', header: 'Name' },
 *   { accessorKey: 'employment_status', header: 'Status' },
 * ]
 * <DataTable columns={cols} data={employees} isLoading={isLoading}
 *   pagination={meta} onPageChange={setPage} />
 * ```
 */
export default function DataTable<TData>({
  columns,
  data,
  isLoading,
  pagination,
  onPageChange,
  emptyMessage = 'No records found.',
}: DataTableProps<TData>) {
  const [sorting, setSorting] = useState<SortingState>([])

  const table = useReactTable({
    data,
    columns,
    state: { sorting },
    onSortingChange: setSorting,
    getCoreRowModel: getCoreRowModel(),
    getSortedRowModel: getSortedRowModel(),
    manualPagination: true,
    pageCount: pagination?.last_page ?? -1,
  })

  // ── Loading ───────────────────────────────────────────────────────────────
  if (isLoading) {
    return <SkeletonLoader rows={8} />
  }

  const colCount = columns.length

  return (
    <div className="overflow-x-auto rounded-lg border border-gray-200">
      <table className="min-w-full divide-y divide-gray-200 text-sm">
        {/* Head */}
        <thead className="bg-gray-50">
          {table.getHeaderGroups().map((hg) => (
            <tr key={hg.id}>
              {hg.headers.map((header) => {
                const canSort = header.column.getCanSort()
                const sorted  = header.column.getIsSorted()

                return (
                  <th
                    key={header.id}
                    className={[
                      'px-3 py-2.5 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap',
                      canSort ? 'cursor-pointer select-none hover:text-gray-700' : '',
                    ].join(' ')}
                    onClick={canSort ? header.column.getToggleSortingHandler() : undefined}
                  >
                    <span className="inline-flex items-center gap-1">
                      {flexRender(header.column.columnDef.header, header.getContext())}
                      {canSort && (
                        <span className="text-gray-400">
                          {sorted === 'asc'  ? <ChevronUp   className="h-3 w-3" /> :
                           sorted === 'desc' ? <ChevronDown className="h-3 w-3" /> :
                                              <ChevronsUpDown className="h-3 w-3" />}
                        </span>
                      )}
                    </span>
                  </th>
                )
              })}
            </tr>
          ))}
        </thead>

        {/* Body */}
        <tbody className="bg-white divide-y divide-gray-100">
          {table.getRowModel().rows.length === 0 ? (
            <tr>
              <td colSpan={colCount} className="px-4 py-10 text-center text-sm text-gray-400">
                {emptyMessage}
              </td>
            </tr>
          ) : (
            table.getRowModel().rows.map((row) => (
              <tr key={row.id} className="even:bg-slate-50 hover:bg-blue-50/60 transition-colors">
                {row.getVisibleCells().map((cell) => (
                  <td key={cell.id} className="px-3 py-2 text-gray-700 whitespace-nowrap">
                    {flexRender(cell.column.columnDef.cell, cell.getContext())}
                  </td>
                ))}
              </tr>
            ))
          )}
        </tbody>
      </table>

      {/* Pagination */}
      {pagination && onPageChange && pagination.last_page > 1 && (
        <div className="flex items-center justify-between px-4 py-3 border-t border-gray-200 bg-gray-50">
          <p className="text-xs text-gray-500">
            Page <span className="font-medium">{pagination.current_page}</span> of{' '}
            <span className="font-medium">{pagination.last_page}</span>
            {' — '}
            <span className="font-medium">{pagination.total}</span> total
          </p>
          <div className="flex gap-1">
            <PaginationButton
              label="← Prev"
              disabled={pagination.current_page <= 1}
              onClick={() => onPageChange(pagination.current_page - 1)}
            />
            <PaginationButton
              label="Next →"
              disabled={pagination.current_page >= pagination.last_page}
              onClick={() => onPageChange(pagination.current_page + 1)}
            />
          </div>
        </div>
      )}
    </div>
  )
}

// ── Internal: pagination button ───────────────────────────────────────────────

function PaginationButton({
  label,
  onClick,
  disabled,
}: {
  label: string
  onClick: () => void
  disabled: boolean
}) {
  return (
    <button
      type="button"
      disabled={disabled}
      onClick={onClick}
      className="px-3 py-1 text-xs rounded border border-gray-300 bg-white text-gray-600 hover:bg-gray-100 disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
    >
      {label}
    </button>
  )
}
