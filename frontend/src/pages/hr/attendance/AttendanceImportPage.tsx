import { useRef, useState } from 'react'
import { useImportAttendance, downloadAttendanceTemplate } from '@/hooks/useAttendance'
import { PageHeader } from '@/components/ui/PageHeader'

interface ImportResult {
  imported: number
  failed: number
  errors: string[]
}

export default function AttendanceImportPage() {
  const importMutation = useImportAttendance()
  const fileInputRef = useRef<HTMLInputElement>(null)

  const [file, setFile]         = useState<File | null>(null)
  const [dragOver, setDragOver] = useState(false)
  const [result, setResult]     = useState<ImportResult | null>(null)

  const handleDrop = (e: React.DragEvent) => {
    e.preventDefault()
    setDragOver(false)
    const dropped = e.dataTransfer.files[0]
    if (dropped) setFile(dropped)
  }

  const handleSubmit = () => {
    if (!file) return
    importMutation.mutate(file, {
      onSuccess: (data) => setResult(data),
      onError: (err: unknown) => {
        const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message
        setResult({ imported: 0, failed: 0, errors: [msg ?? 'Import failed.'] })
      },
    })
  }

  return (
    <div className="max-w-4xl mx-auto">
      <PageHeader
        title="Import Attendance"
        subtitle="Upload a CSV file from your biometric/time-keeping device."
        backTo="/hr/attendance"
      />

      {/* Template download section */}
      <div className="bg-white border border-neutral-200 rounded-lg p-5 mb-6">
        <div className="flex items-start gap-4">
          <div className="p-3 bg-blue-50 rounded-lg">
            <svg className="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5}
                d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
          </div>
          <div className="flex-1">
            <h2 className="text-base font-semibold text-neutral-900">Download Template</h2>
            <p className="text-sm text-neutral-600 mt-1">
              Get a pre-formatted Excel template with all active employees listed. 
              Fill in the work dates, time in, and time out, then upload the completed file.
            </p>
            <div className="mt-3">
              <button
                onClick={() => downloadAttendanceTemplate()}
                className="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-blue-700 bg-blue-50 hover:bg-blue-100 rounded-lg transition-colors"
              >
                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2}
                    d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                </svg>
                Download Excel Template
              </button>
            </div>
          </div>
        </div>
      </div>

      {/* Instructions */}
      <div className="bg-amber-50 border border-amber-100 rounded-lg p-4 mb-6">
        <div className="flex gap-3">
          <svg className="w-5 h-5 text-amber-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5}
              d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          <div className="text-sm text-amber-800">
            <p className="font-medium mb-1">How to use the template:</p>
            <ol className="list-decimal list-inside space-y-1 text-amber-700">
              <li>Download the template above (contains all active employees)</li>
              <li>Fill in <strong>work_date</strong> for each employee (format: YYYY-MM-DD)</li>
              <li>Fill in <strong>time_in</strong> and <strong>time_out</strong> (format: HH:MM, 24-hour)</li>
              <li>Add optional notes if needed</li>
              <li>Save as CSV and upload below, or upload the Excel file directly</li>
            </ol>
          </div>
        </div>
      </div>

      {/* Drop zone */}
      <div
        onDragOver={(e) => { e.preventDefault(); setDragOver(true) }}
        onDragLeave={() => setDragOver(false)}
        onDrop={handleDrop}
        onClick={() => fileInputRef.current?.click()}
        className={`border-2 border-dashed rounded-lg p-10 flex flex-col items-center justify-center text-center cursor-pointer transition-colors
          ${dragOver ? 'border-neutral-400 bg-neutral-50' : 'border-neutral-300 bg-white hover:border-neutral-400'}`}
      >
        <svg className="w-10 h-10 text-neutral-400 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5}
            d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
        </svg>
        {file ? (
          <p className="text-sm font-medium text-neutral-700">{file.name}</p>
        ) : (
          <>
            <p className="text-sm font-medium text-neutral-700">Drop your CSV here or <span className="text-neutral-900 underline">browse</span></p>
            <p className="text-xs text-neutral-400 mt-1">Supported: .csv</p>
          </>
        )}
        <input ref={fileInputRef} type="file" accept=".csv" className="hidden" onChange={(e) => setFile(e.target.files?.[0] ?? null)} />
      </div>

      {/* Actions */}
      <div className="flex justify-between items-center mt-5">
        {file && (
          <button onClick={() => { setFile(null); setResult(null) }} className="text-sm text-neutral-500 hover:text-neutral-700">Clear</button>
        )}
        <div className="ml-auto">
          <button
            disabled={!file || importMutation.isPending}
            onClick={handleSubmit}
            className="px-5 py-2 text-sm bg-neutral-900 hover:bg-neutral-800 text-white rounded disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
          >
            {importMutation.isPending ? 'Importing…' : 'Start Import'}
          </button>
        </div>
      </div>

      {/* Result */}
      {result && (
        <div className="mt-6 bg-white border border-neutral-200 rounded-lg p-5">
          <h2 className="text-base font-semibold text-neutral-900 mb-3">Import Result</h2>
          <div className="flex gap-6 mb-4">
            <div className="text-center">
              <p className="text-lg font-semibold text-green-600">{result.imported}</p>
              <p className="text-xs text-neutral-500">Imported</p>
            </div>
            <div className="text-center">
              <p className="text-lg font-semibold text-red-600">{result.failed}</p>
              <p className="text-xs text-neutral-500">Failed</p>
            </div>
          </div>
          {result.errors.length > 0 && (
            <div className="bg-red-50 border border-red-100 rounded-lg p-3">
              <p className="text-xs font-semibold text-red-700 mb-2">Errors ({result.errors.length})</p>
              <ul className="space-y-0.5 max-h-48 overflow-y-auto">
                {result.errors.map((err, i) => (
                  <li key={i} className="text-xs text-red-600">{err}</li>
                ))}
              </ul>
            </div>
          )}
          {result.imported > 0 && result.errors.length === 0 && (
            <p className="text-sm text-green-700">All records imported successfully.</p>
          )}
        </div>
      )}
    </div>
  )
}
