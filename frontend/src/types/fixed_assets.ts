export interface FixedAssetCategory {
  id: number
  name: string
  code_prefix: string
  default_useful_life_years: number
  default_depreciation_method: 'straight_line' | 'double_declining' | 'units_of_production'
  gl_asset_account_id: number | null
  gl_depreciation_expense_account_id: number | null
  gl_accumulated_depreciation_account_id: number | null
  created_at: string
  updated_at: string
}

export interface FixedAsset {
  id: number
  ulid: string
  category_id: number
  department_id: number | null
  name: string
  description: string | null
  serial_number: string | null
  location: string | null
  status: 'active' | 'disposed' | 'under_maintenance'
  acquisition_date: string
  acquisition_cost_centavos: number
  residual_value_centavos: number
  useful_life_years: number | null
  depreciation_method: 'straight_line' | 'double_declining' | 'units_of_production' | null
  book_value_centavos: number
  purchased_from: string | null
  purchase_invoice_ref: string | null
  created_at: string
  updated_at: string
  category?: FixedAssetCategory
  disposal?: AssetDisposal
}

export interface AssetDisposal {
  id: number
  fixed_asset_id: number
  disposal_date: string
  proceeds_centavos: number | null
  disposal_method: 'sale' | 'scrap' | 'donation' | 'write_off' | null
  notes: string | null
  created_by: number
  created_at: string
}

export interface AssetDepreciationEntry {
  id: number
  fixed_asset_id: number
  fiscal_period_id: number
  depreciation_centavos: number
  accumulated_centavos: number
  book_value_centavos: number
  created_at: string
  fiscal_period?: { id: number; name: string }
}

export interface AssetTransfer {
  id: number
  ulid: string
  fixed_asset_id: number
  from_department_id: number
  to_department_id: number
  transfer_date: string
  status: 'pending' | 'approved' | 'completed' | 'rejected'
  reason: string | null
  requested_by_id: number
  approved_by_id: number | null
  approved_at: string | null
  created_at: string
  updated_at: string
  fixed_asset?: FixedAsset
  from_department?: { id: number; code: string; name: string }
  to_department?: { id: number; code: string; name: string }
  requested_by?: { id: number; name: string }
  approved_by?: { id: number; name: string } | null
}
