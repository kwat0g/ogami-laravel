<?php

declare(strict_types=1);

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Enhancement Module Routes — /api/v1/
| Routes for enhancement services.
| All routes require Sanctum authentication.
|--------------------------------------------------------------------------
*/

// ── General authenticated routes (dashboard, chain record, audit trail) ───────
Route::middleware(['auth:sanctum'])->group(function () {

    // ── System Health Overview (12-module pulse) ───────────────────────
    Route::get('dashboard/system-health', function (): JsonResponse {
        $modules = [];

        // 1. HR (People)
        $modules[] = [
            'module' => 'HR',
            'icon' => 'users',
            'color' => '#3b82f6',
            'metrics' => [
                ['label' => 'Active Employees', 'value' => (int) DB::table('employees')->whereNull('deleted_at')->where('employment_status', 'active')->count()],
                ['label' => 'On Leave Today', 'value' => (int) DB::table('leave_requests')->where('status', 'approved')->where('date_from', '<=', now()->toDateString())->where('date_to', '>=', now()->toDateString())->count()],
                ['label' => 'Pending Leave', 'value' => (int) DB::table('leave_requests')->whereIn('status', ['pending', 'pending_supervisor', 'pending_hr'])->whereNull('deleted_at')->count()],
            ],
            'href' => '/hr/employees/all',
        ];

        // 2. Payroll
        $lastRun = DB::table('payroll_runs')->whereNull('deleted_at')->orderByDesc('created_at')->first();
        $modules[] = [
            'module' => 'Payroll',
            'icon' => 'banknote',
            'color' => '#10b981',
            'metrics' => [
                ['label' => 'Last Run', 'value' => $lastRun?->status ?? 'No runs'],
                ['label' => 'Total Runs', 'value' => (int) DB::table('payroll_runs')->whereNull('deleted_at')->count()],
                ['label' => 'Pending Approval', 'value' => (int) DB::table('payroll_runs')->whereIn('status', ['SUBMITTED', 'HR_APPROVED', 'ACCTG_APPROVED'])->whereNull('deleted_at')->count()],
            ],
            'href' => '/payroll/runs',
        ];

        // 3. Accounting (GL + AP + AR + Tax)
        $modules[] = [
            'module' => 'Accounting',
            'icon' => 'book-open',
            'color' => '#8b5cf6',
            'metrics' => [
                ['label' => 'Journal Entries', 'value' => (int) DB::table('journal_entries')->whereNull('deleted_at')->count()],
                ['label' => 'Open AP Invoices', 'value' => (int) DB::table('vendor_invoices')->whereNotIn('status', ['paid', 'cancelled', 'voided'])->whereNull('deleted_at')->count()],
                ['label' => 'Open AR Invoices', 'value' => (int) DB::table('customer_invoices')->whereNotIn('status', ['paid', 'cancelled', 'voided'])->whereNull('deleted_at')->count()],
            ],
            'href' => '/accounting/journal-entries',
        ];

        // 4. Procurement
        $modules[] = [
            'module' => 'Procurement',
            'icon' => 'shopping-cart',
            'color' => '#f59e0b',
            'metrics' => [
                ['label' => 'Pending PRs', 'value' => (int) DB::table('purchase_requests')->whereNotIn('status', ['cancelled', 'rejected', 'converted_to_po'])->whereNull('deleted_at')->count()],
                ['label' => 'Active POs', 'value' => (int) DB::table('purchase_orders')->whereNotIn('status', ['closed', 'cancelled'])->whereNull('deleted_at')->count()],
                ['label' => 'Pending GRs', 'value' => (int) DB::table('goods_receipts')->where('status', 'draft')->whereNull('deleted_at')->count()],
            ],
            'href' => '/procurement/purchase-requests',
        ];

        // 5. Inventory
        $modules[] = [
            'module' => 'Inventory',
            'icon' => 'package',
            'color' => '#06b6d4',
            'metrics' => [
                ['label' => 'Item Masters', 'value' => (int) DB::table('item_masters')->whereNull('deleted_at')->count()],
                ['label' => 'Low Stock Items', 'value' => (int) DB::table('stock_balances')->join('item_masters', 'stock_balances.item_id', '=', 'item_masters.id')->whereColumn('stock_balances.quantity_on_hand', '<=', 'item_masters.reorder_point')->where('item_masters.reorder_point', '>', 0)->count()],
                ['label' => 'Pending MRQs', 'value' => (int) DB::table('material_requisitions')->whereNotIn('status', ['fulfilled', 'cancelled', 'rejected'])->whereNull('deleted_at')->count()],
            ],
            'href' => '/inventory/items',
        ];

        // 6. Production
        $modules[] = [
            'module' => 'Production',
            'icon' => 'factory',
            'color' => '#ec4899',
            'metrics' => [
                ['label' => 'Active Orders', 'value' => (int) DB::table('production_orders')->whereIn('status', ['released', 'in_progress'])->whereNull('deleted_at')->count()],
                ['label' => 'Draft Orders', 'value' => (int) DB::table('production_orders')->where('status', 'draft')->whereNull('deleted_at')->count()],
                ['label' => 'BOMs', 'value' => (int) DB::table('bill_of_materials')->where('is_active', true)->whereNull('deleted_at')->count()],
            ],
            'href' => '/production/orders',
        ];

        // 7. QC
        $modules[] = [
            'module' => 'Quality Control',
            'icon' => 'shield-check',
            'color' => '#14b8a6',
            'metrics' => [
                ['label' => 'Pending Inspections', 'value' => (int) DB::table('inspections')->whereIn('status', ['scheduled', 'in_progress'])->whereNull('deleted_at')->count()],
                ['label' => 'Open NCRs', 'value' => (int) DB::table('non_conformance_reports')->whereNotIn('status', ['closed', 'cancelled'])->whereNull('deleted_at')->count()],
                ['label' => 'Pass Rate', 'value' => (function () {
                    $total = DB::table('inspections')->whereIn('status', ['passed', 'failed'])->count();
                    $passed = DB::table('inspections')->where('status', 'passed')->count();
                    return $total > 0 ? round(($passed / $total) * 100) . '%' : 'N/A';
                })()],
            ],
            'href' => '/qc/inspections',
        ];

        // 8. Maintenance
        $modules[] = [
            'module' => 'Maintenance',
            'icon' => 'wrench',
            'color' => '#f97316',
            'metrics' => [
                ['label' => 'Open Work Orders', 'value' => (int) DB::table('maintenance_work_orders')->whereNotIn('status', ['completed', 'cancelled', 'closed'])->whereNull('deleted_at')->count()],
                ['label' => 'Equipment Count', 'value' => (int) DB::table('equipment')->whereNull('deleted_at')->count()],
                ['label' => 'Active Molds', 'value' => (int) DB::table('mold_masters')->where('status', 'active')->whereNull('deleted_at')->count()],
            ],
            'href' => '/maintenance/work-orders',
        ];

        // 9. Delivery
        $modules[] = [
            'module' => 'Delivery',
            'icon' => 'truck',
            'color' => '#6366f1',
            'metrics' => [
                ['label' => 'Pending DRs', 'value' => (int) DB::table('delivery_receipts')->where('status', 'draft')->whereNull('deleted_at')->count()],
                ['label' => 'Ready Schedules', 'value' => (int) DB::table('delivery_schedules')->where('status', 'ready')->whereNull('deleted_at')->count()],
                ['label' => 'Vehicles', 'value' => (int) DB::table('vehicles')->whereNull('deleted_at')->count()],
            ],
            'href' => '/delivery/receipts',
        ];

        // 10. CRM & Sales
        $modules[] = [
            'module' => 'CRM & Sales',
            'icon' => 'handshake',
            'color' => '#e11d48',
            'metrics' => [
                ['label' => 'Active Orders', 'value' => (int) DB::table('client_orders')->whereNotIn('status', ['cancelled', 'delivered', 'completed'])->whereNull('deleted_at')->count()],
                ['label' => 'Open Tickets', 'value' => (int) DB::table('crm_tickets')->whereNotIn('status', ['closed', 'resolved'])->whereNull('deleted_at')->count()],
                ['label' => 'Customers', 'value' => (int) DB::table('customers')->whereNull('deleted_at')->count()],
            ],
            'href' => '/crm/orders',
        ];

        // 11. Budget
        $modules[] = [
            'module' => 'Budget',
            'icon' => 'wallet',
            'color' => '#84cc16',
            'metrics' => [
                ['label' => 'Cost Centers', 'value' => (int) DB::table('cost_centers')->where('is_active', true)->count()],
                ['label' => 'Dept Budgets Set', 'value' => (int) DB::table('departments')->where('annual_budget_centavos', '>', 0)->count()],
            ],
            'href' => '/budget/cost-centers',
        ];

        // 12. Fixed Assets
        $modules[] = [
            'module' => 'Fixed Assets',
            'icon' => 'landmark',
            'color' => '#a855f7',
            'metrics' => [
                ['label' => 'Active Assets', 'value' => (int) DB::table('fixed_assets')->where('status', 'active')->whereNull('deleted_at')->count()],
                ['label' => 'Total Book Value', 'value' => '₱' . number_format((float) DB::table('fixed_assets')->where('status', 'active')->whereNull('deleted_at')->selectRaw('SUM(acquisition_cost_centavos - accumulated_depreciation_centavos) as val')->value('val') / 100, 0)],
            ],
            'href' => '/fixed-assets',
        ];

        return response()->json(['data' => $modules]);
    });

    // ── Chain Record Timeline ────────────────────────────────────────────
    Route::get('chain-record/{type}/{id}', function (string $type, int $id): JsonResponse {
        $service = app(\App\Services\ChainRecordService::class);
        $chain = $service->trace($type, $id);

        return response()->json(['data' => $chain]);
    })->where('type', '[a-z_]+')->where('id', '[0-9]+');

    // ── Audit Trail (Status Timeline) ────────────────────────────────────
    Route::get('audit-trail/{type}/{id}', function (string $type, int $id): JsonResponse {
        // Map short type names to auditable_type (Eloquent class names)
        $typeMap = [
            'purchase_request' => 'App\\Domains\\Procurement\\Models\\PurchaseRequest',
            'purchase_order' => 'App\\Domains\\Procurement\\Models\\PurchaseOrder',
            'goods_receipt' => 'App\\Domains\\Procurement\\Models\\GoodsReceipt',
            'production_order' => 'App\\Domains\\Production\\Models\\ProductionOrder',
            'delivery_schedule' => 'App\\Domains\\Production\\Models\\DeliverySchedule',
            'delivery_receipt' => 'App\\Domains\\Delivery\\Models\\DeliveryReceipt',
            'vendor_invoice' => 'App\\Domains\\AP\\Models\\VendorInvoice',
            'customer_invoice' => 'App\\Domains\\AR\\Models\\CustomerInvoice',
            'client_order' => 'App\\Domains\\CRM\\Models\\ClientOrder',
            'payroll_run' => 'App\\Domains\\Payroll\\Models\\PayrollRun',
            'employee' => 'App\\Domains\\HR\\Models\\Employee',
            'loan' => 'App\\Domains\\Loan\\Models\\Loan',
            'leave_request' => 'App\\Domains\\Leave\\Models\\LeaveRequest',
            'inspection' => 'App\\Domains\\QC\\Models\\Inspection',
            'material_requisition' => 'App\\Domains\\Inventory\\Models\\MaterialRequisition',
        ];

        $auditableType = $typeMap[$type] ?? null;
        if (! $auditableType) {
            return response()->json(['data' => []]);
        }

        $audits = \OwenIt\Auditing\Models\Audit::query()
            ->where('auditable_type', $auditableType)
            ->where('auditable_id', $id)
            ->orderBy('created_at', 'asc')
            ->limit(50)
            ->get()
            ->map(fn ($audit) => [
                'id' => $audit->id,
                'event' => $audit->event,
                'old_values' => $audit->old_values ?? [],
                'new_values' => $audit->new_values ?? [],
                'user_id' => $audit->user_id,
                'user_name' => $audit->user?->name ?? null,
                'created_at' => $audit->created_at?->toIso8601String(),
            ]);

        return response()->json(['data' => $audits]);
    })->where('type', '[a-z_]+')->where('id', '[0-9]+');

    // ── Material Consumption Report (Production Order variance) ─────────
    Route::get('production/orders/{order}/material-consumption', function (\App\Domains\Production\Models\ProductionOrder $order): JsonResponse {
        abort_unless(auth()->user()?->hasPermissionTo('production.orders.view'), 403, 'Unauthorized');
        $bom = $order->bom_id ? \App\Domains\Production\Models\BillOfMaterials::with('components.componentItem')->find($order->bom_id) : null;

        // BOM expected quantities (per unit * qty required)
        $bomExpected = [];
        if ($bom) {
            foreach ($bom->components as $comp) {
                $bomExpected[$comp->component_item_id] = [
                    'item_id' => $comp->component_item_id,
                    'item_name' => $comp->componentItem?->name ?? "Item #{$comp->component_item_id}",
                    'item_code' => $comp->componentItem?->item_code ?? '',
                    'expected_qty' => round((float) $comp->quantity_per_unit * (float) $order->qty_required, 4),
                    'actual_qty' => 0.0,
                    'variance' => 0.0,
                    'variance_pct' => 0.0,
                ];
            }
        }

        // Actual consumed quantities from fulfilled MRQs
        $mrqs = \App\Domains\Inventory\Models\MaterialRequisition::where('production_order_id', $order->id)
            ->whereIn('status', ['fulfilled', 'issued'])
            ->with('items')
            ->get();

        foreach ($mrqs as $mrq) {
            foreach ($mrq->items as $item) {
                $itemId = $item->item_id;
                if (isset($bomExpected[$itemId])) {
                    $bomExpected[$itemId]['actual_qty'] += (float) $item->qty_requested;
                } else {
                    // Unplanned material (not in BOM)
                    $bomExpected[$itemId] = [
                        'item_id' => $itemId,
                        'item_name' => $item->itemMaster?->name ?? "Item #{$itemId}",
                        'item_code' => $item->itemMaster?->item_code ?? '',
                        'expected_qty' => 0.0,
                        'actual_qty' => (float) $item->qty_requested,
                        'variance' => 0.0,
                        'variance_pct' => 0.0,
                    ];
                }
            }
        }

        // Calculate variances
        $report = collect($bomExpected)->map(function (array $row): array {
            $row['variance'] = round($row['actual_qty'] - $row['expected_qty'], 4);
            $row['variance_pct'] = $row['expected_qty'] > 0
                ? round(($row['variance'] / $row['expected_qty']) * 100, 2)
                : ($row['actual_qty'] > 0 ? 100.0 : 0.0);

            return $row;
        })->values()->all();

        return response()->json([
            'data' => [
                'production_order_id' => $order->id,
                'po_reference' => $order->po_reference,
                'qty_required' => (float) $order->qty_required,
                'qty_produced' => (float) $order->qty_produced,
                'bom_version' => $bom?->version ?? null,
                'materials' => $report,
                'total_bom_items' => $bom?->components->count() ?? 0,
                'total_mrq_fulfilled' => $mrqs->count(),
            ],
        ]);
    })->where('order', '[0-9]+');

    // ── AP Early Payment Discounts ──────────────────────────────────────
    Route::prefix('ap')->group(function () {
        Route::get('/discount-summary', function (): JsonResponse {
            abort_unless(auth()->user()?->hasPermissionTo('vendor_invoices.view'), 403, 'Unauthorized');
            $service = app(\App\Domains\AP\Services\EarlyPaymentDiscountService::class);
            return response()->json(['data' => $service->discountSummary()]);
        });
        Route::get('/payment-optimization', function (Request $request): JsonResponse {
            abort_unless(auth()->user()?->hasPermissionTo('vendor_invoices.view'), 403, 'Unauthorized');
            $service = app(\App\Domains\AP\Services\EarlyPaymentDiscountService::class);
            return response()->json(['data' => $service->paymentOptimization($request->integer('days', 7))]);
        });
    });

    // ── Financial Ratios ────────────────────────────────────────────────
    Route::get('accounting/financial-ratios', function (Request $request): JsonResponse {
        abort_unless(auth()->user()?->hasPermissionTo('reports.financial_statements'), 403, 'Unauthorized');
        $service = app(\App\Domains\Accounting\Services\FinancialRatioService::class);
        return response()->json(['data' => $service->compute($request->integer('year') ?: null)]);
    });

    // ── Production Capacity Planning ────────────────────────────────────
    // TODO: Phase 2 — CapacityPlanningService and MrpService not yet implemented
    // Route::prefix('production')->group(function () {
    //     Route::get('/capacity', function (Request $request): JsonResponse { ... });
    //     Route::get('/capacity/check/{productionOrder}', function (...): JsonResponse { ... });
    //     Route::get('/mrp/time-phased', function (): JsonResponse { ... });
    // });
    Route::prefix('production')->group(function () {
        Route::get('/bom/where-used/{itemId}', function (int $itemId): JsonResponse {
            abort_unless(auth()->user()?->hasPermissionTo('production.bom.view'), 403, 'Unauthorized');
            $service = app(\App\Domains\Production\Services\CostingService::class);
            return response()->json(['data' => $service->whereUsed($itemId)]);
        });
    });

    // ── Inventory Costing ───────────────────────────────────────────────
    Route::get('inventory/valuation-by-method', function (): JsonResponse {
        abort_unless(auth()->user()?->hasPermissionTo('inventory.stock.view'), 403, 'Unauthorized');
        $service = app(\App\Domains\Inventory\Services\CostingMethodService::class);
        return response()->json(['data' => $service->valuationByMethod()]);
    });

    // ── QC Quarantine ───────────────────────────────────────────────────
    Route::prefix('qc/quarantine')->group(function () {
        Route::get('/', function (): JsonResponse {
            abort_unless(auth()->user()?->hasPermissionTo('qc.inspections.view'), 403, 'Unauthorized');
            $service = app(\App\Domains\QC\Services\QuarantineService::class);
            return response()->json(['data' => $service->currentQuarantine()]);
        });
        Route::post('/{entryId}/release', function (Request $request, int $entryId): JsonResponse {
            abort_unless(auth()->user()?->hasPermissionTo('qc.manage'), 403, 'Unauthorized');
            $data = $request->validate(['target_location_id' => 'required|exists:warehouse_locations,id']);
            $service = app(\App\Domains\QC\Services\QuarantineService::class);
            return response()->json(['data' => $service->release($entryId, $data['target_location_id'], $request->user())]);
        })->middleware('throttle:api-action');
        Route::post('/{entryId}/reject', function (Request $request, int $entryId): JsonResponse {
            abort_unless(auth()->user()?->hasPermissionTo('qc.manage'), 403, 'Unauthorized');
            $data = $request->validate([
                'disposition' => 'required|in:return_to_vendor,scrap',
                'remarks' => 'nullable|string',
            ]);
            $service = app(\App\Domains\QC\Services\QuarantineService::class);
            return response()->json(['data' => $service->reject($entryId, $data['disposition'], $request->user(), $data['remarks'] ?? null)]);
        })->middleware('throttle:api-action');
    });

    // ── ISO Document Acknowledgment ─────────────────────────────────────
    // TODO: Phase 2 — ISO domain layer (Models, Services) not yet implemented
    // Route::prefix('iso')->group(function () {
    //     Route::get('/pending-acknowledgments', ...);
    //     Route::post('/acknowledge/{distributionId}', ...);
    //     Route::get('/acknowledgment-status/{document}', ...);
    // });

    // ── Loan Payoff ─────────────────────────────────────────────────────
    Route::prefix('loans')->group(function () {
        Route::get('/{loan}/payoff', function (\App\Domains\Loan\Models\Loan $loan): JsonResponse {
            abort_unless(auth()->user()?->hasPermissionTo('loans.view_own') || auth()->user()?->hasPermissionTo('loans.hr_approve'), 403, 'Unauthorized');
            $service = app(\App\Domains\Loan\Services\LoanPayoffService::class);
            return response()->json(['data' => $service->computePayoff($loan)]);
        });
        Route::post('/{loan}/payoff', function (Request $request, \App\Domains\Loan\Models\Loan $loan): JsonResponse {
            abort_unless(auth()->user()?->hasPermissionTo('loans.hr_approve'), 403, 'Unauthorized');
            $service = app(\App\Domains\Loan\Services\LoanPayoffService::class);
            return response()->json(['data' => $service->executePayoff($loan, $request->user())]);
        })->middleware('throttle:api-action');
        Route::post('/{loan}/restructure', function (Request $request, \App\Domains\Loan\Models\Loan $loan): JsonResponse {
            abort_unless(auth()->user()?->hasPermissionTo('loans.hr_approve'), 403, 'Unauthorized');
            $data = $request->validate([
                'new_term_months' => 'required|integer|min:1|max:60',
                'new_annual_rate_pct' => 'nullable|numeric|min:0|max:100',
                'reason' => 'required|string',
            ]);
            $service = app(\App\Domains\Loan\Services\LoanPayoffService::class);
            return response()->json(['data' => $service->restructure($loan, $data, $request->user())]);
        })->middleware('throttle:api-action');
    });

    // ── Blanket Purchase Orders ──────────────────────────────────────────
    Route::prefix('procurement/blanket-pos')->group(function () {
        Route::get('/', function (Request $request): JsonResponse {
            abort_unless(auth()->user()?->hasPermissionTo('procurement.purchase-order.view'), 403, 'Unauthorized');
            $service = app(\App\Domains\Procurement\Services\BlanketPurchaseOrderService::class);
            return response()->json($service->paginate($request->only(['vendor_id', 'status', 'per_page'])));
        });
        Route::post('/', function (Request $request): JsonResponse {
            abort_unless(auth()->user()?->hasPermissionTo('procurement.purchase-order.manage'), 403, 'Unauthorized');
            $data = $request->validate([
                'vendor_id' => 'required|exists:vendors,id',
                'start_date' => 'required|date',
                'end_date' => 'required|date|after:start_date',
                'committed_amount_centavos' => 'required|integer|min:1',
                'terms' => 'nullable|string',
            ]);
            $service = app(\App\Domains\Procurement\Services\BlanketPurchaseOrderService::class);
            return response()->json(['data' => $service->store($data, $request->user())], 201);
        });
        Route::patch('/{bpo}/activate', function (\App\Domains\Procurement\Models\BlanketPurchaseOrder $bpo): JsonResponse {
            abort_unless(auth()->user()?->hasPermissionTo('procurement.purchase-order.manage'), 403, 'Unauthorized');
            $service = app(\App\Domains\Procurement\Services\BlanketPurchaseOrderService::class);
            return response()->json(['data' => $service->activate($bpo)]);
        })->middleware('throttle:api-action');
        Route::get('/consolidation/{vendorId}', function (int $vendorId): JsonResponse {
            abort_unless(auth()->user()?->hasPermissionTo('procurement.purchase-order.view'), 403, 'Unauthorized');
            $service = app(\App\Domains\Procurement\Services\BlanketPurchaseOrderService::class);
            return response()->json(['data' => $service->suggestConsolidation($vendorId)]);
        });
    });

    // ── Fixed Asset Revaluation ─────────────────────────────────────────
    // TODO: Phase 4 — AssetRevaluationService not yet implemented
    // Route::prefix('fixed-assets')->group(function () {
    //     Route::post('/{fixedAsset}/revalue', ...);
    //     Route::post('/{fixedAsset}/impairment-test', ...);
    // });

    // ── Tax Alphalist ───────────────────────────────────────────────────
    Route::prefix('tax')->group(function () {
        Route::get('/alphalist-2316', function (Request $request): JsonResponse {
            abort_unless(auth()->user()?->hasPermissionTo('reports.vat'), 403, 'Unauthorized');
            $service = app(\App\Domains\Tax\Services\BirPdfGeneratorService::class);
            return response()->json(['data' => $service->alphalist2316($request->integer('year', (int) now()->format('Y')))]);
        });
        Route::get('/alphalist-2307', function (Request $request): JsonResponse {
            abort_unless(auth()->user()?->hasPermissionTo('reports.vat'), 403, 'Unauthorized');
            $service = app(\App\Domains\Tax\Services\BirPdfGeneratorService::class);
            return response()->json(['data' => $service->alphalist2307(
                $request->integer('year', (int) now()->format('Y')),
                $request->integer('quarter', (int) ceil(now()->month / 3)),
            )]);
        });
    });

    // ── Delivery POD ────────────────────────────────────────────────────
    Route::post('delivery/receipts/{deliveryReceipt}/pod', function (Request $request, \App\Domains\Delivery\Models\DeliveryReceipt $deliveryReceipt): JsonResponse {
        abort_unless(auth()->user()?->hasPermissionTo('delivery.manage'), 403, 'Unauthorized');
        $data = $request->validate([
            'receiver_name' => 'required|string|max:200',
            'signature_base64' => 'nullable|string',
            'photo_base64' => 'nullable|string',
            'photos_base64' => 'nullable|array|max:3',
            'photos_base64.*' => 'string',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'delivery_notes' => 'nullable|string',
        ]);

        if (empty($data['photos_base64']) && empty($data['photo_base64'])) {
            return response()->json([
                'message' => 'At least one POD photo is required.',
                'errors' => ['photos_base64' => ['At least one POD photo is required.']],
            ], 422);
        }

        $service = app(\App\Domains\Delivery\Services\ProofOfDeliveryService::class);
        return response()->json(['data' => $service->recordPod($deliveryReceipt, $data, $request->user())]);
    })->middleware('throttle:api-action');

    Route::get('delivery/receipts/{deliveryReceipt}/pod-photos/{photoIndex}', function (
        Request $request,
        \App\Domains\Delivery\Models\DeliveryReceipt $deliveryReceipt,
        int $photoIndex
    ) {
        $user = $request->user();
        abort_if($user === null, 401, 'Unauthenticated');

        $canView = $user->hasPermissionTo('delivery.manage')
            || ($user->client_id !== null && $deliveryReceipt->customer_id === $user->client_id);
        abort_unless($canView, 403, 'Unauthorized');

        $paths = is_array($deliveryReceipt->pod_photo_paths) ? $deliveryReceipt->pod_photo_paths : [];
        abort_unless(isset($paths[$photoIndex]), 404, 'POD photo not found');

        $path = $paths[$photoIndex];
        abort_unless(is_string($path) && $path !== '', 404, 'POD photo not found');
        abort_unless(\Illuminate\Support\Facades\Storage::disk('local')->exists($path), 404, 'POD photo not found');

        return response()->file(\Illuminate\Support\Facades\Storage::disk('local')->path($path));
    })->middleware('throttle:api-action');

    // ── Leave Conflict Check ────────────────────────────────────────────
    Route::get('leave/requests/{leaveRequest}/conflicts', function (\App\Domains\Leave\Models\LeaveRequest $leaveRequest): JsonResponse {
        abort_unless(auth()->user()?->hasPermissionTo('leaves.view_own') || auth()->user()?->hasPermissionTo('leaves.view_team'), 403, 'Unauthorized');
        $service = app(\App\Domains\Leave\Services\LeaveConflictDetectionService::class);
        return response()->json(['data' => $service->checkConflicts($leaveRequest)]);
    });

    // ── Payroll Final Pay ───────────────────────────────────────────────
    Route::get('payroll/final-pay/{employee}', function (Request $request, \App\Domains\HR\Models\Employee $employee): JsonResponse {
        abort_unless(auth()->user()?->hasPermissionTo('payroll.view_runs'), 403, 'Unauthorized');
        $service = app(\App\Domains\Payroll\Services\FinalPayService::class);
        return response()->json(['data' => $service->compute($employee, $request->input('last_working_date'))]);
    });
});
