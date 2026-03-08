import { useRef, useState } from 'react'
import { Link } from 'react-router-dom'
import { useImportAttendance } from '@/hooks/useAttendance'

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
    <div className="max-w-xl">
      <div className="flex items-center justify-between mb-6">
        <div>
          <h1 className="text-lg font-semibold text-neutral-900">Import Attendance</h1>
          <p className="text-sm text-neutral-500 mt-0.5">Upload a CSV file from your biometric/time-keeping device.</p>
        </div>
        <Link to="/hr/attendance" className="text-sm text-neutral-500 hover:text-neutral-700">← Back</Link>
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
            className="px-5 py-2 text-sm bg-neutral-900 hover:bg-neutral-800 text-white rounded disabled:opacity-40 transition-colors"
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
              <p className="text-2xl font-bold text-green-600">{result.imported}</p>
              <p className="text-xs text-neutral-500">Imported</p>
            </div>
            <div className="text-center">
              <p className="text-2xl font-bold text-red-600">{result.failed}</p>
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
