/**
 * Step 7 — Disburse & Publish
 * Two sub-stages:
 *  7a. Disburse: Trigger GL posting + generate bank disbursement file.
 *  7b. Publish: Publish payslips to employees (immediate or scheduled).
 */
import { useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { toast } from 'sonner'
import { Building2, Send, CheckCircle, Loader2, Download, CalendarClock, Bell } from 'lucide-react'
import {
  usePayrollRun,
  useDisburse,
  usePublish,
  useExportPayrollBreakdown,
} from '@/hooks/usePayroll'
import { WizardStepHeader } from '@/components/payroll/WizardStepHeader'
import ConfirmDialog from '@/components/ui/ConfirmDialog'
import { firstErrorMessage } from '@/lib/errorHandler'

function formatCentavos(c: number | null | undefined): string {
  if (c == null) return '—'
  return new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(c / 100)
}

type Stage = 'disburse' | 'publish' | 'done'

export default function PayrollRunDisbursePage() {
  const { ulid: id } = useParams<{ ulid: string }>()
  const runId = id ?? null
  const navigate = useNavigate()

  const { data: run } = usePayrollRun(runId)

  const disburse = useDisburse(runId)
  const publish = usePublish(runId)
  const { download: downloadBreakdown, isLoading: breakdownLoading } =
    useExportPayrollBreakdown(runId)

  const [publishAt, setPublishAt] = useState<string>('')
  const [notifyEmail, setNotifyEmail] = useState(true)
  const [notifyInApp, setNotifyInApp] = useState(true)
  const [disburseDone, setDisburseDone] = useState(false)

  // Determine current stage from run status
  const stage: Stage =
    run?.status === 'PUBLISHED' ? 'done' : run?.status === 'DISBURSED' ? 'publish' : 'disburse'

  // ── Validation for disburse ───────────────────────────────────────────────
  function validateDisburse(): boolean {
    if (run?.status !== 'VP_APPROVED' && run?.status !== 'ACCTG_APPROVED') {
      toast.error('Payroll run must be approved before disbursement.')
      return false
    }
    return true
  }

  async function handleDisburse() {
    if (!validateDisburse()) return
    try {
      await disburse.mutateAsync()
      setDisburseDone(true)
      toast.success('Disbursement complete. GL journal entry posted.')
    } catch (_err) {
      toast.error(firstErrorMessage(err))
    }
  }

  // ── Validation for publish ────────────────────────────────────────────────
  function validatePublish(): boolean {
    if (!disburseDone && run?.status !== 'DISBURSED') {
      toast.error('Disbursement must be completed before publishing payslips.')
      return false
    }
    if (publishAt) {
      const scheduledDate = new Date(publishAt)
      if (isNaN(scheduledDate.getTime())) {
        toast.error('Please enter a valid publish date and time.')
        return false
      }
      if (scheduledDate < new Date()) {
        toast.error('Publish date must be in the future.')
        return false
      }
    }
    return true
  }

  async function handlePublish() {
    if (!validatePublish()) return
    try {
      await publish.mutateAsync({
        publish_at: publishAt || null,
        notify_email: notifyEmail,
        notify_in_app: notifyInApp,
      })
      toast.success(
        publishAt
          ? `Payslips scheduled for ${new Date(publishAt).toLocaleString('en-PH')}.`
          : 'Payslips published. Employees can now view them.',
      )
      navigate('/payroll/runs')
    } catch (_err) {
      toast.error(firstErrorMessage(err))
    }
  }

  if (!run) return null

  if (stage === 'done' || run.status === 'PUBLISHED') {
    return (
      <div className="max-w-5xl mx-auto space-y-6">
        <WizardStepHeader
          step={7}
          title="Disburse & Publish"
          description={`Run #${run.reference_no}`}
        />
        <div className="bg-green-50 border border-green-200 rounded p-8 text-center">
          <CheckCircle className="h-12 w-12 text-green-500 mx-auto mb-3" />
          <h2 className="text-xl font-bold text-neutral-900 mb-2">Payroll Run Complete</h2>
          <p className="text-sm text-neutral-600">
            Payslips have been published. Reference: <strong>{run.reference_no}</strong>
          </p>
          <p className="text-sm text-neutral-600 mt-1">
            Net Pay Total: <strong>{formatCentavos(run.net_pay_total_centavos)}</strong>
          </p>
          <div className="flex justify-center gap-3 mt-6">
            <button
              type="button"
              onClick={() => void downloadBreakdown()}
              disabled={breakdownLoading}
              className="flex items-center gap-2 px-4 py-2 bg-neutral-900 text-white text-sm rounded hover:bg-neutral-800"
            >
              <Download className="h-4 w-4" /> Download Full Breakdown (Excel)
            </button>
            <button
              type="button"
              onClick={() => navigate('/payroll/runs')}
              className="flex items-center gap-2 px-4 py-2 bg-neutral-600 text-white text-sm rounded hover:bg-neutral-700"
            >
              Back to Runs
            </button>
          </div>
        </div>
      </div>
    )
  }

  return (
    <div className="max-w-5xl mx-auto space-y-6">
      <WizardStepHeader
        step={7}
        title="Disburse & Publish"
        description={`Run #${run.reference_no} — Post to GL, generate bank file, and publish payslips.`}
      />

      {/* ── 7a: Disburse ── */}
      <div
        className={`bg-white border rounded overflow-hidden ${stage !== 'disburse' || disburseDone ? 'border-green-200' : 'border-neutral-200'}`}
      >
        <div
          className={`px-5 py-4 border-b flex items-center gap-3 ${stage !== 'disburse' || disburseDone ? 'bg-green-50 border-green-100' : 'border-neutral-100'}`}
        >
          <div
            className={`w-8 h-8 rounded flex items-center justify-center text-sm font-bold ${stage !== 'disburse' || disburseDone ? 'bg-green-500 text-white' : 'bg-neutral-900 text-white'}`}
          >
            {stage !== 'disburse' || disburseDone ? '✓' : '7a'}
          </div>
          <div>
            <p className="text-sm font-semibold text-neutral-800">Step 7a: Disbursement</p>
            <p className="text-xs text-neutral-500">
              Post GL journal entry and generate bank disbursement file.
            </p>
          </div>
        </div>

        <div className="p-5 space-y-4">
          <div className="grid grid-cols-2 sm:grid-cols-3 gap-3 text-sm">
            <div className="bg-neutral-50 rounded p-3">
              <p className="text-xs text-neutral-400">Total Net Pay</p>
              <p className="font-bold text-neutral-900">
                {formatCentavos(run.net_pay_total_centavos)}
              </p>
            </div>
            <div className="bg-neutral-50 rounded p-3">
              <p className="text-xs text-neutral-400">Employees</p>
              <p className="font-bold text-neutral-900">{run.total_employees}</p>
            </div>
            <div className="bg-neutral-50 rounded p-3">
              <p className="text-xs text-neutral-400">Pay Date</p>
              <p className="font-bold text-neutral-900">
                {new Date(run.pay_date).toLocaleDateString('en-PH')}
              </p>
            </div>
          </div>

          <div className="flex items-center gap-3">
            {stage === 'disburse' && !disburseDone ? (
              <ConfirmDialog
                title="Post to GL & Disburse?"
                description={`This will post the GL journal entry and generate the bank disbursement file for ${run.total_employees} employees. This action cannot be undone.`}
                confirmLabel="Post & Disburse"
                onConfirm={handleDisburse}
              >
                <button
                  type="button"
                  disabled={disburse.isPending}
                  className="flex items-center gap-2 px-5 py-2 bg-neutral-900 hover:bg-neutral-800 disabled:opacity-50 disabled:cursor-not-allowed text-white text-sm font-medium rounded transition-colors"
                >
                  {disburse.isPending ? (
                    <>
                      <Loader2 className="h-4 w-4 animate-spin" /> Posting…
                    </>
                  ) : (
                    <>
                      <Building2 className="h-4 w-4" /> Post to GL & Disburse
                    </>
                  )}
                </button>
              </ConfirmDialog>
            ) : (
              <span className="flex items-center gap-2 text-sm text-green-600 font-medium">
                <CheckCircle className="h-4 w-4" /> GL posted. Disbursement complete.
              </span>
            )}

            {(stage === 'publish' || disburseDone) && (
              <button
                type="button"
                onClick={() => void downloadBreakdown()}
                disabled={breakdownLoading}
                className="flex items-center gap-2 px-4 py-2 bg-neutral-900 text-white text-sm rounded hover:bg-neutral-800"
              >
                <Download className="h-4 w-4" />
                {breakdownLoading ? 'Downloading…' : 'Full Breakdown (Excel)'}
              </button>
            )}
          </div>
        </div>
      </div>

      {/* ── 7b: Publish Payslips ── */}
      <div
        className={`bg-white border rounded overflow-hidden ${
          stage === 'disburse' && !disburseDone
            ? 'border-neutral-100 opacity-50 pointer-events-none'
            : 'border-neutral-200'
        }`}
      >
        <div className="px-5 py-4 border-b border-neutral-100 flex items-center gap-3">
          <div className="w-8 h-8 rounded bg-neutral-900 flex items-center justify-center text-sm font-bold text-white">
            7b
          </div>
          <div>
            <p className="text-sm font-semibold text-neutral-800">Step 7b: Publish Payslips</p>
            <p className="text-xs text-neutral-500">
              Make payslips visible to employees. Optionally schedule for a future date.
            </p>
          </div>
        </div>

        <div className="p-5 space-y-4">
          {/* Schedule */}
          <div>
            <label className="block text-sm font-medium text-neutral-700 mb-1">
              <CalendarClock className="inline h-4 w-4 mr-1 text-neutral-400" />
              Publish Date &amp; Time{' '}
              <span className="text-neutral-400 font-normal">
                (leave blank to publish immediately)
              </span>
            </label>
            <input
              type="datetime-local"
              value={publishAt}
              onChange={(e) => setPublishAt(e.target.value)}
              min={new Date().toISOString().slice(0, 16)}
              className="border border-neutral-300 rounded px-3 py-2 text-sm focus:ring-2 focus:ring-neutral-500 outline-none"
            />
            {publishAt && new Date(publishAt) < new Date() && (
              <p className="text-xs text-red-500 mt-1">Publish date must be in the future.</p>
            )}
          </div>

          {/* Notification options */}
          <div className="space-y-2">
            <p className="text-sm font-medium text-neutral-700 mb-1">
              <Bell className="inline h-4 w-4 mr-1 text-neutral-400" />
              Notify Employees
            </p>
            <label className="flex items-center gap-2 cursor-pointer">
              <input
                type="checkbox"
                checked={notifyEmail}
                onChange={(e) => setNotifyEmail(e.target.checked)}
                className="accent-neutral-900"
              />
              <span className="text-sm text-neutral-700">Email notification</span>
            </label>
            <label className="flex items-center gap-2 cursor-pointer">
              <input
                type="checkbox"
                checked={notifyInApp}
                onChange={(e) => setNotifyInApp(e.target.checked)}
                className="accent-neutral-900"
              />
              <span className="text-sm text-neutral-700">In-app notification</span>
            </label>
          </div>

          <ConfirmDialog
            title={publishAt ? 'Schedule Payslip Publication?' : 'Publish Payslips Now?'}
            description={
              publishAt
                ? `Payslips will be published on ${new Date(publishAt).toLocaleString('en-PH')} and employees will be notified.`
                : `Payslips will be published immediately and ${run.total_employees} employees will be notified.`
            }
            confirmLabel={publishAt ? 'Schedule Publication' : 'Publish Now'}
            onConfirm={handlePublish}
          >
            <button
              type="button"
              disabled={publish.isPending || (!disburseDone && run?.status !== 'DISBURSED')}
              className="flex items-center gap-2 px-5 py-2 bg-neutral-900 hover:bg-neutral-800 disabled:opacity-50 disabled:cursor-not-allowed text-white text-sm font-medium rounded transition-colors"
            >
              {publish.isPending ? (
                <>
                  <Loader2 className="h-4 w-4 animate-spin" /> Publishing…
                </>
              ) : (
                <>
                  <Send className="h-4 w-4" /> {publishAt ? 'Schedule Publication' : 'Publish Now'}
                </>
              )}
            </button>
          </ConfirmDialog>
        </div>
      </div>
    </div>
  )
}
