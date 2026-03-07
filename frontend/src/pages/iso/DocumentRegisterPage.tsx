import { useState } from 'react';
import { Link } from 'react-router-dom';
import { Plus, FileText } from 'lucide-react';
import { useDocuments } from '@/hooks/useISO';
import type { DocumentStatus } from '@/types/iso';

const STATUS_COLORS: Record<DocumentStatus, string> = {
  draft: 'bg-gray-100 text-gray-600',
  under_review: 'bg-yellow-100 text-yellow-700',
  approved: 'bg-green-100 text-green-700',
  obsolete: 'bg-red-100 text-red-500',
};

export default function DocumentRegisterPage() {
  const [status, setStatus] = useState('');
  const [docType, setDocType] = useState('');
  const [withArchived, setWithArchived] = useState(false);

  const params: Record<string, string | boolean> = {};
  if (status) params.status = status;
  if (docType) params.document_type = docType;
  if (withArchived) params.with_archived = true;

  const { data, isLoading } = useDocuments(Object.keys(params).length ? params : undefined);

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-semibold">Document Register</h1>
        <Link
          to="/iso/documents/new"
          className="inline-flex items-center gap-1.5 rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700"
        >
          <Plus size={16} /> New Document
        </Link>
      </div>

      <div className="flex gap-2 flex-wrap">
        <select value={docType} onChange={e => setDocType(e.target.value)} className="rounded border border-gray-300 px-2 py-1.5 text-sm">
          <option value="">All Types</option>
          <option value="procedure">Procedure</option>
          <option value="work_instruction">Work Instruction</option>
          <option value="form">Form</option>
          <option value="manual">Manual</option>
          <option value="policy">Policy</option>
          <option value="record">Record</option>
        </select>
        <select value={status} onChange={e => setStatus(e.target.value)} className="rounded border border-gray-300 px-2 py-1.5 text-sm">
          <option value="">All Statuses</option>
          <option value="draft">Draft</option>
          <option value="under_review">Under Review</option>
          <option value="approved">Approved</option>
          <option value="obsolete">Obsolete</option>
        </select>
        <label className="flex items-center gap-2 text-sm text-gray-600 cursor-pointer select-none">
          <input type="checkbox" checked={withArchived} onChange={(e) => setWithArchived(e.target.checked)} className="rounded border-gray-300 text-indigo-600" />
          <span>Show Archived</span>
        </label>
      </div>

      <div className="overflow-hidden rounded-lg border border-gray-200 bg-white">
        <table className="w-full text-sm">
          <thead className="bg-gray-50 text-xs uppercase text-gray-500">
            <tr>
              <th className="px-4 py-3 text-left">Code</th>
              <th className="px-4 py-3 text-left">Title</th>
              <th className="px-4 py-3 text-left">Type</th>
              <th className="px-4 py-3 text-left">Version</th>
              <th className="px-4 py-3 text-left">Status</th>
              <th className="px-4 py-3 text-left">Review Date</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-gray-100">
            {isLoading ? (
              <tr><td colSpan={6} className="px-4 py-8 text-center text-gray-400">Loading…</td></tr>
            ) : (data?.data ?? []).length === 0 ? (
              <tr>
                <td colSpan={6} className="px-4 py-8 text-center text-gray-400">
                  <FileText size={32} className="mx-auto mb-2 opacity-30" />
                  No documents found.
                </td>
              </tr>
            ) : (
              (data?.data ?? []).map(doc => (
                <tr key={doc.ulid} className="hover:bg-gray-50">
                  <td className="px-4 py-3 font-mono text-xs text-gray-500">{doc.doc_code}</td>
                  <td className="px-4 py-3 font-medium text-indigo-600">{doc.title}</td>
                  <td className="px-4 py-3 capitalize text-gray-500">{doc.document_type.replace('_', ' ')}</td>
                  <td className="px-4 py-3 text-gray-600">{doc.current_version}</td>
                  <td className="px-4 py-3">
                    {doc.deleted_at && <span className="rounded-full px-2 py-0.5 text-xs font-medium bg-orange-100 text-orange-700 mr-1">Archived</span>}
                    <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${STATUS_COLORS[doc.status]}`}>
                      {doc.status.replace('_', ' ')}
                    </span>
                  </td>
                  <td className="px-4 py-3 text-gray-500">{doc.review_date ?? '—'}</td>
                </tr>
              ))
            )}
          </tbody>
        </table>
      </div>
    </div>
  );
}
