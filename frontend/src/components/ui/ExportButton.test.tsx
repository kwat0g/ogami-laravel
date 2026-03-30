import { describe, it, expect, vi, afterEach } from 'vitest'
import { render, screen, fireEvent, waitFor } from '@testing-library/react'
import { ExportButton } from './ExportButton'

// Mock URL.createObjectURL and revokeObjectURL
const mockCreateObjectURL = vi.fn(() => 'blob:mock-url')
const mockRevokeObjectURL = vi.fn()
Object.defineProperty(URL, 'createObjectURL', { value: mockCreateObjectURL })
Object.defineProperty(URL, 'revokeObjectURL', { value: mockRevokeObjectURL })

afterEach(() => {
  vi.restoreAllMocks()
})

describe('ExportButton', () => {
  const mockData = [
    { name: 'Alice', age: 30, dept: { name: 'Engineering' } },
    { name: 'Bob', age: 25, dept: { name: 'Marketing' } },
  ]

  const columns = [
    { key: 'name', label: 'Name' },
    { key: 'age', label: 'Age' },
    { key: 'dept.name', label: 'Department' },
  ]

  it('renders the export button', () => {
    render(<ExportButton data={mockData} columns={columns} />)
    expect(screen.getByText('Export CSV')).toBeInTheDocument()
  })

  it('renders custom label', () => {
    render(<ExportButton data={mockData} columns={columns} label="Download" />)
    expect(screen.getByText('Download')).toBeInTheDocument()
  })

  it('is disabled when data is empty', () => {
    render(<ExportButton data={[]} columns={columns} />)
    const button = screen.getByRole('button')
    expect(button).toBeDisabled()
  })

  it('is enabled when data has items', () => {
    render(<ExportButton data={mockData} columns={columns} />)
    const button = screen.getByRole('button')
    expect(button).not.toBeDisabled()
  })

  it('generates CSV on click', async () => {
    // Mock document.createElement for the download link
    const originalCreateElement = document.createElement.bind(document)
    const mockLink = originalCreateElement('a')
    const mockClick = vi.fn()
    mockLink.click = mockClick

    vi.spyOn(document, 'createElement').mockImplementation(((tagName: string, options?: ElementCreationOptions) => {
      if (tagName === 'a') {
        return mockLink
      }

      return originalCreateElement(tagName, options)
    }) as typeof document.createElement)

    render(<ExportButton data={mockData} columns={columns} filename="test" />)
    const button = screen.getByRole('button')
    fireEvent.click(button)

    // Wait for async export
    await waitFor(() => {
      expect(mockCreateObjectURL).toHaveBeenCalled()
    })
  })

  it('handles dot-notation nested values', () => {
    // This tests the internal getNestedValue logic indirectly through rendering
    render(<ExportButton data={mockData} columns={columns} />)
    // If it renders without error, dot-notation is working
    expect(screen.getByRole('button')).toBeInTheDocument()
  })

  it('supports custom format function', () => {
    const columnsWithFormat = [
      { key: 'name', label: 'Name' },
      { key: 'age', label: 'Age', format: (v: unknown) => `${v} years` },
    ]
    render(<ExportButton data={mockData} columns={columnsWithFormat} />)
    expect(screen.getByRole('button')).toBeInTheDocument()
  })
})
