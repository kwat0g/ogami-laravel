export type DocumentType = 'procedure' | 'work_instruction' | 'form' | 'manual' | 'policy' | 'record';
export type DocumentStatus = 'draft' | 'under_review' | 'approved' | 'obsolete';
export type AuditStatus = 'planned' | 'in_progress' | 'completed' | 'closed';
export type FindingType = 'nonconformity' | 'observation' | 'opportunity';
export type FindingSeverity = 'minor' | 'major';
export type FindingStatus = 'open' | 'in_progress' | 'closed' | 'verified';

export interface ControlledDocument {
  id: number;
  ulid: string;
  doc_code: string;
  title: string;
  category: string | null;
  document_type: DocumentType;
  current_version: string;
  status: DocumentStatus;
  effective_date: string | null;
  review_date: string | null;
  is_active: boolean;
  owner: { id: number; name: string } | null;
  created_at: string;
  updated_at: string;
  deleted_at?: string | null;
}

export interface AuditFinding {
  id: number;
  ulid: string;
  finding_type: FindingType;
  clause_ref: string | null;
  description: string;
  severity: FindingSeverity;
  status: FindingStatus;
  actions_count: number | null;
}

export interface InternalAudit {
  id: number;
  ulid: string;
  audit_reference: string;
  audit_scope: string;
  standard: string;
  audit_date: string;
  status: AuditStatus;
  summary: string | null;
  closed_at: string | null;
  lead_auditor: { id: number; name: string } | null;
  findings?: AuditFinding[];
  created_at: string;
  updated_at: string;
  deleted_at?: string | null;
}

export interface CreateDocumentPayload {
  title: string;
  category?: string;
  document_type: DocumentType;
  owner_id?: number | null;
  current_version?: string;
  effective_date?: string;
  review_date?: string;
}

export interface CreateAuditPayload {
  audit_scope: string;
  standard?: string;
  lead_auditor_id?: number | null;
  audit_date: string;
}

export interface CreateFindingPayload {
  finding_type: FindingType;
  clause_ref?: string;
  description: string;
  severity: FindingSeverity;
}
