<?php

declare(strict_types=1);

namespace App\Http\Controllers\Accounting;

use App\Domains\Accounting\Models\BankReconciliation;
use App\Domains\Accounting\Models\BankTransaction;
use App\Domains\Accounting\Models\JournalEntryLine;
use App\Domains\Accounting\Services\BankReconciliationService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\CreateBankReconciliationRequest;
use App\Http\Requests\Accounting\ImportBankStatementRequest;
use App\Http\Requests\Accounting\MatchTransactionRequest;
use App\Http\Resources\Accounting\BankReconciliationResource;
use App\Http\Resources\Accounting\BankTransactionResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * GL-006 — Bank Reconciliation lifecycle
 */
final class BankReconciliationController extends Controller
{
    public function __construct(
        private readonly BankReconciliationService $reconService,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', BankReconciliation::class);

        $reconciliations = BankReconciliation::query()
            ->when($request->input('bank_account_id'), fn ($q, $id) => $q->where('bank_account_id', $id))
            ->when($request->input('status'), fn ($q, $status) => $q->where('status', $status))
            ->with('bankAccount')
            ->orderByDesc('period_to')
            ->paginate(20);

        return BankReconciliationResource::collection($reconciliations);
    }

    public function store(CreateBankReconciliationRequest $request): BankReconciliationResource
    {
        $this->authorize('create', BankReconciliation::class);

        $reconciliation = $this->reconService->create($request->validated());

        return new BankReconciliationResource($reconciliation->load('bankAccount'));
    }

    public function show(BankReconciliation $reconciliation): BankReconciliationResource
    {
        $this->authorize('view', $reconciliation);

        return new BankReconciliationResource(
            $reconciliation->load(['bankAccount', 'transactions'])
        );
    }

    /**
     * Import bank statement lines into an existing draft reconciliation.
     */
    public function importStatement(
        ImportBankStatementRequest $request,
        BankReconciliation $reconciliation,
    ): JsonResponse {
        $this->authorize('create', BankReconciliation::class);

        $count = $this->reconService->importStatement(
            $reconciliation,
            $request->validated('transactions'),
        );

        return response()->json([
            'message' => "{$count} transaction(s) imported successfully.",
            'imported_count' => $count,
            'reconciliation_id' => $reconciliation->id,
        ]);
    }

    /**
     * Match a bank transaction to a GL journal entry line.
     */
    public function matchTransaction(
        MatchTransactionRequest $request,
        BankReconciliation $reconciliation,
    ): BankTransactionResource {
        $this->authorize('create', BankReconciliation::class);

        $bankTx = BankTransaction::findOrFail($request->validated('bank_transaction_id'));
        $jeLine = JournalEntryLine::findOrFail($request->validated('journal_entry_line_id'));

        $updated = $this->reconService->matchTransaction($bankTx, $jeLine);

        return new BankTransactionResource($updated);
    }

    /**
     * Unmatch a bank transaction — returns it to 'unmatched' status.
     */
    public function unmatchTransaction(
        Request $request,
        BankReconciliation $reconciliation,
        BankTransaction $bankTransaction,
    ): BankTransactionResource {
        $this->authorize('create', BankReconciliation::class);

        $updated = $this->reconService->unmatchTransaction($bankTransaction);

        return new BankTransactionResource($updated);
    }

    /**
     * Certify a reconciliation — SoD enforced in service.
     */
    public function certify(
        Request $request,
        BankReconciliation $reconciliation,
    ): BankReconciliationResource {
        $this->authorize('certify', $reconciliation);

        $certified = $this->reconService->certify($reconciliation, $request->user());

        return new BankReconciliationResource($certified->load(['bankAccount', 'transactions']));
    }
}
