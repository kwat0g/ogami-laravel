export type EquipmentStatus = 'operational' | 'under_maintenance' | 'decommissioned';
export type WorkOrderType = 'corrective' | 'preventive';
export type WorkOrderPriority = 'low' | 'normal' | 'high' | 'critical';
export type WorkOrderStatus = 'open' | 'in_progress' | 'completed' | 'cancelled';

export interface PmSchedule {
  id: number;
  task_name: string;
  frequency_days: number;
  last_done_on: string | null;
  next_due_on: string | null;
}

export interface Equipment {
  id: number;
  ulid: string;
  equipment_code: string;
  name: string;
  category: string | null;
  manufacturer: string | null;
  model_number: string | null;
  serial_number: string | null;
  location: string | null;
  commissioned_on: string | null;
  status: EquipmentStatus;
  is_active: boolean;
  work_orders_count?: number;
  pm_schedules?: PmSchedule[];
  created_at: string;
  updated_at: string;
}

export interface MaintenanceWorkOrder {
  id: number;
  ulid: string;
  mwo_reference: string;
  type: WorkOrderType;
  priority: WorkOrderPriority;
  status: WorkOrderStatus;
  title: string;
  description: string | null;
  scheduled_date: string | null;
  completed_at: string | null;
  completion_notes: string | null;
  equipment?: { id: number; equipment_code: string; name: string };
  assigned_to?: { id: number; name: string } | null;
  reported_by?: { id: number; name: string } | null;
  created_at: string;
  updated_at: string;
}

export interface CreateEquipmentPayload {
  name: string;
  category?: string;
  manufacturer?: string;
  model_number?: string;
  serial_number?: string;
  location?: string;
  commissioned_on?: string;
  status?: EquipmentStatus;
  is_active?: boolean;
}

export interface CreateWorkOrderPayload {
  equipment_id: number;
  type: WorkOrderType;
  priority: WorkOrderPriority;
  title: string;
  description?: string;
  assigned_to_id?: number | null;
  scheduled_date?: string;
}

export interface CompleteWorkOrderPayload {
  completion_notes?: string;
}
