import { useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { FileText, Trash2 } from 'lucide-react';
import { toast } from 'sonner';
import { useDocument, useSubmitDocumentForReview, useApproveDocument } from '@/hooks/useISO';
import SkeletonLoader from '@/components/ui/SkeletonLoader';
import ConfirmDialog from '@/components/ui/ConfirmDialog';
import ConfirmDestructiveDialog from '@/components/ui/ConfirmDestructiveDialog';
import PageHeader from '@/components/ui/PageHeader';
import StatusBadge from '@/components/ui/StatusBadge';
import { Card, CardHeader, CardBody } from '@/components/ui/Card';
import { InfoRow, InfoList } from '@/components/ui/InfoRow';
import { useAuthStore } from '@/stores/authStore';
import { firstErrorMessage } from '@/lib/errorHandler';
import type { DocumentType } from '@/types/iso';

const TYPE_LABELS: Record<DocumentType, string> = {
  procedure: 'Procedure',
  work_instruction: 'Work Instruction',
  form: 'Form',
  manual: 'Manual',
  policy: 'Policy',
  record: 'Record',
};

type ConfirmAction = 'submit_review' | 'approve' | 'delete' | null;

export default function IsoDocumentDetailPage(): React.ReactElement {
  const { ulid } = useParams<{ ulid: string }>();
  const navigate = useNavigate();
  const { data, isLoading, isError } = useDocument(ulid ?? '');
  const submitReviewMut = useSubmitDocumentForReview();
  const approveMut = useApproveDocument();
  const [confirmAction, setConfirmAction] = useState<ConfirmAction>(null);
  const canManage = useAuthStore((s) => s.hasPermission('iso.manage'));

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
      } else if (confirmAction === 'approve') {
        await approveMut.mutateAsync(doc.ulid);
        toast.success('Document approved.');
      }
      setConfirmAction(null);
    } catch (err) {
      toast.error(firstErrorMessage(err));
    }
  };

  const handleDelete = async () => {
    // Note: Delete functionality would need a corresponding hook
    toast.info('Delete functionality requires backend API support');
    setConfirmAction(null);
  };

  const isPending = submitReviewMut.isPending || approveMut.isPending;

  const statusBadges = (
    <div className="flex items-center gap-2">
      <StatusBadge status={doc.status}>{doc.status?.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase())}</StatusBadge>
      {!doc.is_active && (
        <span className="inline-flex items-center px-2.5 py-0.5 rounded text-xs font-medium bg-neutral-100 text-neutral-400">
          Inactive
        </span>
      )}
    </div>
  );

  return (
    <div className="max-w-7xl mx-auto">
      <PageHeader
        backTo="/iso/documents"
        title={doc.doc_code}
        subtitle={doc.title}
        icon={<FileText className="w-5 h-5" />}
        status={statusBadges}
        actions={
          canManage ? (
            <div className="flex items-center gap-2 shrink-0">
              {doc.status === 'draft' && (
                <>
                  <button
                    type="button"
                    onClick={() => setConfirmAction('submit_review')}
                    className="px-4 py-2 text-sm bg-white text-neutral-700 border border-neutral-300 rounded hover:bg-neutral-50"
                  >
                    Submit for Review
                  </button>
                  <ConfirmDestructiveDialog
                    title="Delete Document?"
                    description={`This will permanently delete "${doc.title}". This action cannot be undone.`}
                    confirmWord="DELETE"
                    confirmLabel="Delete Document"
                    onConfirm={handleDelete}
                  >
                    <button
                      type="button"
                      className="px-4 py-2 text-sm bg-red-50 text-red-600 border border-red-200 rounded hover:bg-red-100 flex items-center gap-1.5"
                    >
                      <Trash2 className="w-4 h-4" />
                      Delete
                    </button>
                  </ConfirmDestructiveDialog>
                </>
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
          ) : null
        }
      />

      {/* Details */}
      <Card>
        <CardHeader>Document Information</CardHeader>
        <CardBody>
          <InfoList columns={2}>
            <InfoRow label="Document Type" value={TYPE_LABELS[doc.document_type] ?? doc.document_type} />
            <InfoRow label="Category" value={doc.category ?? '—'} />
            <InfoRow label="Version" value={<span className="font-mono">{doc.current_version}</span>} />
            <InfoRow label="Owner" value={doc.owner?.name ?? '—'} />
            <InfoRow label="Effective Date" value={doc.effective_date ?? '—'} />
            <InfoRow label="Review Date" value={doc.review_date ?? '—'} />
          </InfoList>
        </CardBody>
      </Card>

      <ConfirmDialog
        open={confirmAction !== null && confirmAction !== 'delete'}
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
