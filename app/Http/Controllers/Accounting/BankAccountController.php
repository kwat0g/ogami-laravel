<?php

declare(strict_types=1);

namespace App\Http\Controllers\Accounting;

use App\Domains\Accounting\Models\BankAccount;
use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\CreateBankAccountRequest;
use App\Http\Resources\Accounting\BankAccountResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * GL-006 — Bank Account CRUD
 */
final class BankAccountController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', BankAccount::class);

        $accounts = BankAccount::query()
            ->when($request->boolean('include_inactive') === false, fn ($q) => $q->where('is_active', true))
            ->with('chartAccount')
            ->orderBy('name')
            ->get();

        return BankAccountResource::collection($accounts);
    }

    public function store(CreateBankAccountRequest $request): BankAccountResource
    {
        $this->authorize('create', BankAccount::class);

        $account = BankAccount::create($request->validated());

        return new BankAccountResource($account->load('chartAccount'));
    }

    public function show(BankAccount $bankAccount): BankAccountResource
    {
        $this->authorize('view', $bankAccount);

        return new BankAccountResource($bankAccount->load('chartAccount'));
    }

    public function update(CreateBankAccountRequest $request, BankAccount $bankAccount): BankAccountResource
    {
        $this->authorize('update', $bankAccount);

        $bankAccount->update($request->validated());

        return new BankAccountResource($bankAccount->fresh()->load('chartAccount'));
    }

    public function destroy(BankAccount $bankAccount): JsonResponse
    {
        $this->authorize('delete', $bankAccount);

        // Block deletion if there are linked reconciliations
        if ($bankAccount->reconciliations()->exists()) {
            return response()->json([
                'message' => 'Cannot delete a bank account that has associated reconciliations.',
                'error' => ['code' => 'BANK_ACCOUNT_HAS_RECONCILIATIONS'],
            ], 422);
        }

        $bankAccount->delete();

        return response()->json(null, 204);
    }
}
