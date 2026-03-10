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
