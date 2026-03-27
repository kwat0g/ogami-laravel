/**
 * GlobalSearchPage - Cross-module search with recent searches
 *
 * Features:
 * - Searches across employees, vendors, customers, items, POs, equipment
 * - Recent searches persisted in localStorage
 * - Keyboard navigation (Arrow Up/Down + Enter)
 * - Accessible via Cmd+K / Ctrl+K shortcut from any page
 */
import { useState, useEffect, useRef, useCallback } from 'react'
import { Search, ArrowRight, Clock, X, Loader2 } from 'lucide-react'
import { useQuery } from '@tanstack/react-query'
import { useNavigate } from 'react-router-dom'
import api from '@/lib/api'

interface SearchResult {
  module: string
  type: string
  label: string
  url: string
}

const RECENT_SEARCHES_KEY = 'ogami_recent_searches'
const MAX_RECENT = 5

function useGlobalSearch(q: string) {
  return useQuery({
    queryKey: ['global-search', q],
    queryFn: async () => {
      const res = await api.get<{ data: SearchResult[] }>('/search', { params: { q, limit: 10 } })
      return res.data.data
    },
    enabled: q.length >= 2,
    staleTime: 10_000,
  })
}

function getRecentSearches(): string[] {
  try {
    const raw = localStorage.getItem(RECENT_SEARCHES_KEY)
    return raw ? JSON.parse(raw) : []
  } catch {
    return []
  }
}

function saveRecentSearch(query: string) {
  try {
    const recent = getRecentSearches().filter(s => s !== query)
    recent.unshift(query)
    localStorage.setItem(RECENT_SEARCHES_KEY, JSON.stringify(recent.slice(0, MAX_RECENT)))
  } catch {
    // ignore
  }
}

function clearRecentSearches() {
  try {
    localStorage.removeItem(RECENT_SEARCHES_KEY)
  } catch {
    // ignore
  }
}

const MODULE_COLORS: Record<string, string> = {
  HR: 'bg-blue-100 text-blue-700',
  AP: 'bg-violet-100 text-violet-700',
  AR: 'bg-emerald-100 text-emerald-700',
  Inventory: 'bg-cyan-100 text-cyan-700',
  Procurement: 'bg-amber-100 text-amber-700',
  Maintenance: 'bg-rose-100 text-rose-700',
  Production: 'bg-orange-100 text-orange-700',
  QC: 'bg-teal-100 text-teal-700',
  Payroll: 'bg-indigo-100 text-indigo-700',
  Delivery: 'bg-sky-100 text-sky-700',
  CRM: 'bg-pink-100 text-pink-700',
  Accounting: 'bg-purple-100 text-purple-700',
}

export default function GlobalSearchPage(): React.ReactElement {
  const [query, setQuery] = useState('')
  const [debouncedQuery, setDebouncedQuery] = useState('')
  const [selectedIndex, setSelectedIndex] = useState(-1)
  const [recentSearches, setRecentSearches] = useState<string[]>(getRecentSearches)
  const inputRef = useRef<HTMLInputElement>(null)
  const navigate = useNavigate()

  useEffect(() => {
    inputRef.current?.focus()
  }, [])

  useEffect(() => {
    const timer = setTimeout(() => setDebouncedQuery(query), 300)
    return () => clearTimeout(timer)
  }, [query])

  // Reset selection when results change
  useEffect(() => {
    setSelectedIndex(-1)
  }, [debouncedQuery])

  const { data: results, isLoading } = useGlobalSearch(debouncedQuery)

  const handleNavigate = useCallback((url: string, searchTerm?: string) => {
    if (searchTerm) saveRecentSearch(searchTerm)
    navigate(url)
  }, [navigate])

  const handleKeyDown = (e: React.KeyboardEvent) => {
    if (!results || results.length === 0) return

    if (e.key === 'ArrowDown') {
      e.preventDefault()
      setSelectedIndex(prev => Math.min(prev + 1, results.length - 1))
    } else if (e.key === 'ArrowUp') {
      e.preventDefault()
      setSelectedIndex(prev => Math.max(prev - 1, 0))
    } else if (e.key === 'Enter' && selectedIndex >= 0) {
      e.preventDefault()
      handleNavigate(results[selectedIndex].url, debouncedQuery)
    }
  }

  const handleClearRecent = () => {
    clearRecentSearches()
    setRecentSearches([])
  }

  const showRecent = query.length === 0 && recentSearches.length > 0
  const showHint = debouncedQuery.length < 2 && query.length === 0 && recentSearches.length === 0
  const showNoResults = results && results.length === 0 && debouncedQuery.length >= 2

  return (
    <div className="max-w-2xl mx-auto pt-8">
      {/* Search Input */}
      <div className="relative mb-6">
        <Search className="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-neutral-400" />
        <input
          ref={inputRef}
          type="text"
          value={query}
          onChange={(e) => setQuery(e.target.value)}
          onKeyDown={handleKeyDown}
          placeholder="Search employees, vendors, items, POs, equipment..."
          className="w-full pl-12 pr-12 py-4 text-lg border border-neutral-300 rounded-xl shadow-sm focus:outline-none focus:ring-2 focus:ring-neutral-500 focus:border-neutral-500 bg-white"
        />
        {query && (
          <button
            onClick={() => { setQuery(''); inputRef.current?.focus() }}
            className="absolute right-4 top-1/2 -translate-y-1/2 p-1 rounded hover:bg-neutral-100 text-neutral-400 hover:text-neutral-600"
          >
            <X className="w-4 h-4" />
          </button>
        )}
      </div>

      {/* Keyboard hint */}
      {showHint && (
        <div className="text-center space-y-2">
          <p className="text-sm text-neutral-400">Type at least 2 characters to search across all modules.</p>
          <p className="text-xs text-neutral-300">
            Use <kbd className="px-1.5 py-0.5 bg-neutral-100 border border-neutral-200 rounded text-[10px] font-mono">↑</kbd>{' '}
            <kbd className="px-1.5 py-0.5 bg-neutral-100 border border-neutral-200 rounded text-[10px] font-mono">↓</kbd> to navigate,{' '}
            <kbd className="px-1.5 py-0.5 bg-neutral-100 border border-neutral-200 rounded text-[10px] font-mono">Enter</kbd> to select
          </p>
        </div>
      )}

      {/* Recent Searches */}
      {showRecent && (
        <div className="mb-6">
          <div className="flex items-center justify-between mb-2">
            <p className="text-xs font-medium text-neutral-500 uppercase tracking-wide">Recent Searches</p>
            <button
              onClick={handleClearRecent}
              className="text-xs text-neutral-400 hover:text-neutral-600"
            >
              Clear
            </button>
          </div>
          <div className="bg-white border border-neutral-200 rounded-lg divide-y divide-neutral-100">
            {recentSearches.map((term) => (
              <button
                key={term}
                onClick={() => setQuery(term)}
                className="w-full flex items-center gap-3 px-4 py-2.5 hover:bg-neutral-50 transition-colors text-left"
              >
                <Clock className="w-3.5 h-3.5 text-neutral-300 shrink-0" />
                <span className="text-sm text-neutral-600">{term}</span>
              </button>
            ))}
          </div>
        </div>
      )}

      {/* Loading */}
      {isLoading && (
        <div className="flex items-center justify-center gap-2 py-8">
          <Loader2 className="w-4 h-4 text-neutral-400 animate-spin" />
          <p className="text-sm text-neutral-500">Searching...</p>
        </div>
      )}

      {/* No Results */}
      {showNoResults && (
        <div className="text-center py-8">
          <Search className="w-8 h-8 text-neutral-300 mx-auto mb-2" />
          <p className="text-sm text-neutral-500">No results found for "<span className="font-medium">{debouncedQuery}</span>"</p>
          <p className="text-xs text-neutral-400 mt-1">Try different keywords or check the spelling</p>
        </div>
      )}

      {/* Results */}
      {results && results.length > 0 && (
        <div>
          <p className="text-xs text-neutral-400 mb-2">{results.length} result{results.length > 1 ? 's' : ''} found</p>
          <div className="bg-white border border-neutral-200 rounded-lg divide-y divide-neutral-100 shadow-sm">
            {results.map((r, i) => (
              <button
                key={`${r.url}-${i}`}
                onClick={() => handleNavigate(r.url, debouncedQuery)}
                className={`w-full flex items-center gap-3 px-4 py-3 hover:bg-neutral-50 transition-colors text-left ${
                  i === selectedIndex ? 'bg-neutral-50 ring-1 ring-inset ring-neutral-200' : ''
                }`}
              >
                <span className={`text-[10px] font-bold px-2 py-0.5 rounded-full whitespace-nowrap ${MODULE_COLORS[r.module] ?? 'bg-neutral-100 text-neutral-600'}`}>
                  {r.module}
                </span>
                <span className="text-xs text-neutral-400 w-20 shrink-0">{r.type}</span>
                <span className="flex-1 text-sm text-neutral-800 truncate">{r.label}</span>
                <ArrowRight className="w-4 h-4 text-neutral-300 shrink-0" />
              </button>
            ))}
          </div>
        </div>
      )}
    </div>
  )
}
