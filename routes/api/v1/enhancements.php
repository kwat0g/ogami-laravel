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

Route::middleware(['auth:sanctum'])->group(function () {

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
            $service = app(\App\Domains\AP\Services\EarlyPaymentDiscountService::class);
            return response()->json(['data' => $service->discountSummary()]);
        });
        Route::get('/payment-optimization', function (Request $request): JsonResponse {
            $service = app(\App\Domains\AP\Services\EarlyPaymentDiscountService::class);
            return response()->json(['data' => $service->paymentOptimization($request->integer('days', 7))]);
        });
    });

    // ── Financial Ratios ────────────────────────────────────────────────
    Route::get('accounting/financial-ratios', function (Request $request): JsonResponse {
        $service = app(\App\Domains\Accounting\Services\FinancialRatioService::class);
        return response()->json(['data' => $service->compute($request->integer('year') ?: null)]);
    });

    // ── Production Capacity Planning ────────────────────────────────────
    Route::prefix('production')->group(function () {
        Route::get('/capacity', function (Request $request): JsonResponse {
            $service = app(\App\Domains\Production\Services\CapacityPlanningService::class);
            return response()->json(['data' => $service->utilizationReport($request->input('from'), $request->input('to'))]);
        });
        Route::get('/capacity/check/{productionOrder}', function (\App\Domains\Production\Models\ProductionOrder $productionOrder): JsonResponse {
            $service = app(\App\Domains\Production\Services\CapacityPlanningService::class);
            return response()->json(['data' => $service->checkFeasibility($productionOrder)]);
        });
        Route::get('/mrp/time-phased', function (): JsonResponse {
            $service = app(\App\Domains\Production\Services\MrpService::class);
            return response()->json(['data' => $service->timePhasedExplode()]);
        });
        Route::get('/bom/where-used/{itemId}', function (int $itemId): JsonResponse {
            $service = app(\App\Domains\Production\Services\CostingService::class);
            return response()->json(['data' => $service->whereUsed($itemId)]);
        });
    });

    // ── Inventory Costing ───────────────────────────────────────────────
    Route::get('inventory/valuation-by-method', function (): JsonResponse {
        $service = app(\App\Domains\Inventory\Services\CostingMethodService::class);
        return response()->json(['data' => $service->valuationByMethod()]);
    });

    // ── QC Quarantine ───────────────────────────────────────────────────
    Route::prefix('qc/quarantine')->group(function () {
        Route::get('/', function (): JsonResponse {
            $service = app(\App\Domains\QC\Services\QuarantineService::class);
            return response()->json(['data' => $service->currentQuarantine()]);
        });
        Route::post('/{entryId}/release', function (Request $request, int $entryId): JsonResponse {
            $data = $request->validate(['target_location_id' => 'required|exists:warehouse_locations,id']);
            $service = app(\App\Domains\QC\Services\QuarantineService::class);
            return response()->json(['data' => $service->release($entryId, $data['target_location_id'], $request->user())]);
        })->middleware('throttle:api-action');
        Route::post('/{entryId}/reject', function (Request $request, int $entryId): JsonResponse {
            $data = $request->validate([
                'disposition' => 'required|in:return_to_vendor,scrap',
                'remarks' => 'nullable|string',
            ]);
            $service = app(\App\Domains\QC\Services\QuarantineService::class);
            return response()->json(['data' => $service->reject($entryId, $data['disposition'], $request->user(), $data['remarks'] ?? null)]);
        })->middleware('throttle:api-action');
    });

    // ── ISO Document Acknowledgment ─────────────────────────────────────
    Route::prefix('iso')->group(function () {
        Route::get('/pending-acknowledgments', function (Request $request): JsonResponse {
            $service = app(\App\Domains\ISO\Services\DocumentAcknowledgmentService::class);
            return response()->json(['data' => $service->pendingForUser($request->user())]);
        });
        Route::post('/acknowledge/{distributionId}', function (Request $request, int $distributionId): JsonResponse {
            $service = app(\App\Domains\ISO\Services\DocumentAcknowledgmentService::class);
            return response()->json(['data' => $service->acknowledge($distributionId, $request->user())]);
        })->middleware('throttle:api-action');
        Route::get('/acknowledgment-status/{document}', function (\App\Domains\ISO\Models\ControlledDocument $document): JsonResponse {
            $service = app(\App\Domains\ISO\Services\DocumentAcknowledgmentService::class);
            return response()->json(['data' => $service->acknowledgmentStatus($document)]);
        });
    });

    // ── Loan Payoff ─────────────────────────────────────────────────────
    Route::prefix('loans')->group(function () {
        Route::get('/{loan}/payoff', function (\App\Domains\Loan\Models\Loan $loan): JsonResponse {
            $service = app(\App\Domains\Loan\Services\LoanPayoffService::class);
            return response()->json(['data' => $service->computePayoff($loan)]);
        });
        Route::post('/{loan}/payoff', function (Request $request, \App\Domains\Loan\Models\Loan $loan): JsonResponse {
            $service = app(\App\Domains\Loan\Services\LoanPayoffService::class);
            return response()->json(['data' => $service->executePayoff($loan, $request->user())]);
        })->middleware('throttle:api-action');
        Route::post('/{loan}/restructure', function (Request $request, \App\Domains\Loan\Models\Loan $loan): JsonResponse {
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
            $service = app(\App\Domains\Procurement\Services\BlanketPurchaseOrderService::class);
            return response()->json($service->paginate($request->only(['vendor_id', 'status', 'per_page'])));
        });
        Route::post('/', function (Request $request): JsonResponse {
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
            $service = app(\App\Domains\Procurement\Services\BlanketPurchaseOrderService::class);
            return response()->json(['data' => $service->activate($bpo)]);
        })->middleware('throttle:api-action');
        Route::get('/consolidation/{vendorId}', function (int $vendorId): JsonResponse {
            $service = app(\App\Domains\Procurement\Services\BlanketPurchaseOrderService::class);
            return response()->json(['data' => $service->suggestConsolidation($vendorId)]);
        });
    });

    // ── Fixed Asset Revaluation ─────────────────────────────────────────
    Route::prefix('fixed-assets')->group(function () {
        Route::post('/{fixedAsset}/revalue', function (Request $request, \App\Domains\FixedAssets\Models\FixedAsset $fixedAsset): JsonResponse {
            $data = $request->validate([
                'fair_value_centavos' => 'required|integer|min:0',
                'reason' => 'required|string',
            ]);
            $service = app(\App\Domains\FixedAssets\Services\AssetRevaluationService::class);
            return response()->json(['data' => $service->revalue($fixedAsset, $data['fair_value_centavos'], $request->user(), $data['reason'])]);
        })->middleware('throttle:api-action');
        Route::post('/{fixedAsset}/impairment-test', function (Request $request, \App\Domains\FixedAssets\Models\FixedAsset $fixedAsset): JsonResponse {
            $data = $request->validate(['recoverable_amount_centavos' => 'required|integer|min:0']);
            $service = app(\App\Domains\FixedAssets\Services\AssetRevaluationService::class);
            return response()->json(['data' => $service->impairmentTest($fixedAsset, $data['recoverable_amount_centavos'], $request->user())]);
        })->middleware('throttle:api-action');
    });

    // ── Tax Alphalist ───────────────────────────────────────────────────
    Route::prefix('tax')->group(function () {
        Route::get('/alphalist-2316', function (Request $request): JsonResponse {
            $service = app(\App\Domains\Tax\Services\BirPdfGeneratorService::class);
            return response()->json(['data' => $service->alphalist2316($request->integer('year', (int) now()->format('Y')))]);
        });
        Route::get('/alphalist-2307', function (Request $request): JsonResponse {
            $service = app(\App\Domains\Tax\Services\BirPdfGeneratorService::class);
            return response()->json(['data' => $service->alphalist2307(
                $request->integer('year', (int) now()->format('Y')),
                $request->integer('quarter', (int) ceil(now()->month / 3)),
            )]);
        });
    });

    // ── Delivery POD ────────────────────────────────────────────────────
    Route::post('delivery/receipts/{deliveryReceipt}/pod', function (Request $request, \App\Domains\Delivery\Models\DeliveryReceipt $deliveryReceipt): JsonResponse {
        $data = $request->validate([
            'receiver_name' => 'required|string|max:200',
            'receiver_designation' => 'nullable|string|max:100',
            'signature_base64' => 'nullable|string',
            'photo_base64' => 'nullable|string',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'delivery_notes' => 'nullable|string',
        ]);
        $service = app(\App\Domains\Delivery\Services\ProofOfDeliveryService::class);
        return response()->json(['data' => $service->recordPod($deliveryReceipt, $data, $request->user())]);
    })->middleware('throttle:api-action');

    // ── Leave Conflict Check ────────────────────────────────────────────
    Route::get('leave/requests/{leaveRequest}/conflicts', function (\App\Domains\Leave\Models\LeaveRequest $leaveRequest): JsonResponse {
        $service = app(\App\Domains\Leave\Services\LeaveConflictDetectionService::class);
        return response()->json(['data' => $service->checkConflicts($leaveRequest)]);
    });

    // ── Payroll Final Pay ───────────────────────────────────────────────
    Route::get('payroll/final-pay/{employee}', function (Request $request, \App\Domains\HR\Models\Employee $employee): JsonResponse {
        $service = app(\App\Domains\Payroll\Services\FinalPayService::class);
        return response()->json(['data' => $service->compute($employee, $request->input('last_working_date'))]);
    });
});
