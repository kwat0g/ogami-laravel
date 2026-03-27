<?php

declare(strict_types=1);

use App\Http\Controllers\ISO\ISOController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'module_access:iso'])->group(function (): void {
    // Controlled Documents
    Route::get('/documents', [ISOController::class, 'indexDocuments']);
    Route::post('/documents', [ISOController::class, 'storeDocument']);
    Route::get('/documents/{controlledDocument}', [ISOController::class, 'showDocument']);
    Route::put('/documents/{controlledDocument}', [ISOController::class, 'updateDocument']);
    Route::patch('/documents/{controlledDocument}/submit-for-review', [ISOController::class, 'submitDocumentForReview']);
    Route::patch('/documents/{controlledDocument}/approve', [ISOController::class, 'approveDocument']);
    Route::get('/documents/{controlledDocument}/revisions', [ISOController::class, 'documentRevisions']);

    // Internal Audits
    Route::get('/audits', [ISOController::class, 'indexAudits']);
    Route::post('/audits', [ISOController::class, 'storeAudit']);
    Route::get('/audits/{internalAudit}', [ISOController::class, 'showAudit']);
    Route::middleware('throttle:30,1')->group(function (): void {
        Route::patch('/audits/{internalAudit}/start', [ISOController::class, 'startAudit']);
        Route::patch('/audits/{internalAudit}/complete', [ISOController::class, 'completeAudit']);
    });
    Route::post('/audits/{internalAudit}/findings', [ISOController::class, 'storeFinding']);

    // Audit Findings
    Route::patch('/audit-findings/{auditFinding}/close', [ISOController::class, 'closeFinding']);

    // ── Document Distribution (Phase 3) ───────────────────────────────────
    Route::prefix('distributions')->name('distributions.')->group(function () {
        Route::get('/', function (\Illuminate\Http\Request $request): \Illuminate\Http\JsonResponse {
            $distributions = \App\Domains\ISO\Models\DocumentDistribution::with(['distributedTo', 'distributedBy'])
                ->when($request->input('status'), fn ($q, $v) => $q->where('status', $v))
                ->when($request->input('document_id'), fn ($q, $v) => $q->where('controlled_document_id', $v))
                ->orderByDesc('id')
                ->paginate((int) ($request->input('per_page', 20)));
            return response()->json($distributions);
        })->name('index');

        Route::post('/', function (\Illuminate\Http\Request $request): \Illuminate\Http\JsonResponse {
            $data = $request->validate([
                'controlled_document_id' => ['required', 'integer', 'exists:controlled_documents,id'],
                'user_ids' => ['required', 'array', 'min:1'],
                'user_ids.*' => ['integer', 'exists:users,id'],
            ]);
            $created = [];
            foreach ($data['user_ids'] as $userId) {
                $created[] = \App\Domains\ISO\Models\DocumentDistribution::create([
                    'controlled_document_id' => $data['controlled_document_id'],
                    'distributed_to_id' => $userId,
                    'status' => 'distributed',
                    'distributed_at' => now(),
                    'distributed_by_id' => $request->user()->id,
                ]);
            }
            return response()->json(['data' => $created, 'count' => count($created)], 201);
        })->name('store');

        Route::patch('/{documentDistribution:ulid}/acknowledge', function (\App\Domains\ISO\Models\DocumentDistribution $documentDistribution): \Illuminate\Http\JsonResponse {
            $documentDistribution->update(['status' => 'acknowledged', 'acknowledged_at' => now()]);
            return response()->json(['data' => $documentDistribution->fresh()]);
        })->name('acknowledge');
    });
});
