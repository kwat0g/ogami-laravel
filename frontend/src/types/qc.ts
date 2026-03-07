// QC / QA types

export type InspectionStage = 'iqc' | 'ipqc' | 'oqc'
export type InspectionStatus = 'open' | 'passed' | 'failed' | 'on_hold' | 'voided'
export type NcrSeverity = 'minor' | 'major' | 'critical'
export type NcrStatus = 'open' | 'under_review' | 'capa_issued' | 'closed' | 'voided'
export type CapaType = 'corrective' | 'preventive'
export type CapaStatus = 'open' | 'in_progress' | 'completed' | 'verified'

export interface InspectionTemplateItem {
  id: number
  criterion: string
  method: string | null
  acceptable_range: string | null
  sort_order: number
}

export interface InspectionTemplate {
  id: number
  ulid: string
  name: string
  stage: InspectionStage
  description: string | null
  is_active: boolean
  items?: InspectionTemplateItem[]
  created_at: string
  deleted_at?: string | null
}

export interface InspectionResult {
  id: number
  inspection_template_item_id: number | null
  criterion: string
  actual_value: string | null
  is_conforming: boolean | null
  remarks: string | null
}

export interface Inspection {
  id: number
  ulid: string
  inspection_reference: string
  stage: InspectionStage
  status: InspectionStatus
  qty_inspected: string
  qty_passed: string
  qty_failed: string
  inspection_date: string
  remarks: string | null
  item_master?: { id: number; item_code: string; name: string } | null
  lot_batch?: { id: number; batch_number: string } | null
  inspector?: { id: number; name: string } | null
  template?: InspectionTemplate | null
  results?: InspectionResult[]
  ncrs?: NonConformanceReport[]
  created_at: string
  deleted_at?: string | null
}

export interface CapaAction {
  id: number
  ulid: string
  type: CapaType
  description: string
  due_date: string
  status: CapaStatus
  assigned_to?: { id: number; name: string } | null
  completed_at: string | null
}

export interface NonConformanceReport {
  id: number
  ulid: string
  ncr_reference: string
  title: string
  description: string
  severity: NcrSeverity
  status: NcrStatus
  inspection?: {
    id: number
    inspection_reference: string
    stage: InspectionStage
    item_master?: { id: number; name: string } | null
  } | null
  raised_by?: { id: number; name: string } | null
  capa_actions?: CapaAction[]
  closed_at: string | null
  created_at: string
  deleted_at?: string | null
}

// Payloads
export interface CreateInspectionPayload {
  stage: InspectionStage
  inspection_template_id?: number
  item_master_id?: number
  lot_batch_id?: number
  goods_receipt_id?: number
  production_order_id?: number
  qty_inspected: number
  inspection_date: string
  inspector_id?: number
  remarks?: string
}

export interface RecordResultsPayload {
  qty_passed: number
  qty_failed: number
  results: Array<{
    inspection_template_item_id?: number
    criterion: string
    actual_value?: string
    is_conforming?: boolean
    remarks?: string
  }>
}

export interface CreateNcrPayload {
  inspection_id: number
  title: string
  description: string
  severity: NcrSeverity
}

export interface IssueCapaPayload {
  type: CapaType
  description: string
  due_date: string
  assigned_to_id?: number
}
