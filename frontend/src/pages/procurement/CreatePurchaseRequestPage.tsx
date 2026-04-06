import { useNavigate, useParams } from 'react-router-dom'
import { useForm, useFieldArray, Controller } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { toast } from 'sonner'
import { useEffect, useState, useRef } from 'react'
import { Trash2, Plus, AlertTriangle, CheckCircle2, XCircle, Search, RefreshCw } from 'lucide-react'
import { useCreatePurchaseRequest, useUpdatePurchaseRequest, usePurchaseRequest, useCheckBudgetAvailability, useSuggestVendors, type BudgetCheckResult } from '@/hooks/usePurchaseRequests'
import { useDepartments } from '@/hooks/useEmployees'
import { useVendors, useVendorItems } from '@/hooks/useAP'
import { useAuth } from '@/hooks/useAuth'
import { PageHeader } from '@/components/ui/PageHeader'
import { Card, CardHeader, CardBody } from '@/components/ui/Card'
import { firstErrorMessage } from '@/lib/errorHandler'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import { useAutoSave, getDraftTimestamp } from '@/hooks/useAutoSave'
import DraftRestorationBanner from '@/components/ui/DraftRestorationBanner'
import type { PurchaseRequestUrgency } from '@/types/procurement'

// ── Zod schema ────────────────────────────────────────────────────────────────

const itemSchema = z.object({
  vendor_item_id: z.coerce.number().positive('Vendor item is required'),
  item_description: z.string(),
  unit_of_measure: z.string(),
  quantity: z.preprocess(
    (value) => {
      if (value === '' || value === null || value === undefined) return null
      return Number(value)
    },
    z.number().gt(0, 'Must be > 0').nullable().refine((v) => v !== null, {
      message: 'Quantity is required',
    }),
  ),
  estimated_unit_cost: z.coerce.number().gt(0, 'Must be > 0'),
  specifications: z.string().optional(),
})

const schema = z.object({
  vendor_id: z.coerce.number().int().positive('Vendor is required'),
  department_id: z.coerce.number().int().positive('Department is required'),
  urgency: z.enum(['normal', 'urgent', 'critical']).default('normal'),
  justification: z.string().min(5, 'Justification must be at least 5 characters'),
  notes: z.string().optional(),
  items: z.array(itemSchema).min(1, 'At least one line item is required'),
}).superRefine((data, ctx) => {
  const seen = new Set<number>()

  data.items.forEach((item, index) => {
    if (item.vendor_item_id > 0) {
      if (seen.has(item.vendor_item_id)) {
        ctx.addIssue({
          code: z.ZodIssueCode.custom,
          path: ['items', index, 'vendor_item_id'],
          message: 'Duplicate item selected. Increase quantity on the existing row instead.',
        })
      } else {
        seen.add(item.vendor_item_id)
      }
    }
  })
})

type FormValues = z.infer<typeof schema>

// ── Component ─────────────────────────────────────────────────────────────────

export default function CreatePurchaseRequestPage(): React.ReactElement {
  const navigate = useNavigate()
  const { ulid } = useParams<{ ulid?: string }>()
  const isEditMode = !!ulid

  const createPR = useCreatePurchaseRequest()
  const updatePR = useUpdatePurchaseRequest(ulid ?? '')
  const { data: existingPR, isLoading: isLoadingPR } = usePurchaseRequest(ulid ?? null)

  const checkBudget = useCheckBudgetAvailability()
  const { data: deptData } = useDepartments()
  const { data: authData } = useAuth()
  const departments = deptData?.data ?? []
  const currentUser = authData?.user
  const isDeptScoped = currentUser?.roles?.some((r: string) => ['head', 'manager', 'officer'].includes(r))
    && !currentUser?.roles?.some((r: string) => ['super_admin', 'admin', 'executive', 'vice_president'].includes(r))
  const userDeptId = currentUser?.department_id

  const {
    register,
    control,
    handleSubmit,
    watch,
    setValue,
    reset,
    formState: { errors, isSubmitting },
  } = useForm<FormValues>({
    resolver: zodResolver(schema),
    mode: 'onBlur',
    defaultValues: {
      urgency: 'normal',
      vendor_id: 0,
      department_id: isDeptScoped && userDeptId ? userDeptId : 0,
      items: [
        {
          vendor_item_id: 0,
          item_description: '',
          unit_of_measure: '',
          quantity: null,
          estimated_unit_cost: 0,
        },
      ],
    },
  })

  // Populate form when editing an existing returned PR
  useEffect(() => {
    if (isEditMode && existingPR) {
      reset({
        vendor_id: existingPR.vendor_id ?? 0,
        department_id: existingPR.department_id,
        urgency: (existingPR.urgency as PurchaseRequestUrgency) ?? 'normal',
        justification: existingPR.justification,
        notes: existingPR.notes ?? '',
        items: existingPR.items.map((item) => ({
          vendor_item_id: item.vendor_item_id ?? 0,
          item_description: item.item_description,
          unit_of_measure: item.unit_of_measure,
          quantity: item.quantity,
          estimated_unit_cost: item.estimated_unit_cost,
          specifications: item.specifications ?? '',
        })),
      })
    }
  }, [isEditMode, existingPR, reset])

  const { fields, append, remove, replace } = useFieldArray({ control, name: 'items' })

  // ── Auto-save draft (only for new PRs, not edit mode) ─────────────────────
  const formValues = watch()
  const { hasDraft, restore, clear: clearDraft } = useAutoSave(
    'pr-create',
    formValues,
    undefined,
    { enabled: !isEditMode },
  )
  const [hideDraftBanner, setHideDraftBanner] = useState(false)

  const handleRestoreDraft = () => {
    const saved = restore()
    if (saved) {
      reset(saved)
      setHideDraftBanner(true)
      toast.success('Draft restored')
    }
  }

  // Watch form values
  const watchedVendorId = watch('vendor_id')
  const watchedDeptId = watch('department_id')
  const items = watch('items')

  // Budget check state
  const [budgetStatus, setBudgetStatus] = useState<BudgetCheckResult | null>(null)

  // Vendor change confirmation state
  const [pendingVendorId, setPendingVendorId] = useState<number | null>(null)

  const handleVendorChange = (newVendorId: number, fieldOnChange: (val: number) => void): void => {
    const hasItems = items.some((it) => it.vendor_item_id && it.vendor_item_id > 0)
    if (hasItems && newVendorId !== watchedVendorId) {
      setPendingVendorId(newVendorId)
    } else {
      fieldOnChange(newVendorId)
    }
  }

  const confirmVendorChange = (fieldOnChange: (val: number) => void): void => {
    if (pendingVendorId === null) return
    fieldOnChange(pendingVendorId)
    replace([{ vendor_item_id: 0, item_description: '', unit_of_measure: '', quantity: null, estimated_unit_cost: 0, specifications: '' }])
    setBudgetStatus(null)
    setPendingVendorId(null)
  }

  // Vendor-by-item suggest state
  const [itemSearchQuery, setItemSearchQuery] = useState('')
  const itemSearchDebounceRef = useRef<ReturnType<typeof setTimeout> | null>(null)
  const [debouncedItemSearch, setDebouncedItemSearch] = useState('')

  const handleItemSearchInput = (val: string): void => {
    setItemSearchQuery(val)
    if (itemSearchDebounceRef.current) clearTimeout(itemSearchDebounceRef.current)
    itemSearchDebounceRef.current = setTimeout(() => setDebouncedItemSearch(val), 350)
  }

  const { data: vendorSuggestions } = useSuggestVendors(debouncedItemSearch)

  // Check budget when department or items change
  useEffect(() => {
    const checkDeptBudget = async () => {
      // Only check budget if we have valid department and items with cost
      const hasValidItems = items.length > 0 && items.every(
        (item) => item.quantity > 0 && item.estimated_unit_cost > 0
      )
      if (watchedDeptId > 0 && hasValidItems) {
        try {
          const result = await checkBudget.mutateAsync({
            department_id: watchedDeptId,
            items: items.map((item) => ({
              quantity: item.quantity,
              estimated_unit_cost: item.estimated_unit_cost,
            })),
          })
          setBudgetStatus(result)
        } catch {
          // Ignore errors - backend will validate on submit
          setBudgetStatus(null)
        }
      } else {
        setBudgetStatus(null)
      }
    }

    void checkDeptBudget()
  }, [watchedDeptId, items])

  // Fetch vendors and vendor items
  const { data: vendorData } = useVendors({ is_active: true, per_page: 200 })
  const vendors = vendorData?.data ?? []
  const { data: vendorItems, isLoading: loadingItems } = useVendorItems(
    watchedVendorId > 0 ? watchedVendorId : null,
  )

  // Live total computation
  const grandTotal = items.reduce((sum, item) => {
    const qty = Number(item.quantity) || 0
    const cost = Number(item.estimated_unit_cost) || 0
    return sum + qty * cost
  }, 0)

  // Handle vendor item selection — auto-fill description, UoM, price
  const handleVendorItemSelect = (index: number, vendorItemId: number): void => {
    const vendorItem = vendorItems?.find((vi) => vi.id === vendorItemId)
    if (!vendorItem) return

    const duplicateIndex = items.findIndex(
      (it, i) => i !== index && Number(it.vendor_item_id) === vendorItemId,
    )

    // If the item already exists in another row, merge quantity into that row
    // and remove the duplicate row to keep one line per vendor item.
    if (duplicateIndex >= 0) {
      const currentQty = Number(items[index]?.quantity) || 1
      const existingQty = Number(items[duplicateIndex]?.quantity) || 0
      setValue(`items.${duplicateIndex}.quantity`, existingQty + currentQty)

      if (fields.length > 1) {
        remove(index)
      } else {
        setValue('items.0.quantity', existingQty + currentQty)
      }

      toast.info('Item already added. Quantity has been increased on the existing line.')

      return
    }

    setValue(`items.${index}.vendor_item_id`, vendorItem.id)
    setValue(`items.${index}.item_description`, vendorItem.item_name)
    setValue(`items.${index}.unit_of_measure`, vendorItem.unit_of_measure)
    setValue(`items.${index}.estimated_unit_cost`, vendorItem.unit_price)
  }

  const onSubmit = async (values: FormValues): Promise<void> => {
    const payload = {
      vendor_id: values.vendor_id,
      department_id: values.department_id,
      urgency: values.urgency as PurchaseRequestUrgency,
      justification: values.justification,
      notes: values.notes,
      items: values.items.map((item) => ({
        vendor_item_id: item.vendor_item_id,
        item_description: item.item_description,
        unit_of_measure: item.unit_of_measure,
        quantity: item.quantity,
        estimated_unit_cost: item.estimated_unit_cost,
        specifications: item.specifications,
      })),
    }

    try {
      if (isEditMode) {
        await updatePR.mutateAsync(payload)
        toast.success('Purchase Request updated.')
        navigate(`/procurement/purchase-requests/${ulid}`)
      } else {
        const pr = await createPR.mutateAsync(payload)
        clearDraft() // Clear auto-saved draft on successful creation
        toast.success(`Purchase Request ${pr.pr_reference} created as draft.`)
        navigate(`/procurement/purchase-requests/${pr.ulid}`)
      }
    } catch (err) {
      const message = firstErrorMessage(err)
    }
  }

  if (isEditMode && isLoadingPR) return <SkeletonLoader rows={8} />

  return (
    <div className="max-w-4xl mx-auto">
      <PageHeader
        title={isEditMode ? `Edit ${existingPR?.pr_reference ?? 'Purchase Request'}` : 'New Purchase Request'}
        backTo={isEditMode ? `/procurement/purchase-requests/${ulid}` : '/procurement/purchase-requests'}
      />

      {/* Draft restoration banner */}
      {!isEditMode && hasDraft && !hideDraftBanner && (
        <DraftRestorationBanner
          onRestore={handleRestoreDraft}
          onDiscard={() => {
            clearDraft()
            setHideDraftBanner(true)
          }}
          timestamp={getDraftTimestamp('pr-create')}
        />
      )}

      <form onSubmit={handleSubmit(onSubmit)} className="space-y-5">
        {/* Header Section */}
        <Card>
          <CardHeader>Request Details</CardHeader>
          <CardBody>
            <div className="space-y-4">
              <div className="grid grid-cols-3 gap-4">
                {/* Vendor (required — first selection) */}
                <div>
                  <label className="block text-sm font-medium text-neutral-700 mb-1">
                    Vendor <span className="text-red-500">*</span>
                  </label>
                  <Controller
                    control={control}
                    name="vendor_id"
                    render={({ field }) => (
                      <>
                        <select
                          {...field}
                          onChange={(e) => handleVendorChange(Number(e.target.value), field.onChange)}
                          className="w-full text-sm border border-neutral-300 rounded px-3 py-2 bg-white focus:outline-none focus:ring-1 focus:ring-neutral-400"
                        >
                          <option value="">— Select Vendor —</option>
                          {vendors.map((v) => (
                            <option key={v.id} value={v.id}>
                              {v.name}
                            </option>
                          ))}
                        </select>
                        {pendingVendorId !== null && (
                          <div className="mt-2 bg-amber-50 border border-amber-300 rounded p-2.5 text-xs">
                            <div className="flex items-start gap-1.5">
                              <AlertTriangle className="w-3.5 h-3.5 text-amber-500 mt-0.5 shrink-0" />
                              <p className="text-amber-800 font-medium">
                                Changing the vendor will clear all line items and prices.
                              </p>
                            </div>
                            <div className="flex gap-2 mt-2">
                              <button
                                type="button"
                                onClick={() => confirmVendorChange(field.onChange)}
                                className="inline-flex items-center gap-1 px-2.5 py-1 bg-amber-600 hover:bg-amber-700 text-white rounded text-xs font-medium"
                              >
                                <RefreshCw className="w-3 h-3" />
                                Yes, change vendor
                              </button>
                              <button
                                type="button"
                                onClick={() => setPendingVendorId(null)}
                                className="px-2.5 py-1 bg-white hover:bg-neutral-50 text-neutral-700 border border-neutral-300 rounded text-xs font-medium"
                              >
                                Keep current vendor
                              </button>
                            </div>
                          </div>
                        )}
                      </>
                    )}
                  />
                  {errors.vendor_id && (
                    <p className="text-xs text-red-600 mt-1">{errors.vendor_id.message}</p>
                  )}
                  {/* Suggest-vendors reverse lookup */}
                  {(!watchedVendorId || watchedVendorId <= 0) && (
                    <div className="mt-1.5">
                      <div className="relative">
                        <Search className="absolute left-2 top-1.5 w-3.5 h-3.5 text-neutral-400 pointer-events-none" />
                        <input
                          type="text"
                          value={itemSearchQuery}
                          onChange={(e) => handleItemSearchInput(e.target.value)}
                          placeholder="Find vendor by item name…"
                          className="w-full text-xs border border-neutral-200 rounded pl-7 pr-2 py-1.5 focus:outline-none focus:ring-1 focus:ring-neutral-400 bg-neutral-50"
                        />
                      </div>
                      {vendorSuggestions && vendorSuggestions.length > 0 && (
                        <ul className="mt-0.5 border border-neutral-200 rounded bg-white shadow-sm text-xs divide-y divide-neutral-100 max-h-40 overflow-y-auto">
                          {vendorSuggestions.map((s) => (
                            <li key={`${s.vendor_id}-${s.vendor_item_id}`}>
                              <button
                                type="button"
                                className="w-full text-left px-2 py-1.5 hover:bg-neutral-50 flex items-center justify-between gap-2"
                                onClick={() => {
                                  setValue('vendor_id', s.vendor_id)
                                  // Pre-fill the first empty item row with the suggested item
                                  const targetIndex = items.findIndex(
                                    (it) => !it.vendor_item_id || it.vendor_item_id === 0,
                                  )
                                  const idx = targetIndex >= 0 ? targetIndex : 0
                                  setValue(`items.${idx}.vendor_item_id`, s.vendor_item_id)
                                  setValue(`items.${idx}.item_description`, s.item_name)
                                  setValue(`items.${idx}.unit_of_measure`, s.unit_of_measure)
                                  setValue(`items.${idx}.estimated_unit_cost`, s.unit_price)
                                  setItemSearchQuery('')
                                  setDebouncedItemSearch('')
                                }}
                              >
                                <span>
                                  <span className="font-medium text-neutral-800">{s.vendor_name}</span>
                                  <span className="text-neutral-500 ml-1">— {s.item_name} ({s.unit_of_measure})</span>
                                </span>
                                <span className="text-green-700 font-medium whitespace-nowrap">
                                  ₱{s.unit_price.toLocaleString('en-PH', { minimumFractionDigits: 2 })}
                                </span>
                              </button>
                            </li>
                          ))}
                        </ul>
                      )}
                      {debouncedItemSearch.length >= 3 && vendorSuggestions?.length === 0 && (
                        <p className="text-xs text-neutral-400 mt-0.5 px-1">No vendors found for "{debouncedItemSearch}"</p>
                      )}
                    </div>
                  )}
                </div>

                <div>
                  <label className="block text-sm font-medium text-neutral-700 mb-1">
                    Department <span className="text-red-500">*</span>
                  </label>
                  <Controller
                    control={control}
                    name="department_id"
                    render={({ field }) => (
                      <select
                        {...field}
                        disabled={isDeptScoped}
                        onChange={(e) => field.onChange(Number(e.target.value))}
                        className="w-full text-sm border border-neutral-300 rounded px-3 py-2 bg-white focus:outline-none focus:ring-1 focus:ring-neutral-400 disabled:bg-neutral-100 disabled:text-neutral-500"
                      >
                        <option value="">— Select Department —</option>
                        {departments.map((d) => (
                          <option key={d.id} value={d.id}>
                            {d.name}
                          </option>
                        ))}
                      </select>
                    )}
                  />
                  {errors.department_id && (
                    <p className="text-xs text-red-600 mt-1">{errors.department_id.message}</p>
                  )}
                  {isDeptScoped && (
                    <p className="text-xs text-neutral-500 mt-1">
                      Department heads can only create PRs for their own department
                    </p>
                  )}
                </div>

                <div>
                  <label className="block text-sm font-medium text-neutral-700 mb-1">Urgency</label>
                  <Controller
                    control={control}
                    name="urgency"
                    render={({ field }) => (
                      <select
                        {...field}
                        className="w-full text-sm border border-neutral-300 rounded px-3 py-2 bg-white focus:outline-none focus:ring-1 focus:ring-neutral-400"
                      >
                        <option value="normal">Normal</option>
                        <option value="urgent">Urgent</option>
                        <option value="critical">Critical</option>
                      </select>
                    )}
                  />
                </div>
              </div>

              <div>
                <label className="block text-sm font-medium text-neutral-700 mb-1">
                  Justification <span className="text-red-500">*</span>
                </label>
                <textarea
                  {...register('justification')}
                  rows={3}
                  placeholder="Explain why this purchase is needed (min. 5 characters)"
                  className="w-full text-sm border border-neutral-300 rounded px-3 py-2 focus:outline-none focus:ring-1 focus:ring-neutral-400 resize-none"
                />
                {errors.justification && (
                  <p className="text-xs text-red-600 mt-1">{errors.justification.message}</p>
                )}
              </div>

              <div>
                <label className="block text-sm font-medium text-neutral-700 mb-1">
                  Additional Notes
                </label>
                <textarea
                  {...register('notes')}
                  rows={2}
                  placeholder="Optional notes for approvers"
                  className="w-full text-sm border border-neutral-300 rounded px-3 py-2 focus:outline-none focus:ring-1 focus:ring-neutral-400 resize-none"
                />
              </div>
            </div>
          </CardBody>
        </Card>

        {/* Budget Preview */}
        {budgetStatus && budgetStatus.budget > 0 && (
          <Card>
            <CardHeader>Budget Status</CardHeader>
            <CardBody>
              <div className="space-y-3">
                <div className="grid grid-cols-4 gap-4 text-sm">
                  <div>
                    <p className="text-neutral-500">Department Budget</p>
                    <p className="font-medium">{budgetStatus.formatted.budget}</p>
                  </div>
                  <div>
                    <p className="text-neutral-500">YTD Spend</p>
                    <p className="font-medium">{budgetStatus.formatted.ytd_spend}</p>
                  </div>
                  <div>
                    <p className="text-neutral-500">This PR</p>
                    <p className="font-medium">{budgetStatus.formatted.this_pr}</p>
                  </div>
                  <div>
                    <p className="text-neutral-500">Remaining</p>
                    <p className={`font-medium ${budgetStatus.remaining < 0 ? 'text-red-600' : 'text-green-600'}`}>
                      {budgetStatus.formatted.remaining}
                    </p>
                  </div>
                </div>
                {!budgetStatus.available && (
                  <div className="flex items-start gap-2 bg-red-50 border border-red-200 rounded p-3">
                    <XCircle className="w-5 h-5 text-red-500 mt-0.5" />
                    <div>
                      <p className="font-medium text-red-800">Budget Limit Exceeded</p>
                      <p className="text-sm text-red-600">
                        This purchase request exceeds the department&apos;s annual budget.
                        You may still save as draft, but it will be blocked on submit.
                      </p>
                    </div>
                  </div>
                )}
                {budgetStatus.available && budgetStatus.remaining > 0 && (
                  <div className="flex items-start gap-2 bg-green-50 border border-green-200 rounded p-3">
                    <CheckCircle2 className="w-5 h-5 text-green-500 mt-0.5" />
                    <div>
                      <p className="font-medium text-green-800">Within Budget</p>
                      <p className="text-sm text-green-600">
                        This purchase request is within the department&apos;s annual budget.
                      </p>
                    </div>
                  </div>
                )}
              </div>
            </CardBody>
          </Card>
        )}

        {/* Line Items */}
        <Card>
          <CardHeader
            action={
              <button
                type="button"
                onClick={() =>
                  append({
                    vendor_item_id: 0,
                    item_description: '',
                    unit_of_measure: '',
                    quantity: null,
                    estimated_unit_cost: 0,
                  })
                }
                className="inline-flex items-center gap-1.5 text-xs px-3 py-1.5 bg-neutral-900 hover:bg-neutral-800 text-white font-medium rounded"
              >
                <Plus className="w-3.5 h-3.5" />
                Add Item
              </button>
            }
          >
            Line Items
            {watchedVendorId > 0 && vendorItems && (
              <span className="ml-2 text-xs font-normal text-neutral-400">
                ({vendorItems.length} items in vendor catalog)
              </span>
            )}
          </CardHeader>
          <CardBody>
            {!watchedVendorId || watchedVendorId <= 0 ? (
              <div className="flex items-center gap-2 text-amber-600 text-sm py-6 justify-center">
                <AlertTriangle className="w-4 h-4" />
                Please select a vendor first to load their item catalog.
              </div>
            ) : loadingItems ? (
              <div className="text-sm text-neutral-400 py-6 text-center">Loading vendor items…</div>
            ) : (
              <>
                {errors.items?.root && (
                  <p className="text-xs text-red-600 mb-3">{errors.items.root.message}</p>
                )}

                <div className="space-y-3">
                  {fields.map((field, index) => {
                    const qty = Number(items[index]?.quantity) || 0
                    const cost = Number(items[index]?.estimated_unit_cost) || 0
                    const lineTotal = qty * cost

                    return (
                      <div key={field.id} className="bg-neutral-50 rounded p-3 space-y-2">
                        <div className="grid grid-cols-12 gap-2 items-start">
                          {/* Item selection */}
                          <div className="col-span-4">
                            {index === 0 && (
                              <p className="text-xs text-neutral-500 mb-1">Vendor Item *</p>
                            )}
                            <select
                              value={items[index]?.vendor_item_id ?? ''}
                              onChange={(e) =>
                                handleVendorItemSelect(index, Number(e.target.value))
                              }
                              className="w-full text-sm border border-neutral-300 rounded px-2 py-1.5 bg-white focus:outline-none focus:ring-1 focus:ring-neutral-400"
                            >
                              <option value="">— Select Item —</option>
                              {vendorItems?.map((vi) => (
                                <option key={vi.id} value={vi.id}>
                                  {vi.item_code} — {vi.item_name} (₱
                                  {vi.unit_price.toLocaleString('en-PH', {
                                    minimumFractionDigits: 2,
                                  })}
                                  )
                                </option>
                              ))}
                            </select>
                            {errors.items?.[index]?.vendor_item_id && (
                              <p className="text-xs text-red-600 mt-0.5">
                                {errors.items[index]?.vendor_item_id?.message}
                              </p>
                            )}
                          </div>

                          {/* UoM */}
                          <div className="col-span-1">
                            {index === 0 && <p className="text-xs text-neutral-500 mb-1">UoM</p>}
                            <div className="text-sm text-neutral-600 py-1.5 px-2 bg-neutral-100 rounded">
                              {items[index]?.unit_of_measure || '—'}
                            </div>
                          </div>

                          {/* Quantity */}
                          <div className="col-span-2">
                            {index === 0 && <p className="text-xs text-neutral-500 mb-1">Qty *</p>}
                            <input
                              type="number"
                              step="0.001"
                              placeholder="0"
                              {...register(`items.${index}.quantity`)}
                              className="w-full text-sm border border-neutral-300 rounded px-2 py-1.5 focus:outline-none focus:ring-1 focus:ring-neutral-400 [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none"
                            />
                            {errors.items?.[index]?.quantity && (
                              <p className="text-xs text-red-600 mt-0.5">
                                {errors.items[index]?.quantity?.message}
                              </p>
                            )}
                          </div>

                          {/* Unit Cost */}
                          <div className="col-span-2">
                            {index === 0 && (
                              <p className="text-xs text-neutral-500 mb-1">Unit Cost</p>
                            )}
                            <div className="text-sm text-neutral-700 font-medium py-1.5 px-2 bg-neutral-100 rounded">
                              ₱{cost.toLocaleString('en-PH', { minimumFractionDigits: 2 })}
                            </div>
                          </div>

                          {/* Line Total */}
                          <div className="col-span-2">
                            {index === 0 && (
                              <p className="text-xs text-neutral-500 mb-1">Est. Total</p>
                            )}
                            <div className="text-sm text-neutral-700 font-medium py-1.5 px-2">
                              ₱{lineTotal.toLocaleString('en-PH', { minimumFractionDigits: 2 })}
                            </div>
                          </div>

                          {/* Remove */}
                          <div className="col-span-1 flex items-end justify-center pb-1">
                            {index === 0 && (
                              <p className="text-xs text-neutral-500 mb-1 opacity-0">—</p>
                            )}
                            <button
                              type="button"
                              disabled={fields.length === 1}
                              onClick={() => remove(index)}
                              className="p-1 text-neutral-400 hover:text-red-500 disabled:opacity-30 transition-colors"
                            >
                              <Trash2 className="w-4 h-4" />
                            </button>
                          </div>
                        </div>

                        {/* Specifications */}
                        <div>
                          <input
                            {...register(`items.${index}.specifications`)}
                            placeholder="Specifications (optional)"
                            className="w-full text-sm border border-neutral-200 rounded px-2 py-1.5 focus:outline-none focus:ring-1 focus:ring-neutral-400 bg-neutral-50"
                          />
                        </div>
                      </div>
                    )
                  })}
                </div>

                {/* Grand Total */}
                <div className="flex justify-end mt-4 pt-4 border-t border-neutral-200">
                  <div className="text-right">
                    <p className="text-xs text-neutral-500">Total Estimated Cost</p>
                    <p className="text-lg font-semibold text-neutral-900 mt-0.5">
                      ₱{grandTotal.toLocaleString('en-PH', { minimumFractionDigits: 2 })}
                    </p>
                  </div>
                </div>
              </>
            )}
          </CardBody>
        </Card>

        {/* Actions */}
        <div className="flex justify-end gap-3 pt-4">
          <button
            type="button"
            onClick={() => navigate(-1)}
            className="px-5 py-2.5 bg-white text-neutral-700 text-sm font-medium rounded border border-neutral-300 hover:bg-neutral-50"
          >
            Cancel
          </button>
          <button
            type="submit"
            disabled={isSubmitting}
            className="px-6 py-2.5 bg-neutral-900 text-white text-sm font-medium rounded hover:bg-neutral-800 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            {isSubmitting ? 'Saving…' : isEditMode ? 'Save Changes' : 'Save Draft'}
          </button>
        </div>
      </form>
    </div>
  )
}
