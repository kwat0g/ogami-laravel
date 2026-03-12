import { useState, useEffect, useRef } from 'react'
import { Search, ArrowRight } from 'lucide-react'
import { useQuery } from '@tanstack/react-query'
import { useNavigate } from 'react-router-dom'
import api from '@/lib/api'

interface SearchResult {
  module: string
  type: string
  label: string
  url: string
}

function useGlobalSearch(q: string) {
  return useQuery({
    queryKey: ['global-search', q],
    queryFn: async () => {
      const res = await api.get<{ data: SearchResult[] }>('/search', { params: { q, limit: 8 } })
      return res.data.data
    },
    enabled: q.length >= 2,
    staleTime: 10_000,
  })
}

const moduleColor: Record<string, string> = {
  HR: 'bg-blue-100 text-blue-700',
  AP: 'bg-violet-100 text-violet-700',
  AR: 'bg-emerald-100 text-emerald-700',
  Inventory: 'bg-cyan-100 text-cyan-700',
  Procurement: 'bg-amber-100 text-amber-700',
  Maintenance: 'bg-rose-100 text-rose-700',
}

export default function GlobalSearchPage(): React.ReactElement {
  const [query, setQuery] = useState('')
  const [debouncedQuery, setDebouncedQuery] = useState('')
  const inputRef = useRef<HTMLInputElement>(null)
  const navigate = useNavigate()

  useEffect(() => {
    inputRef.current?.focus()
  }, [])

  useEffect(() => {
    const timer = setTimeout(() => setDebouncedQuery(query), 300)
    return () => clearTimeout(timer)
  }, [query])

  const { data: results, isLoading } = useGlobalSearch(debouncedQuery)

  return (
    <div className="max-w-2xl mx-auto pt-8">
      <div className="relative mb-6">
        <Search className="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-neutral-400" />
        <input
          ref={inputRef}
          type="text"
          value={query}
          onChange={(e) => setQuery(e.target.value)}
          placeholder="Search employees, vendors, items, POs, equipment…"
          className="w-full pl-12 pr-4 py-4 text-lg border border-neutral-300 rounded-xl shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
        />
      </div>

      {debouncedQuery.length < 2 && (
        <p className="text-sm text-neutral-400 text-center">Type at least 2 characters to search across all modules.</p>
      )}

      {isLoading && <p className="text-sm text-neutral-500 text-center">Searching…</p>}

      {results && results.length === 0 && debouncedQuery.length >= 2 && (
        <p className="text-sm text-neutral-500 text-center">No results found for "{debouncedQuery}".</p>
      )}

      {results && results.length > 0 && (
        <div className="bg-white border border-neutral-200 rounded-lg divide-y divide-neutral-100 shadow-sm">
          {results.map((r, i) => (
            <button
              key={`${r.url}-${i}`}
              onClick={() => navigate(r.url)}
              className="w-full flex items-center gap-3 px-4 py-3 hover:bg-neutral-50 transition-colors text-left"
            >
              <span className={`text-[10px] font-bold px-2 py-0.5 rounded-full ${moduleColor[r.module] ?? 'bg-neutral-100 text-neutral-600'}`}>
                {r.module}
              </span>
              <span className="text-xs text-neutral-400 w-20 shrink-0">{r.type}</span>
              <span className="flex-1 text-sm text-neutral-800 truncate">{r.label}</span>
              <ArrowRight className="w-4 h-4 text-neutral-300 shrink-0" />
            </button>
          ))}
        </div>
      )}
    </div>
  )
}
