import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen } from '@testing-library/react'
import { useQuery } from '@tanstack/react-query'
import StatusTimeline from './StatusTimeline'

vi.mock('@tanstack/react-query', () => ({
  useQuery: vi.fn(),
}))

vi.mock('@/lib/api', () => ({
  default: {
    get: vi.fn(),
  },
}))

const mockUseQuery = vi.mocked(useQuery)

describe('StatusTimeline', () => {
  beforeEach(() => {
    mockUseQuery.mockReset()
  })

  it('renders loading state while audits are being fetched', () => {
    mockUseQuery.mockReturnValue({
      data: undefined,
      isLoading: true,
    } as ReturnType<typeof useQuery>)

    const { container } = render(
      <StatusTimeline auditableType="job_requisition" auditableId={1} />
    )

    expect(container.querySelector('.animate-pulse')).toBeInTheDocument()
  })

  it('renders empty state when no audits exist', () => {
    mockUseQuery.mockReturnValue({
      data: [],
      isLoading: false,
    } as ReturnType<typeof useQuery>)

    render(<StatusTimeline auditableType="job_requisition" auditableId={1} />)

    expect(screen.getByText('No activity recorded.')).toBeInTheDocument()
  })

  it('renders a status transition entry', () => {
    mockUseQuery.mockReturnValue({
      data: [
        {
          id: 1,
          event: 'updated',
          old_values: { status: 'draft' },
          new_values: { status: 'submitted' },
          user_id: 10,
          user_name: 'John Doe',
          created_at: '2026-01-15T10:00:00Z',
        },
      ],
      isLoading: false,
    } as ReturnType<typeof useQuery>)

    render(<StatusTimeline auditableType="job_requisition" auditableId={1} />)

    expect(screen.getByText('Activity Timeline')).toBeInTheDocument()
    expect(screen.getByText(/Status changed from/i)).toBeInTheDocument()
    expect(screen.getByText('draft')).toBeInTheDocument()
    expect(screen.getByText('submitted')).toBeInTheDocument()
    expect(screen.getByText('John Doe')).toBeInTheDocument()
  })

  it('renders generic audit events with changed fields', () => {
    mockUseQuery.mockReturnValue({
      data: [
        {
          id: 2,
          event: 'updated',
          old_values: { title: 'Old Title' },
          new_values: { title: 'New Title', updated_at: '2026-01-16T09:00:00Z' },
          user_id: 11,
          user_name: 'Jane Doe',
          created_at: '2026-01-16T09:00:00Z',
        },
      ],
      isLoading: false,
    } as ReturnType<typeof useQuery>)

    render(
      <StatusTimeline
        auditableType="job_requisition"
        auditableId={1}
        title="Requisition History"
      />
    )

    expect(screen.getByText('Requisition History')).toBeInTheDocument()
    expect(screen.getByText('Updated')).toBeInTheDocument()
    expect(screen.getByText('(title)')).toBeInTheDocument()
  })

  it('falls back to the raw event label and System actor when user is missing', () => {
    mockUseQuery.mockReturnValue({
      data: [
        {
          id: 3,
          event: 'archived',
          old_values: {},
          new_values: {},
          user_id: null,
          user_name: null,
          created_at: '2026-01-17T09:00:00Z',
        },
      ],
      isLoading: false,
    } as ReturnType<typeof useQuery>)

    render(<StatusTimeline auditableType="job_requisition" auditableId={1} />)

    expect(screen.getByText('archived')).toBeInTheDocument()
    expect(screen.getByText('System')).toBeInTheDocument()
  })

  it('configures the query with the current auditable target', () => {
    mockUseQuery.mockReturnValue({
      data: [],
      isLoading: false,
    } as ReturnType<typeof useQuery>)

    render(<StatusTimeline auditableType="job_offer" auditableId={42} />)

    expect(mockUseQuery).toHaveBeenCalledWith(
      expect.objectContaining({
        queryKey: ['audit-trail', 'job_offer', 42],
        enabled: true,
      })
    )
  })
})
