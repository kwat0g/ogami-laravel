<?php

declare(strict_types=1);

namespace App\Http\Controllers\Accounting;

use App\Domains\Accounting\Models\ChartOfAccount;
use App\Domains\Accounting\Services\ChartOfAccountService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\CreateAccountRequest;
use App\Http\Resources\Accounting\ChartOfAccountResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class ChartOfAccountController extends Controller
{
    public function __construct(
        private readonly ChartOfAccountService $service,
    ) {}

    /**
     * List all active accounts (flat list, sorted by code).
     * Use ?parent_id=X to filter to direct children.
     * Use ?tree=1 to get full nested tree from root.
     */
    public function index(Request $request): AnonymousResourceCollection|JsonResponse
    {
        $this->authorize('viewAny', ChartOfAccount::class);

        if ($request->boolean('tree')) {
            $tree = $this->service->treeFor(null);

            return response()->json(['data' => $tree]);
        }

        $query = ChartOfAccount::whereNull('deleted_at')
            ->orderBy('code');

        if ($request->filled('parent_id')) {
            $query->where('parent_id', $request->integer('parent_id'));
        }

        if ($request->boolean('include_archived')) {
            $query->withTrashed();
        }

        return ChartOfAccountResource::collection($query->get());
    }

    public function store(CreateAccountRequest $request): ChartOfAccountResource
    {
        $this->authorize('create', ChartOfAccount::class);

        $account = $this->service->createAccount($request->validated());

        return new ChartOfAccountResource($account);
    }

    public function show(ChartOfAccount $account): ChartOfAccountResource
    {
        $this->authorize('view', $account);

        $account->load('children', 'parent');

        return new ChartOfAccountResource($account);
    }

    public function update(CreateAccountRequest $request, ChartOfAccount $account): ChartOfAccountResource
    {
        $this->authorize('update', $account);

        $updated = $this->service->updateAccount($account, $request->validated());

        return new ChartOfAccountResource($updated->load('children', 'parent'));
    }

    /**
     * Archive (soft-delete) an account.
     * COA-003: system accounts blocked in service.
     * COA-005: non-zero balance blocked in service.
     */
    public function destroy(Request $request, ChartOfAccount $account): JsonResponse
    {
        $this->authorize('delete', $account);

        $this->service->archiveAccount($account, $request->user());

        return response()->json(['message' => "Account '{$account->code}' has been archived."]);
    }

    /** List archived (soft-deleted) accounts. */
    public function archived(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', ChartOfAccount::class);

        $records = $this->service->listArchived(
            perPage: $request->integer('per_page', 20),
            search: $request->input('search'),
        );

        return ChartOfAccountResource::collection($records);
    }

    /** Restore a soft-deleted account from the archive. */
    public function restore(Request $request, int $id): ChartOfAccountResource
    {
        $account = $this->service->restoreAccount($id, $request->user());

        return new ChartOfAccountResource($account->load('children', 'parent'));
    }

    /** Permanently delete an account — superadmin only. */
    public function forceDelete(Request $request, int $id): JsonResponse
    {
        abort_unless($request->user()->hasRole('super_admin'), 403, 'Only super admins can permanently delete records.');

        $this->service->forceDeleteAccount($id, $request->user());

        return response()->json(['message' => 'Account permanently deleted.']);
    }
}
