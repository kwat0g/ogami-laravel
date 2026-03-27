import { describe, it, expect } from 'vitest'
import { render, screen } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { StatusTimeline } from './StatusTimeline'

const steps = [
  { label: 'Draft', status: 'draft' },
  { label: 'Submitted', status: 'submitted', actor: 'John Doe', timestamp: '2026-01-15T10:00:00Z' },
  { label: 'Approved', status: 'approved' },
  { label: 'Completed', status: 'completed' },
]

describe('StatusTimeline', () => {
  it('renders all step labels', () => {
    render(
      <MemoryRouter>
        <StatusTimeline steps={steps} currentStatus="submitted" />
      </MemoryRouter>
    )
    expect(screen.getByText('Draft')).toBeInTheDocument()
    expect(screen.getByText('Submitted')).toBeInTheDocument()
    expect(screen.getByText('Approved')).toBeInTheDocument()
    expect(screen.getByText('Completed')).toBeInTheDocument()
  })

  it('shows actor name for completed steps', () => {
    render(
      <MemoryRouter>
        <StatusTimeline steps={steps} currentStatus="approved" />
      </MemoryRouter>
    )
    expect(screen.getByText(/John Doe/)).toBeInTheDocument()
  })

  it('renders in horizontal direction', () => {
    render(
      <MemoryRouter>
        <StatusTimeline steps={steps} currentStatus="submitted" direction="horizontal" />
      </MemoryRouter>
    )
    expect(screen.getByText('Draft')).toBeInTheDocument()
    expect(screen.getByText('Submitted')).toBeInTheDocument()
  })

  it('renders in vertical direction by default', () => {
    const { container } = render(
      <MemoryRouter>
        <StatusTimeline steps={steps} currentStatus="submitted" />
      </MemoryRouter>
    )
    // Vertical layout uses space-y-0 container
    expect(container.querySelector('.space-y-0')).toBeInTheDocument()
  })

  it('handles rejected status with red styling', () => {
    const { container } = render(
      <MemoryRouter>
        <StatusTimeline steps={steps} currentStatus="submitted" isRejected />
      </MemoryRouter>
    )
    // Should have a red ring on the current step
    expect(container.querySelector('.ring-red-400')).toBeInTheDocument()
  })

  it('shows comment when provided', () => {
    const stepsWithComment = [
      { label: 'Draft', status: 'draft' },
      { label: 'Reviewed', status: 'reviewed', actor: 'Jane', comment: 'Looks good' },
      { label: 'Done', status: 'done' },
    ]
    render(
      <MemoryRouter>
        <StatusTimeline steps={stepsWithComment} currentStatus="done" />
      </MemoryRouter>
    )
    expect(screen.getByText(/"Looks good"/)).toBeInTheDocument()
  })

  it('handles empty steps array', () => {
    const { container } = render(
      <MemoryRouter>
        <StatusTimeline steps={[]} currentStatus="draft" />
      </MemoryRouter>
    )
    expect(container).toBeTruthy()
  })
})
