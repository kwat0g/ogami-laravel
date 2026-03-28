export interface TicketMessage {
  id: number
  ticket_id: number
  author_id: number
  author?: {
    id: number
    name: string
  }
  body: string
  is_internal: boolean
  created_at: string
}

export interface Ticket {
  id: number
  ulid: string
  ticket_number: string
  subject: string
  description: string
  type: 'complaint' | 'inquiry' | 'request'
  priority: 'low' | 'normal' | 'high' | 'critical'
  status: 'open' | 'in_progress' | 'resolved' | 'closed'
  customer_id: number | null
  customer?: {
    id: number
    name: string
  }
  client_user_id: number | null
  clientUser?: {
    id: number
    name: string
  }
  assigned_to_id: number | null
  assignedTo?: {
    id: number
    name: string
  }
  resolution_note: string | null
  resolved_at: string | null
  messages?: TicketMessage[]
  created_at: string
  updated_at: string
}

export interface TicketFilters {
  status?: string
  priority?: string
  type?: string
  assigned_to_id?: number
  search?: string
  per_page?: number
  page?: number
}

// ── CRM Leads ──────────────────────────────────────────────────────────────

export interface Lead {
  id: number
  ulid: string
  company_name: string
  contact_name: string
  email: string | null
  phone: string | null
  source: 'website' | 'referral' | 'trade_show' | 'cold_call' | 'social_media' | 'other'
  status: 'new' | 'contacted' | 'qualified' | 'converted' | 'disqualified'
  assigned_to_id: number | null
  assignedTo?: { id: number; name: string } | null
  notes: string | null
  converted_customer_id: number | null
  converted_at: string | null
  created_by?: { id: number; name: string }
  created_at: string
  updated_at: string
}

export interface LeadFilters {
  status?: string
  source?: string
  assigned_to_id?: number
  search?: string
  per_page?: number
  page?: number
}

// ── CRM Contacts ───────────────────────────────────────────────────────────

export interface Contact {
  id: number
  ulid: string
  customer_id: number
  customer?: { id: number; name: string }
  first_name: string
  last_name: string
  email: string | null
  phone: string | null
  position: string | null
  role: 'decision_maker' | 'technical' | 'procurement' | 'end_user'
  is_primary: boolean
  notes: string | null
  created_at: string
  updated_at: string
}

// ── CRM Opportunities ──────────────────────────────────────────────────────

export interface Opportunity {
  id: number
  ulid: string
  customer_id: number
  customer?: { id: number; name: string }
  contact_id: number | null
  contact?: Contact | null
  title: string
  expected_value_centavos: number
  probability_pct: number
  expected_close_date: string | null
  stage: 'prospecting' | 'qualification' | 'proposal' | 'negotiation' | 'closed_won' | 'closed_lost'
  assigned_to_id: number | null
  assignedTo?: { id: number; name: string } | null
  notes: string | null
  loss_reason: string | null
  created_by?: { id: number; name: string }
  created_at: string
  updated_at: string
}

export interface OpportunityFilters {
  stage?: string
  customer_id?: number
  assigned_to_id?: number
  per_page?: number
  page?: number
}

export interface PipelineSummary {
  stage: string
  count: number
  total_centavos: number
  weighted_centavos: number
}

// ── CRM Activities ─────────────────────────────────────────────────────────

export interface CrmActivity {
  id: number
  ulid: string
  contactable_type: string
  contactable_id: number
  type: 'call' | 'meeting' | 'email' | 'note' | 'task'
  subject: string
  notes: string | null
  activity_date: string
  next_action_date: string | null
  next_action_description: string | null
  created_by?: { id: number; name: string }
  created_at: string
}
