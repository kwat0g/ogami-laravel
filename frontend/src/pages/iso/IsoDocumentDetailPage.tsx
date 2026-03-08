import { useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { ArrowLeft, FileText } from 'lucide-react';
import { toast } from 'sonner';
import { useDocument, useSubmitDocumentForReview, useApproveDocument } from '@/hooks/useISO';
import SkeletonLoader from '@/components/ui/SkeletonLoader';
import ConfirmDialog from '@/components/ui/ConfirmDialog';
import type { DocumentStatus, DocumentType } from '@/types/iso';

const STATUS_COLORS: Record<DocumentStatus, string> = {
  draft: 'bg-neutral-100 text-neutral-600',
  under_review: 'bg-neutral-100 text-neutral-700',
  approved: 'bg-neutral-200 text-neutral-800',
  obsolete: 'bg-neutral-100 text-neutral-400',
};

const TYPE_LABELS: Record<DocumentType, string> = {
  procedure: 'Procedure',
  work_instruction: 'Work Instruction',
  form: 'Form',
  manual: 'Manual',
  policy: 'Policy',
  record: 'Record',
};

type ConfirmAction = 'submit_review' | 'approve' | null;

export default function IsoDocumentDetailPage(): React.ReactElement {
  const { ulid } = useParams<{ ulid: string }>();
  const navigate = useNavigate();
  const { data, isLoading, isError } = useDocument(ulid ?? '');
  const submitReviewMut = useSubmitDocumentForReview();
  const approveMut = useApproveDocument();
  const [confirmAction, setConfirmAction] = useState<ConfirmAction>(null);

  if (isLoading) return <SkeletonLoader rows={6} />;

  if (isError || !data?.data) {
    return (
      <div className="text-sm text-red-600 mt-4">
        Document not found or you do not have access.{' '}
        <button onClick={() => navigate('/iso/documents')} className="underline text-neutral-600">
          Back to list
        </button>
      </div>
    );
  }

  const doc = data.data;

  const handleConfirm = async () => {
    if (!confirmAction) return;
    try {
      if (confirmAction === 'submit_review') {
        await submitReviewMut.mutateAsync(doc.ulid);
        toast.success('Document submitted for review.');
      } else {
        await approveMut.mutateAsync(doc.ulid);
        toast.success('Document approved.');
      }
      setConfirmAction(null);
    } catch {
      toast.error('Action failed.');
    }
  };

  const isPending = submitReviewMut.isPending || approveMut.isPending;

  return (
    <div className="max-w-3xl">
      {/* Header */}
      <div className="flex items-center justify-between gap-4 mb-6">
        <div className="flex items-center gap-3">
          <button
            type="button"
            onClick={() => navigate('/iso/documents')}
            className="p-2 rounded-lg border border-neutral-200 bg-white hover:bg-neutral-50 hover:border-neutral-300 text-neutral-500"
            aria-label="Back to Document Register"
          >
            <ArrowLeft className="w-4 h-4" />
          </button>
          <div className="w-10 h-10 bg-neutral-100 rounded-lg flex items-center justify-center shrink-0">
            <FileText className="w-5 h-5 text-neutral-600" />
          </div>
          <div>
            <div className="flex items-center gap-3">
              <h1 className="text-lg font-semibold text-neutral-900 font-mono">{doc.doc_code}</h1>
              <span className={`inline-flex items-center px-2.5 py-0.5 rounded text-xs font-medium ${STATUS_COLORS[doc.status]}`}>
                {doc.status.replace('_', ' ')}
              </span>
              {!doc.is_active && (
                <span className="inline-flex items-center px-2.5 py-0.5 rounded text-xs font-medium bg-neutral-100 text-neutral-400">
                  Inactive
                </span>
              )}
            </div>
            <p className="text-sm text-neutral-500 mt-0.5">{doc.title}</p>
          </div>
        </div>

        <div className="flex items-center gap-2 shrink-0">
          {doc.status === 'draft' && (
            <button
              type="button"
              onClick={() => setConfirmAction('submit_review')}
              className="px-4 py-2 text-sm border border-neutral-300 rounded hover:bg-neutral-50"
            >
              Submit for Review
            </button>
          )}
          {doc.status === 'under_review' && (
            <button
              type="button"
              onClick={() => setConfirmAction('approve')}
              className="px-4 py-2 text-sm bg-neutral-900 text-white rounded hover:bg-neutral-800"
            >
              Approve
            </button>
          )}
        </div>
      </div>

      {/* Details */}
      <div className="bg-white border border-neutral-200 rounded p-6">
        <dl className="grid grid-cols-2 gap-x-8 gap-y-4 text-sm">
          <div>
            <dt className="text-xs font-semibold text-neutral-500 uppercase tracking-wide">Document Type</dt>
            <dd className="mt-1 text-neutral-900">{TYPE_LABELS[doc.document_type] ?? doc.document_type}</dd>
          </div>
          <div>
            <dt className="text-xs font-semibold text-neutral-500 uppercase tracking-wide">Category</dt>
            <dd className="mt-1 text-neutral-900">{doc.category ?? '—'}</dd>
          </div>
          <div>
            <dt className="text-xs font-semibold text-neutral-500 uppercase tracking-wide">Version</dt>
            <dd className="mt-1 font-mono text-neutral-900">{doc.current_version}</dd>
          </div>
          <div>
            <dt className="text-xs font-semibold text-neutral-500 uppercase tracking-wide">Owner</dt>
            <dd className="mt-1 text-neutral-900">{doc.owner?.name ?? '—'}</dd>
          </div>
          <div>
            <dt className="text-xs font-semibold text-neutral-500 uppercase tracking-wide">Effective Date</dt>
            <dd className="mt-1 text-neutral-900">{doc.effective_date ?? '—'}</dd>
          </div>
          <div>
            <dt className="text-xs font-semibold text-neutral-500 uppercase tracking-wide">Review Date</dt>
            <dd className="mt-1 text-neutral-900">{doc.review_date ?? '—'}</dd>
          </div>
        </dl>
      </div>

      <ConfirmDialog
        open={confirmAction !== null}
        onClose={() => setConfirmAction(null)}
        onConfirm={handleConfirm}
        title={confirmAction === 'submit_review' ? 'Submit for review?' : 'Approve document?'}
        description={
          confirmAction === 'submit_review'
            ? 'This will submit the document for review. The status will change to "Under Review".'
            : 'This will approve the document and make it active. This action cannot be undone.'
        }
        confirmLabel={confirmAction === 'submit_review' ? 'Submit for Review' : 'Approve'}
        variant="warning"
        loading={isPending}
      />
    </div>
  );
}
