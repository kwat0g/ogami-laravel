import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import api from '@/lib/api'

export interface SystemSetting {
  id: number
  key: string
  label: string
  value: string | number | boolean | object
  data_type: 'string' | 'integer' | 'decimal' | 'boolean' | 'json'
  group: string
  is_sensitive: boolean
  editable_by_role: string
  updated_by: number | null
  updated_at: string
  created_at: string
  input_type: string
  validation_rules: {
    type: string
    min?: number
    max?: number
    step?: number
  }
}

export type SettingsByGroup = Record<string, SystemSetting[]>

// ── Get all settings ─────────────────────────────────────────────────────────

export function useSystemSettings() {
  return useQuery({
    queryKey: ['settings', 'all'],
    queryFn: async () => {
      const res = await api.get<{ data: SettingsByGroup }>('/admin/settings')
      return res.data.data
    },
    staleTime: 5 * 60 * 1000, // 5 minutes
  })
}

// ── Get settings by group ────────────────────────────────────────────────────

export function useSettingsByGroup(group: string) {
  return useQuery({
    queryKey: ['settings', 'group', group],
    queryFn: async () => {
      const res = await api.get<{ data: SystemSetting[] }>(`/admin/settings/group/${group}`)
      return res.data.data
    },
    enabled: !!group,
    staleTime: 5 * 60 * 1000,
  })
}

// ── Get single setting ───────────────────────────────────────────────────────

export function useSetting(key: string) {
  return useQuery({
    queryKey: ['settings', 'key', key],
    queryFn: async () => {
      const res = await api.get<{ data: SystemSetting }>(`/admin/settings/key/${key}`)
      return res.data.data
    },
    enabled: !!key,
  })
}

// ── Update single setting ────────────────────────────────────────────────────

export function useUpdateSetting() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async ({ key, value }: { key: string; value: unknown }) => {
      const res = await api.patch<{ data: SystemSetting; message: string }>(
        `/admin/settings/${key}`,
        { value }
      )
      return res.data
    },
    onSuccess: (_, variables) => {
      // Invalidate specific setting
      queryClient.invalidateQueries({ queryKey: ['settings', 'key', variables.key] })
      // Invalidate all settings queries
      queryClient.invalidateQueries({ queryKey: ['settings'] })
    },
  })
}

// ── Bulk update settings ─────────────────────────────────────────────────────

export function useBulkUpdateSettings() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async (settings: { key: string; value: unknown }[]) => {
      const res = await api.post<{ 
        data: { updated: string[]; errors: Array<{ key: string; error: string }> }
        message: string 
        success: boolean
      }>(`/admin/settings/bulk-update`, { settings })
      return res.data
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['settings'] })
    },
  })
}
