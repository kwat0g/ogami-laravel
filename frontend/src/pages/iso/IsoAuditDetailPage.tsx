import { useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { ArrowLeft, ClipboardList, Plus } from 'lucide-react';
import { toast } from 'sonner';
import {
  useAudit,
  useStartAudit,
  useCompleteAudit,
  useCreateFinding,
  useCloseFinding,
} from '@/hooks/useISO';
import SkeletonLoader from '@/components/ui/SkeletonLoader';
import ConfirmDialog from '@/components/ui/ConfirmDialog';
import type { AuditStatus, FindingSeverity, FindingType, FindingStatus } from '@/types/iso';

const AUDIT_STATUS_COLORS: Record<AuditStatus, string> = {
  planned: 'bg-neutral-100 text-neutral-600',
  in_progress: 'bg-neutral-100 text-neutral-700',
  completed: 'bg-neutral-200 text-neutral-800',
  closed: 'bg-neutral-100 text-neutral-400',
};

const FINDING_STATUS_COLORS: Record<FindingStatus, string> = {
  open: 'bg-neutral-100 text-neutral-600',
  in_progress: 'bg-neutral-100 text-neutral-700',
  closed: 'bg-neutral-200 text-neutral-800',
  verified: 'bg-neutral-200 text-neutral-700',
};

const SEVERITY_COLORS: Record<FindingSeverity, string> = {
  minor: 'bg-neutral-100 text-neutral-600',
  major: 'bg-neutral-200 text-neutral-800',
};

const INPUT = 'w-full border border-neutral-300 rounded px-3 py-2 text-sm focus:ring-1 focus:ring-neutral-400 bg-white';

type ConfirmState = {
  open: boolean;
  title: string;
  description: string;
  confirmLabel: string;
  onConfirm: () => Promise<void>;
};

export default function IsoAuditDetailPage(): React.ReactElement {
  const { ulid } = useParams<{ ulid: string }>();
  const navigate = useNavigate();
  const { data, isLoading, isError } = useAudit(ulid ?? '');
  const startMut = useStartAudit();
  const completeMut = useCompleteAudit();
  const createFindingMut = useCreateFinding(ulid ?? '');
  const closeFindingMut = useCloseFinding();

  const [confirmState, setConfirmState] = useState<ConfirmState>({
    open: false,
    title: '',
    description: '',
    confirmLabel: 'Confirm',
    onConfirm: async () => {},
  });
  const closeConfirm = () => setConfirmState(s => ({ ...s, open: false }));

  const [showFindingForm, setShowFindingForm] = useState(false);
  const [summaryInput, setSummaryInput] = useState('');
  const [showCompleteDialog, setShowCompleteDialog] = useState(false);
  const [findingForm, setFindingForm] = useState({
    finding_type: 'nonconformity' as FindingType,
    clause_ref: '',
    description: '',
    severity: 'minor' as FindingSeverity,
  });

  if (isLoading) return <SkeletonLoader rows={6} />;

  if (isError || !data?.data) {
    return (
      <div className="text-sm text-red-600 mt-4">
        Audit not found or you do not have access.{' '}
        <button onClick={() => navigate('/iso/audits')} className="underline text-neutral-600">
          Back to list
        </button>
      </div>
    );
  }

  const audit = data.data;

  const handleStart = () => {
    setConfirmState({
      open: true,
      title: 'Start audit?',
      description: 'This will mark the audit as in progress.',
      confirmLabel: 'Start Audit',
      onConfirm: async () => {
        await startMut.mutateAsync(audit.ulid);
        toast.success('Audit started.');
        closeConfirm();
      },
    });
  };

  const handleComplete = async () => {
    try {
      await completeMut.mutateAsync({ ulid: audit.ulid, summary: summaryInput || undefined });
      toast.success('Audit completed.');
      setShowCompleteDialog(false);
      setSummaryInput('');
    } catch {
      toast.error('Failed to complete audit.');
    }
  };

  const handleAddFinding = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!findingForm.description.trim()) { toast.error('Description is required.'); return; }
    try {
      await createFindingMut.mutateAsync({
        finding_type: findingForm.finding_type,
        clause_ref: findingForm.clause_ref || undefined,
        description: findingForm.description.trim(),
        severity: findingForm.severity,
      });
      toast.success('Finding added.');
      setFindingForm({ finding_type: 'nonconformity', clause_ref: '', description: '', severity: 'minor' });
      setShowFindingForm(false);
    } catch {
      toast.error('Failed to add finding.');
    }
  };

  const handleCloseFinding = (findingUlid: string) => {
    setConfirmState({
      open: true,
      title: 'Close finding?',
      description: 'This will mark the finding as closed.',
      confirmLabel: 'Close Finding',
      onConfirm: async () => {
        await closeFindingMut.mutateAsync({ findingUlid, auditUlid: audit.ulid });
        toast.success('Finding closed.');
        closeConfirm();
      },
    });
  };

  return (
    <div className="max-w-3xl space-y-5">
      {/* Header */}
      <div className="flex items-center justify-between gap-4">
        <div className="flex items-center gap-3">
          <button
            type="button"
            onClick={() => navigate('/iso/audits')}
            className="p-2 rounded-lg border border-neutral-200 bg-white hover:bg-neutral-50 hover:border-neutral-300 text-neutral-500"
            aria-label="Back to Audits"
          >
            <ArrowLeft className="w-4 h-4" />
          </button>
          <div className="w-10 h-10 bg-neutral-100 rounded-lg flex items-center justify-center shrink-0">
            <ClipboardList className="w-5 h-5 text-neutral-600" />
          </div>
          <div>
            <div className="flex items-center gap-3">
              <h1 className="text-lg font-semibold text-neutral-900 font-mono">{audit.audit_reference}</h1>
              <span className={`inline-flex items-center px-2.5 py-0.5 rounded text-xs font-medium ${AUDIT_STATUS_COLORS[audit.status]}`}>
                {audit.status.replace('_', ' ')}
              </span>
            </div>
            <p className="text-sm text-neutral-500 mt-0.5">{audit.standard} — {audit.audit_date}</p>
          </div>
        </div>

        <div className="flex items-center gap-2 shrink-0">
          {audit.status === 'planned' && (
            <button
              type="button"
              onClick={handleStart}
              className="px-4 py-2 text-sm border border-neutral-300 rounded hover:bg-neutral-50"
            >
              Start Audit
            </button>
          )}
          {audit.status === 'in_progress' && (
            <button
              type="button"
              onClick={() => setShowCompleteDialog(true)}
              className="px-4 py-2 text-sm bg-neutral-900 text-white rounded hover:bg-neutral-800"
            >
              Complete Audit
            </button>
          )}
        </div>
      </div>

      {/* Details */}
      <div className="bg-white border border-neutral-200 rounded p-6">
        <dl className="grid grid-cols-2 gap-x-8 gap-y-4 text-sm">
          <div className="col-span-2">
            <dt className="text-xs font-semibold text-neutral-500 uppercase tracking-wide">Scope</dt>
            <dd className="mt-1 text-neutral-900">{audit.audit_scope}</dd>
          </div>
          <div>
            <dt className="text-xs font-semibold text-neutral-500 uppercase tracking-wide">Standard</dt>
            <dd className="mt-1 text-neutral-900">{audit.standard}</dd>
          </div>
          <div>
            <dt className="text-xs font-semibold text-neutral-500 uppercase tracking-wide">Lead Auditor</dt>
            <dd className="mt-1 text-neutral-900">{audit.lead_auditor?.name ?? '—'}</dd>
          </div>
          <div>
            <dt className="text-xs font-semibold text-neutral-500 uppercase tracking-wide">Audit Date</dt>
            <dd className="mt-1 text-neutral-900">{audit.audit_date}</dd>
          </div>
          {audit.summary && (
            <div className="col-span-2">
              <dt className="text-xs font-semibold text-neutral-500 uppercase tracking-wide">Summary</dt>
              <dd className="mt-1 text-neutral-900">{audit.summary}</dd>
            </div>
          )}
        </dl>
      </div>

      {/* Findings */}
      <div>
        <div className="flex items-center justify-between mb-2">
          <h2 className="text-sm font-semibold text-neutral-700">Findings</h2>
          {(audit.status === 'in_progress' || audit.status === 'completed') && (
            <button
              type="button"
              onClick={() => setShowFindingForm(s => !s)}
              className="flex items-center gap-1 px-3 py-1.5 text-sm border border-neutral-300 rounded hover:bg-neutral-50"
            >
              <Plus className="w-3.5 h-3.5" />
              Add Finding
            </button>
          )}
        </div>

        {showFindingForm && (
          <form onSubmit={handleAddFinding} className="mb-3 bg-white border border-neutral-200 rounded p-4 space-y-3">
            <div className="grid grid-cols-2 gap-4">
              <div>
                <label className="block text-sm font-medium text-neutral-700 mb-1">Type</label>
                <select
                  className={INPUT}
                  value={findingForm.finding_type}
                  onChange={e => setFindingForm(s => ({ ...s, finding_type: e.target.value as FindingType }))}
                >
                  <option value="nonconformity">Nonconformity</option>
                  <option value="observation">Observation</option>
                  <option value="opportunity">Opportunity</option>
                </select>
              </div>
              <div>
                <label className="block text-sm font-medium text-neutral-700 mb-1">Severity</label>
                <select
                  className={INPUT}
                  value={findingForm.severity}
                  onChange={e => setFindingForm(s => ({ ...s, severity: e.target.value as FindingSeverity }))}
                >
                  <option value="minor">Minor</option>
                  <option value="major">Major</option>
                </select>
              </div>
            </div>
            <div>
              <label className="block text-sm font-medium text-neutral-700 mb-1">Clause Reference</label>
              <input
                type="text"
                className={INPUT}
                value={findingForm.clause_ref}
                onChange={e => setFindingForm(s => ({ ...s, clause_ref: e.target.value }))}
                placeholder="e.g. 8.4.1"
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-neutral-700 mb-1">Description *</label>
              <textarea
                className={INPUT}
                rows={3}
                value={findingForm.description}
                onChange={e => setFindingForm(s => ({ ...s, description: e.target.value }))}
                required
              />
            </div>
            <div className="flex justify-end gap-2 pt-1">
              <button
                type="button"
                onClick={() => setShowFindingForm(false)}
                className="px-3 py-1.5 text-sm border border-neutral-300 rounded hover:bg-neutral-50"
              >
                Cancel
              </button>
              <button
                type="submit"
                disabled={createFindingMut.isPending}
                className="px-3 py-1.5 text-sm bg-neutral-900 text-white rounded hover:bg-neutral-800 disabled:opacity-50"
              >
                {createFindingMut.isPending ? 'Saving…' : 'Add Finding'}
              </button>
            </div>
          </form>
        )}

        {(audit.findings ?? []).length === 0 ? (
          <p className="text-sm text-neutral-400 py-2">No findings recorded.</p>
        ) : (
          <div className="overflow-hidden rounded border border-neutral-200 bg-white">
            <table className="w-full text-sm">
              <thead className="bg-neutral-50 text-xs text-neutral-500 uppercase tracking-wide">
                <tr>
                  <th className="px-4 py-3 text-left">Clause</th>
                  <th className="px-4 py-3 text-left">Description</th>
                  <th className="px-4 py-3 text-left">Severity</th>
                  <th className="px-4 py-3 text-left">Status</th>
                  <th className="px-4 py-3 text-left"></th>
                </tr>
              </thead>
              <tbody className="divide-y divide-neutral-100">
                {(audit.findings ?? []).map(finding => (
                  <tr key={finding.id} className="hover:bg-neutral-50">
                    <td className="px-4 py-3 font-mono text-xs text-neutral-600">{finding.clause_ref ?? '—'}</td>
                    <td className="px-4 py-3 text-neutral-900 max-w-xs">{finding.description}</td>
                    <td className="px-4 py-3">
                      <span className={`inline-flex px-2 py-0.5 rounded text-xs font-medium ${SEVERITY_COLORS[finding.severity]}`}>
                        {finding.severity}
                      </span>
                    </td>
                    <td className="px-4 py-3">
                      <span className={`inline-flex px-2 py-0.5 rounded text-xs font-medium ${FINDING_STATUS_COLORS[finding.status]}`}>
                        {finding.status.replace('_', ' ')}
                      </span>
                    </td>
                    <td className="px-4 py-3">
                      <div className="flex items-center gap-2">
                        {finding.status !== 'closed' && finding.status !== 'verified' && (
                          <button
                            type="button"
                            onClick={() => handleCloseFinding(finding.ulid)}
                            className="text-xs text-neutral-600 border border-neutral-300 rounded px-2 py-0.5 hover:bg-neutral-50"
                          >
                            Close
                          </button>
                        )}
                        {finding.actions_count != null && finding.actions_count > 0 && (
                          <span className="text-xs text-neutral-500">{finding.actions_count} CAPA action{finding.actions_count !== 1 ? 's' : ''}</span>
                        )}
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>

      {/* Complete audit dialog */}
      {showCompleteDialog && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/30">
          <div className="bg-white border border-neutral-200 rounded p-6 w-full max-w-md shadow-lg">
            <h2 className="text-base font-semibold text-neutral-900 mb-3">Complete Audit</h2>
            <div className="mb-4">
              <label className="block text-sm font-medium text-neutral-700 mb-1">Summary (optional)</label>
              <textarea
                className={INPUT}
                rows={4}
                value={summaryInput}
                onChange={e => setSummaryInput(e.target.value)}
                placeholder="Overall audit findings summary…"
              />
            </div>
            <div className="flex justify-end gap-3">
              <button
                type="button"
                onClick={() => setShowCompleteDialog(false)}
                className="px-4 py-2 text-sm border border-neutral-300 rounded hover:bg-neutral-50"
              >
                Cancel
              </button>
              <button
                type="button"
                disabled={completeMut.isPending}
                onClick={handleComplete}
                className="px-4 py-2 text-sm bg-neutral-900 text-white rounded hover:bg-neutral-800 disabled:opacity-50"
              >
                {completeMut.isPending ? 'Completing…' : 'Complete Audit'}
              </button>
            </div>
          </div>
        </div>
      )}

      <ConfirmDialog
        open={confirmState.open}
        onClose={closeConfirm}
        onConfirm={confirmState.onConfirm}
        title={confirmState.title}
        description={confirmState.description}
        confirmLabel={confirmState.confirmLabel}
        variant="warning"
        loading={startMut.isPending || closeFindingMut.isPending}
      />
    </div>
  );
}
