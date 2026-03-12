import { useState } from 'react';
import { Link } from 'react-router-dom';
import { Plus, FileText } from 'lucide-react';
import { useDocuments } from '@/hooks/useISO';
import { PageHeader } from '@/components/ui/PageHeader';
import { useAuthStore } from '@/stores/authStore';
import type { DocumentStatus } from '@/types/iso';

const STATUS_COLORS: Record<DocumentStatus, string> = {
  draft: 'bg-neutral-100 text-neutral-600',
  under_review: 'bg-neutral-100 text-neutral-700',
  approved: 'bg-neutral-100 text-neutral-700',
  obsolete: 'bg-neutral-100 text-neutral-500',
};

export default function DocumentRegisterPage() {
  const [status, setStatus] = useState('');
  const [docType, setDocType] = useState('');
  const [withArchived, setWithArchived] = useState(false);
  const canManage = useAuthStore(s => s.hasPermission('iso.manage'));

  const params: Record<string, string | boolean> = {};
  if (status) params.status = status;
  if (docType) params.document_type = docType;
  if (withArchived) params.with_archived = true;

  const { data, isLoading } = useDocuments(Object.keys(params).length ? params : undefined);

  return (
    <div className="space-y-4">
      <PageHeader title="Document Register" />
      <div className="flex items-center justify-between">
        <div />
        {canManage && (
          <Link
            to="/iso/documents/new"
            className="inline-flex items-center gap-1.5 rounded bg-neutral-900 px-3 py-2 text-sm font-medium text-white hover:bg-neutral-800"
          >
            <Plus size={16} /> New Document
          </Link>
        )}
      </div>

      <div className="flex gap-2 flex-wrap">
        <select value={docType} onChange={e => setDocType(e.target.value)} className="rounded border border-neutral-300 px-2 py-1.5 text-sm">
          <option value="">All Types</option>
          <option value="procedure">Procedure</option>
          <option value="work_instruction">Work Instruction</option>
          <option value="form">Form</option>
          <option value="manual">Manual</option>
          <option value="policy">Policy</option>
          <option value="record">Record</option>
        </select>
        <select value={status} onChange={e => setStatus(e.target.value)} className="rounded border border-neutral-300 px-2 py-1.5 text-sm">
          <option value="">All Statuses</option>
          <option value="draft">Draft</option>
          <option value="under_review">Under Review</option>
          <option value="approved">Approved</option>
          <option value="obsolete">Obsolete</option>
        </select>
        <label className="flex items-center gap-2 text-sm text-neutral-600 cursor-pointer select-none">
          <input type="checkbox" checked={withArchived} onChange={(e) => setWithArchived(e.target.checked)} className="rounded border-neutral-300 text-neutral-900" />
          <span>Show Archived</span>
        </label>
      </div>

      <div className="overflow-hidden rounded border border-neutral-200 bg-white">
        <table className="w-full text-sm">
          <thead className="bg-neutral-50 text-xs font-medium text-neutral-500">
            <tr>
              <th className="px-4 py-3 text-left">Code</th>
              <th className="px-4 py-3 text-left">Title</th>
              <th className="px-4 py-3 text-left">Type</th>
              <th className="px-4 py-3 text-left">Version</th>
              <th className="px-4 py-3 text-left">Status</th>
              <th className="px-4 py-3 text-left">Review Date</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-neutral-100">
            {isLoading ? (
              <tr><td colSpan={6} className="px-4 py-8 text-center text-neutral-400">Loading…</td></tr>
            ) : (data?.data ?? []).length === 0 ? (
              <tr>
                <td colSpan={6} className="px-4 py-8 text-center text-neutral-400">
                  <FileText size={32} className="mx-auto mb-2 opacity-30" />
                  No documents found.
                </td>
              </tr>
            ) : (
              (data?.data ?? []).map(doc => (
                <tr key={doc.ulid} className="even:bg-neutral-100 hover:bg-neutral-50">
                  <td className="px-4 py-3 font-mono text-xs text-neutral-500">{doc.doc_code}</td>
                  <td className="px-4 py-3 font-medium text-neutral-900">
                    <Link to={`/iso/documents/${doc.ulid}`} className="hover:underline">{doc.title}</Link>
                  </td>
                  <td className="px-4 py-3 capitalize text-neutral-500">{doc.document_type?.replace('_', ' ') || '—'}</td>
                  <td className="px-4 py-3 text-neutral-600">{doc.current_version}</td>
                  <td className="px-4 py-3">
                    {doc.deleted_at && <span className="rounded px-2 py-0.5 text-xs font-medium bg-neutral-100 text-neutral-700 mr-1">Archived</span>}
                    <span className={`rounded px-2 py-0.5 text-xs font-medium ${STATUS_COLORS[doc.status]}`}>
                      {doc.status?.replace('_', ' ') || 'Unknown'}
                    </span>
                  </td>
                  <td className="px-4 py-3 text-neutral-500">{doc.review_date ?? '—'}</td>
                </tr>
              ))
            )}
          </tbody>
        </table>
      </div>
    </div>
  );
}
