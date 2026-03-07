export type MoldStatus = 'active' | 'under_maintenance' | 'retired';

export interface MoldShotLog {
  id: number;
  shot_count: number;
  log_date: string;
  remarks: string | null;
  operator: { id: number; name: string } | null;
}

export interface MoldMaster {
  id: number;
  ulid: string;
  mold_code: string;
  name: string;
  description: string | null;
  cavity_count: number;
  material: string | null;
  location: string | null;
  max_shots: number | null;
  current_shots: number;
  is_critical: boolean;
  last_maintenance_at: string | null;
  status: MoldStatus;
  is_active: boolean;
  shot_logs?: MoldShotLog[];
  created_at: string;
  updated_at: string;
  deleted_at?: string | null;
}

export interface CreateMoldPayload {
  name: string;
  description?: string;
  cavity_count: number;
  material?: string;
  location?: string;
  max_shots?: number;
  status?: MoldStatus;
  is_active?: boolean;
}

export interface LogShotsPayload {
  shot_count: number;
  production_order_id?: number | null;
  operator_id?: number | null;
  log_date: string;
  remarks?: string;
}
