<?php

declare(strict_types=1);

namespace App\Http\Controllers\AR;

use App\Domains\AR\Models\Customer;
use App\Domains\AR\Services\CustomerService;
use App\Http\Controllers\Controller;
use App\Http\Requests\AR\CreateCustomerRequest;
use App\Http\Resources\AR\CustomerResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class CustomerController extends Controller
{
    public function __construct(
        private readonly CustomerService $service,
    ) {}

    /**
     * List customers.
     *   ?search=name_or_tin
     *   ?is_active=1|0
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Customer::class);

        $filters = $request->only(['search', 'is_active', 'per_page']);

        if (isset($filters['is_active'])) {
            $filters['is_active'] = $request->boolean('is_active');
        }

        $filters['with_portal_user'] = true;
        $customers = $this->service->list($filters);

        return CustomerResource::collection($customers);
    }

    public function store(CreateCustomerRequest $request): CustomerResource
    {
        $this->authorize('create', Customer::class);

        $customer = $this->service->create($request->validated(), auth()->id());

        return new CustomerResource($customer);
    }

    public function show(Customer $customer): CustomerResource
    {
        $this->authorize('view', $customer);

        return new CustomerResource($customer);
    }

    public function update(CreateCustomerRequest $request, Customer $customer): CustomerResource
    {
        $this->authorize('update', $customer);

        $updated = $this->service->update($customer, $request->validated());

        return new CustomerResource($updated);
    }

    /** Soft-delete (archive) — blocked when the customer has open invoices. */
    public function destroy(Request $request, Customer $customer): JsonResponse
    {
        $this->authorize('archive', $customer);

        $this->service->archive($customer, $request->user());

        return response()->json(['message' => 'Customer archived successfully.']);
    }

    /** List archived (soft-deleted) customers. */
    public function archived(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Customer::class);

        $records = $this->service->listArchived(
            perPage: $request->integer('per_page', 20),
            search: $request->input('search'),
        );

        return CustomerResource::collection($records);
    }

    /** Restore a soft-deleted customer from the archive. */
    public function restore(Request $request, int $id): CustomerResource
    {
        $this->authorize('create', Customer::class);

        $customer = $this->service->restore($id, $request->user());

        return new CustomerResource($customer);
    }

    /** Permanently delete a customer — superadmin only. */
    public function forceDelete(Request $request, int $id): JsonResponse
    {
        abort_unless($request->user()->hasRole('super_admin'), 403, 'Only super admins can permanently delete records.');

        $this->service->forceDelete($id, $request->user());

        return response()->json(['message' => 'Customer permanently deleted.']);
    }

    /**
     * Provision a client portal user account.
     *
     * Admin-only. Creates a User linked to the customer via client_id,
     * assigns the 'client' role, and returns the generated credentials.
     */
    public function provisionPortalAccount(Customer $customer): JsonResponse
    {
        $this->authorize('provisionAccount', $customer);
        $data = $this->service->provisionPortalAccount($customer);

        return response()->json([
            'success' => true,
            'message' => 'Client portal account created successfully.',
            'data' => $data,
        ], 201);
    }

    /** Reset the client portal account password. */
    public function resetPortalAccountPassword(Customer $customer): JsonResponse
    {
        $this->authorize('provisionAccount', $customer);

        $data = $this->service->resetPortalAccountPassword($customer);

        return response()->json([
            'success' => true,
            'message' => 'Client portal password reset successfully.',
            'data' => $data,
        ]);
    }
}
