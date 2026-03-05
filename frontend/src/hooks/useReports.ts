import { useState, useCallback } from 'react'
import { useQuery } from '@tanstack/react-query'
import api from '@/lib/api'
import type {
  GLReport,
  GLFilters,
  TrialBalance,
  BalanceSheet,
  BalanceSheetFilters,
  IncomeStatement,
  CashFlowStatement,
  PeriodFilters,
} from '@/types/reports'

// ---------------------------------------------------------------------------
// Shared download trigger
// ---------------------------------------------------------------------------

function triggerDownload(blob: Blob, filename: string) {
  const url = URL.createObjectURL(blob)
  const a   = document.createElement('a')
  a.href     = url
  a.download = filename
  a.click()
  URL.revokeObjectURL(url)
}

// ---------------------------------------------------------------------------
// BIR Form 1601-C — Monthly PDF
// ---------------------------------------------------------------------------

export function useDownloadForm1601c(year: number, month: number) {
  const [isLoading, setIsLoading] = useState(false)

  const download = useCallback(async () => {
    setIsLoading(true)
    try {
      const res = await api.get('/reports/bir/1601c', {
        params: { year, month },
        responseType: 'blob',
      })
      triggerDownload(
        res.data as Blob,
        `bir-1601c-${year}-${String(month).padStart(2, '0')}.pdf`,
      )
    } finally {
      setIsLoading(false)
    }
  }, [year, month])

  return { download, isLoading }
}

// ---------------------------------------------------------------------------
// BIR Form 2316 — Annual per-employee PDF
// ---------------------------------------------------------------------------

export function useDownloadForm2316(year: number, employeeId: number | null) {
  const [isLoading, setIsLoading] = useState(false)

  const download = useCallback(async () => {
    if (employeeId === null) return
    setIsLoading(true)
    try {
      const res = await api.get('/reports/bir/2316', {
        params: { year, employee_id: employeeId },
        responseType: 'blob',
      })
      triggerDownload(
        res.data as Blob,
        `bir-2316-${year}-emp${employeeId}.pdf`,
      )
    } finally {
      setIsLoading(false)
    }
  }, [year, employeeId])

  return { download, isLoading }
}

// ---------------------------------------------------------------------------
// BIR Alphalist — Annual Excel
// ---------------------------------------------------------------------------

export function useDownloadAlphalist(year: number) {
  const [isLoading, setIsLoading] = useState(false)

  const download = useCallback(async () => {
    setIsLoading(true)
    try {
      const res = await api.get('/reports/bir/alphalist', {
        params: { year },
        responseType: 'blob',
      })
      triggerDownload(res.data as Blob, `bir-alphalist-${year}.xlsx`)
    } finally {
      setIsLoading(false)
    }
  }, [year])

  return { download, isLoading }
}

// ---------------------------------------------------------------------------
// SSS SBR2 — Monthly Excel
// ---------------------------------------------------------------------------

export function useDownloadSssSbr2(year: number, month: number) {
  const [isLoading, setIsLoading] = useState(false)

  const download = useCallback(async () => {
    setIsLoading(true)
    try {
      const res = await api.get('/reports/sss/sbr2', {
        params: { year, month },
        responseType: 'blob',
      })
      triggerDownload(
        res.data as Blob,
        `sss-sbr2-${year}-${String(month).padStart(2, '0')}.xlsx`,
      )
    } finally {
      setIsLoading(false)
    }
  }, [year, month])

  return { download, isLoading }
}

// ---------------------------------------------------------------------------
// PhilHealth RF-1 — Monthly Excel
// ---------------------------------------------------------------------------

export function useDownloadPhilHealthRf1(year: number, month: number) {
  const [isLoading, setIsLoading] = useState(false)

  const download = useCallback(async () => {
    setIsLoading(true)
    try {
      const res = await api.get('/reports/philhealth/rf1', {
        params: { year, month },
        responseType: 'blob',
      })
      triggerDownload(
        res.data as Blob,
        `philhealth-rf1-${year}-${String(month).padStart(2, '0')}.xlsx`,
      )
    } finally {
      setIsLoading(false)
    }
  }, [year, month])

  return { download, isLoading }
}

// ===========================================================================
// Financial Statement Hooks (GL-001 to GL-005)
// ===========================================================================

// ---------------------------------------------------------------------------
// GL-001 — General Ledger
// ---------------------------------------------------------------------------

export function useGeneralLedger(filters: GLFilters | null) {
  return useQuery({
    queryKey: ['reports', 'gl', filters],
    queryFn: async () => {
      const res = await api.get<GLReport>('/accounting/reports/gl', { params: filters! })
      return res.data
    },
    enabled:
      filters !== null &&
      !!filters.account_id &&
      !!filters.date_from &&
      !!filters.date_to,
    staleTime: 60_000,
  })
}

// ---------------------------------------------------------------------------
// GL-002 — Trial Balance
// ---------------------------------------------------------------------------

export function useTrialBalance(filters: PeriodFilters | null) {
  return useQuery({
    queryKey: ['reports', 'trial-balance', filters],
    queryFn: async () => {
      const res = await api.get<TrialBalance>('/accounting/reports/trial-balance', {
        params: filters!,
      })
      return res.data
    },
    enabled: filters !== null && !!filters.date_from && !!filters.date_to,
    staleTime: 60_000,
  })
}

// ---------------------------------------------------------------------------
// GL-003 — Balance Sheet
// ---------------------------------------------------------------------------

export function useBalanceSheet(filters: BalanceSheetFilters | null) {
  return useQuery({
    queryKey: ['reports', 'balance-sheet', filters],
    queryFn: async () => {
      const res = await api.get<BalanceSheet>('/accounting/reports/balance-sheet', {
        params: filters!,
      })
      return res.data
    },
    enabled: filters !== null && !!filters.as_of_date,
    staleTime: 60_000,
  })
}

// ---------------------------------------------------------------------------
// GL-004 — Income Statement
// ---------------------------------------------------------------------------

export function useIncomeStatement(filters: PeriodFilters | null) {
  return useQuery({
    queryKey: ['reports', 'income-statement', filters],
    queryFn: async () => {
      const res = await api.get<IncomeStatement>('/accounting/reports/income-statement', {
        params: filters!,
      })
      return res.data
    },
    enabled: filters !== null && !!filters.date_from && !!filters.date_to,
    staleTime: 60_000,
  })
}

// ---------------------------------------------------------------------------
// GL-005 — Cash Flow Statement
// ---------------------------------------------------------------------------

export function useCashFlow(filters: PeriodFilters | null) {
  return useQuery({
    queryKey: ['reports', 'cash-flow', filters],
    queryFn: async () => {
      const res = await api.get<CashFlowStatement>('/accounting/reports/cash-flow', {
        params: filters!,
      })
      return res.data
    },
    enabled: filters !== null && !!filters.date_from && !!filters.date_to,
    staleTime: 60_000,
  })
}

// ---------------------------------------------------------------------------
// Pag-IBIG Monthly — Excel
// ---------------------------------------------------------------------------

export function useDownloadPagIbigMonthly(year: number, month: number) {
  const [isLoading, setIsLoading] = useState(false)

  const download = useCallback(async () => {
    setIsLoading(true)
    try {
      const res = await api.get('/reports/pagibig/monthly', {
        params: { year, month },
        responseType: 'blob',
      })
      triggerDownload(
        res.data as Blob,
        `pagibig-monthly-${year}-${String(month).padStart(2, '0')}.xlsx`,
      )
    } finally {
      setIsLoading(false)
    }
  }, [year, month])

  return { download, isLoading }
}
