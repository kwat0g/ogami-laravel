import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { MemoryRouter, Route, Routes } from 'react-router-dom'
import BomDetailPage from './BomDetailPage'

vi.mock('@/stores/authStore', () => ({
  useAuthStore: (selector: (state: { hasPermission: (permission: string) => boolean }) => unknown) =>
    selector({ hasPermission: () => true }),
}))

vi.mock('@/hooks/useProduction', () => ({
  useBomCostHistory: vi.fn(),
}))

vi.mock('@/lib/api', () => ({
  default: {
    get: vi.fn(),
    post: vi.fn(),
    delete: vi.fn(),
  },
}))

import api from '@/lib/api'
import { useBomCostHistory } from '@/hooks/useProduction'

const mockUseBomCostHistory = vi.mocked(useBomCostHistory)
const mockApiGet = vi.mocked(api.get)

function renderPage(): void {
  const queryClient = new QueryClient({
    defaultOptions: {
      queries: { retry: false },
      mutations: { retry: false },
    },
  })

  render(
    <QueryClientProvider client={queryClient}>
      <MemoryRouter initialEntries={['/production/boms/01HISTORYULIDTEST00000000001']}>
        <Routes>
          <Route path="/production/boms/:ulid" element={<BomDetailPage />} />
        </Routes>
      </MemoryRouter>
    </QueryClientProvider>
  )
}

describe('BomDetailPage material cost history', () => {
  beforeEach(() => {
    vi.clearAllMocks()

    mockApiGet.mockImplementation(async (url: string) => {
      if (url.includes('/cost-breakdown')) {
        return {
          data: {
            data: {
              product_item_id: 1,
              product_name: 'FG Sample',
              bom_version: '1.0',
              material_cost_centavos: 10000,
              labor_cost_centavos: 0,
              overhead_cost_centavos: 0,
              total_standard_cost_centavos: 10000,
              components: [],
              routings: [],
            },
          },
        }
      }

      return {
        data: {
          data: {
            id: 1,
            ulid: '01HISTORYULIDTEST00000000001',
            product_item: { id: 1, item_code: 'FG-001', name: 'FG Sample', unit_of_measure: 'pcs' },
            product_item_id: 1,
            version: '1.0',
            is_active: true,
            notes: null,
            standard_production_days: 1,
            standard_cost_centavos: 10000,
            last_cost_rollup_at: null,
            components: [],
            created_at: '2026-04-04T00:00:00Z',
            updated_at: '2026-04-04T00:00:00Z',
          },
        },
      }
    })
  })

  it('renders empty-state text when no history entries are available', async () => {
    mockUseBomCostHistory.mockReturnValue({
      data: { data: [], meta: { current_page: 1, last_page: 1, per_page: 5, total: 0 } },
      isLoading: false,
    } as never)

    renderPage()

    expect(await screen.findByText('Material Cost History')).toBeInTheDocument()
    expect(await screen.findByText('No cost history yet. Recalculate cost to create the first snapshot.')).toBeInTheDocument()
  })

  it('renders history rows when snapshots are available', async () => {
    mockUseBomCostHistory.mockReturnValue({
      data: {
        data: [
          {
            id: 11,
            ulid: '01HISTORYSNAPSHOT000000000001',
            bom_id: 1,
            bom_version: '1.0',
            material_cost_centavos: 12600,
            component_lines: [],
            source: 'rollup',
            created_by_id: null,
            created_at: '2026-04-04T05:00:00Z',
            updated_at: '2026-04-04T05:00:00Z',
          },
        ],
        meta: { current_page: 1, last_page: 1, per_page: 5, total: 1 },
      },
      isLoading: false,
    } as never)

    renderPage()

    expect(await screen.findByText('Material Cost History')).toBeInTheDocument()
    expect(await screen.findByText('rollup')).toBeInTheDocument()
    expect(await screen.findByText('₱126.00')).toBeInTheDocument()
  })
})
