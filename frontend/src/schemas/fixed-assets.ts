import { z } from 'zod'

// ── Fixed Asset Category ────────────────────────────────────────────────────

export const fixedAssetCategorySchema = z.object({
  name: z.string().trim().min(1, 'Category name is required').max(200),
  code: z.string().trim().min(1, 'Category code is required').max(20),
  default_useful_life_months: z.coerce.number().int().positive('Useful life must be positive'),
  default_depreciation_method: z.enum(['straight_line', 'double_declining', 'units_of_production'], {
    required_error: 'Depreciation method is required',
  }),
  asset_account_id: z.coerce.number({ required_error: 'Asset GL account is required' }).positive(),
  depreciation_account_id: z.coerce.number({ required_error: 'Depreciation GL account is required' }).positive(),
  accumulated_depreciation_account_id: z.coerce.number({ required_error: 'Accumulated depreciation GL account is required' }).positive(),
})

export type FixedAssetCategoryFormValues = z.infer<typeof fixedAssetCategorySchema>

// ── Fixed Asset ─────────────────────────────────────────────────────────────
// Note: asset_code is PG-trigger-generated — never set in forms.

export const fixedAssetSchema = z.object({
  name: z.string().trim().min(1, 'Asset name is required').max(200),
  description: z.string().trim().max(1000).optional(),
  category_id: z.coerce.number({ required_error: 'Category is required' }).positive(),
  department_id: z.coerce.number().positive().optional(),
  location: z.string().trim().max(200).optional(),
  serial_number: z.string().trim().max(100).optional(),
  acquisition_date: z.string().trim().min(1, 'Acquisition date is required'),
  acquisition_cost_centavos: z.coerce.number({ required_error: 'Acquisition cost is required' }).int().positive('Cost must be positive'),
  residual_value_centavos: z.coerce.number().int().min(0, 'Residual value cannot be negative').default(0),
  useful_life_months: z.coerce.number().int().positive('Useful life must be positive'),
  depreciation_method: z.enum(['straight_line', 'double_declining', 'units_of_production'], {
    required_error: 'Depreciation method is required',
  }),
  vendor_id: z.coerce.number().positive().optional(),
  purchase_order_id: z.coerce.number().positive().optional(),
  warranty_expiry_date: z.string().trim().optional(),
  remarks: z.string().trim().max(1000).optional(),
})

export type FixedAssetFormValues = z.infer<typeof fixedAssetSchema>

// ── Asset Disposal ──────────────────────────────────────────────────────────

export const assetDisposalSchema = z.object({
  fixed_asset_id: z.coerce.number({ required_error: 'Asset is required' }).positive(),
  disposal_date: z.string().trim().min(1, 'Disposal date is required'),
  disposal_method: z.enum(['sale', 'scrap', 'donation', 'trade_in'], {
    required_error: 'Disposal method is required',
  }),
  sale_amount_centavos: z.coerce.number().int().min(0, 'Sale amount cannot be negative').default(0),
  remarks: z.string().trim().max(1000).optional(),
})

export type AssetDisposalFormValues = z.infer<typeof assetDisposalSchema>
