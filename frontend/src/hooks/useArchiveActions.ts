import { useMutation, useQueryClient } from '@tanstack/react-query'
import api from '@/lib/api'

/**
 * Shared hooks for archive (soft-delete), restore, and permanent-delete actions.
 *
 * Usage:
 *   const archiveMut = useArchiveRecord('vendors', vendor.id, ['vendors'])
 *   const restoreMut = useRestoreRecord('vendors', record.id, ['vendors'])
 *   const forceDeleteMut = useForceDeleteRecord('vendors', record.id, ['vendors'])
 *
 * All mutations invalidate both active and archived query keys automatically.
 */

/**
 * Archive (soft-delete) a record. Calls `DELETE /{prefix}/{id}`.
 */
export function useArchiveRecord(
  prefix: string,
  id: number | string,
  queryKeyBase: string[],
) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: () => api.delete(`/${prefix}/${id}`),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: [...queryKeyBase, 'active'] })
      qc.invalidateQueries({ queryKey: [...queryKeyBase, 'archived'] })
      // Also invalidate legacy query keys that don't use active/archived suffix
      qc.invalidateQueries({ queryKey: queryKeyBase })
    },
  })
}

/**
 * Restore a soft-deleted record. Calls `POST /{prefix}/{id}/restore`.
 */
export function useRestoreRecord(
  prefix: string,
  id: number | string,
  queryKeyBase: string[],
) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: () => api.post(`/${prefix}/${id}/restore`),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: [...queryKeyBase, 'active'] })
      qc.invalidateQueries({ queryKey: [...queryKeyBase, 'archived'] })
      qc.invalidateQueries({ queryKey: queryKeyBase })
    },
  })
}

/**
 * Permanently delete a soft-deleted record. Calls `DELETE /{prefix}/{id}/force`.
 * Only available to superadmin.
 */
export function useForceDeleteRecord(
  prefix: string,
  id: number | string,
  queryKeyBase: string[],
) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: () => api.delete(`/${prefix}/${id}/force`),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: [...queryKeyBase, 'active'] })
      qc.invalidateQueries({ queryKey: [...queryKeyBase, 'archived'] })
      qc.invalidateQueries({ queryKey: queryKeyBase })
    },
  })
}
