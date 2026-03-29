import { useState } from 'react'
import { PageHeader } from '@/components/ui/PageHeader'
import {
  FileText,
  Table2,
  RefreshCw,
  Building2,
  Shield,
  Heart,
  Home,
} from 'lucide-react'
import {
  useDownloadForm1601c,
  useDownloadAlphalist,
  useDownloadSssSbr2,
  useDownloadPhilHealthRf1,
  useDownloadPagIbigMonthly,
} from '@/hooks/useReports'

const CURRENT_YEAR  = new Date().getFullYear()
const CURRENT_MONTH = new Date().getMonth() + 1

const YEARS  = Array.from({ length: 6 }, (_, i) => CURRENT_YEAR - i)
const MONTHS = [
  { value: 1,  label: 'January'   },
  { value: 2,  label: 'February'  },
  { value: 3,  label: 'March'     },
  { value: 4,  label: 'April'     },
  { value: 5,  label: 'May'       },
  { value: 6,  label: 'June'      },
  { value: 7,  label: 'July'      },
  { value: 8,  label: 'August'    },
  { value: 9,  label: 'September' },
  { value: 10, label: 'October'   },
  { value: 11, label: 'November'  },
  { value: 12, label: 'December'  },
]

// ─── Download Button ──────────────────────────────────────────────────────────

interface DownloadBtnProps {
  label: string
  subtitle: string
  icon: React.ElementType
  onDownload: () => Promise<void>
  isLoading: boolean
  iconColor?: string
}

function DownloadButton({
  label,
  subtitle,
  icon: Icon,
  onDownload,
  isLoading,
  iconColor = 'text-neutral-600',
}: DownloadBtnProps) {
  return (
    <button
      onClick={() => void onDownload()}
      disabled={isLoading}
      className="flex items-start gap-3 w-full text-left border border-neutral-200 rounded p-4 hover:bg-neutral-50 hover:border-neutral-300 transition-colors disabled:opacity-50 disabled:cursor-not-allowed group"
    >
      <div className={`mt-0.5 shrink-0 ${iconColor}`}>
        {isLoading
          ? <RefreshCw className="h-5 w-5 animate-spin" />
          : <Icon className="h-5 w-5" />
        }
      </div>
      <div>
        <div className="text-sm font-medium text-neutral-800 group-hover:text-neutral-900">
          {label}
        </div>
        <div className="text-xs text-neutral-500 mt-0.5">{subtitle}</div>
      </div>
    </button>
  )
}

// ─── Page ─────────────────────────────────────────────────────────────────────

export default function GovernmentReportsPage() {
  const [year,  setYear]  = useState(CURRENT_YEAR)
  const [month, setMonth] = useState(CURRENT_MONTH)

  // BIR hooks
  const form1601c  = useDownloadForm1601c(year, month)
  const alphalist  = useDownloadAlphalist(year)

  // SSS / PhilHealth / Pag-IBIG hooks
  const sssSbr2    = useDownloadSssSbr2(year, month)
  const philRf1    = useDownloadPhilHealthRf1(year, month)
  const pagibig    = useDownloadPagIbigMonthly(year, month)

  return (
    <div>
      {/* Header */}
      <PageHeader
        title="Government Reports"
        subtitle="Generate BIR, SSS, PhilHealth, and Pag-IBIG compliance reports. All reports reflect frozen payroll data from completed runs."
      />

      {/* Period selector */}
      <div className="bg-white border border-neutral-200 rounded p-4 mb-6 flex flex-wrap items-center gap-4">
        <div>
          <label className="block text-xs font-medium text-neutral-600 mb-1">
            Year
          </label>
          <select
            value={year}
            onChange={(e) => setYear(Number(e.target.value))}
            className="border border-neutral-300 rounded px-3 py-1.5 text-sm text-neutral-700 bg-white focus:outline-none focus:ring-1 focus:ring-neutral-400"
          >
            {YEARS.map((y) => (
              <option key={y} value={y}>{y}</option>
            ))}
          </select>
        </div>

        <div>
          <label className="block text-xs font-medium text-neutral-600 mb-1">
            Month <span className="text-neutral-400 font-normal">(monthly reports only)</span>
          </label>
          <select
            value={month}
            onChange={(e) => setMonth(Number(e.target.value))}
            className="border border-neutral-300 rounded px-3 py-1.5 text-sm text-neutral-700 bg-white focus:outline-none focus:ring-1 focus:ring-neutral-400"
          >
            {MONTHS.map((m) => (
              <option key={m.value} value={m.value}>{m.label}</option>
            ))}
          </select>
        </div>

        <div className="ml-auto text-xs text-neutral-400 self-end pb-1">
          Selected: {MONTHS.find((m) => m.value === month)?.label} {year}
        </div>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* BIR Section */}
        <div className="bg-white border border-neutral-200 rounded p-5">
          <div className="flex items-center gap-2 mb-4">
            <Building2 className="h-5 w-5 text-neutral-600" />
            <h2 className="text-base font-medium text-neutral-800">
              Bureau of Internal Revenue (BIR)
            </h2>
          </div>
          <div className="space-y-3">
            <DownloadButton
              label="Form 1601-C — Monthly Remittance Return"
              subtitle={`${MONTHS.find((m) => m.value === month)?.label} ${year} · PDF`}
              icon={FileText}
              onDownload={form1601c.download}
              isLoading={form1601c.isLoading}
              iconColor="text-neutral-600"
            />
            <DownloadButton
              label="Alphalist of Employees"
              subtitle={`Annual ${year} · Excel (.xlsx)`}
              icon={Table2}
              onDownload={alphalist.download}
              isLoading={alphalist.isLoading}
              iconColor="text-neutral-600"
            />
          </div>
        </div>

        {/* SSS, PhilHealth, Pag-IBIG Section */}
        <div className="bg-white border border-neutral-200 rounded p-5">
          <div className="flex items-center gap-2 mb-4">
            <Shield className="h-5 w-5 text-neutral-600" />
            <h2 className="text-base font-medium text-neutral-800">
              Government Agencies — Monthly Contributions
            </h2>
          </div>
          <div className="space-y-3">
            <DownloadButton
              label="SSS SBR2 Contribution Report"
              subtitle={`${MONTHS.find((m) => m.value === month)?.label} ${year} · Excel`}
              icon={Shield}
              onDownload={sssSbr2.download}
              isLoading={sssSbr2.isLoading}
              iconColor="text-neutral-600"
            />
            <DownloadButton
              label="PhilHealth RF-1 Premium Report"
              subtitle={`${MONTHS.find((m) => m.value === month)?.label} ${year} · Excel`}
              icon={Heart}
              onDownload={philRf1.download}
              isLoading={philRf1.isLoading}
              iconColor="text-neutral-600"
            />
            <DownloadButton
              label="Pag-IBIG Monthly Contribution Report"
              subtitle={`${MONTHS.find((m) => m.value === month)?.label} ${year} · Excel`}
              icon={Home}
              onDownload={pagibig.download}
              isLoading={pagibig.isLoading}
              iconColor="text-neutral-600"
            />
          </div>
        </div>
      </div>

      {/* Info callout */}
      <div className="mt-6 bg-neutral-50 border border-neutral-200 rounded p-4 text-sm text-neutral-700">
        <strong>Note:</strong> Reports only include employees from{' '}
        <span className="font-medium">completed</span> payroll runs. Runs that are still
        in draft, locked, processing, or cancelled status are excluded.
        Form 2316 per-employee is available from each employee&apos;s profile or the payroll run detail page.
      </div>
    </div>
  )
}
