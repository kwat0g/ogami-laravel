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

        return CustomerResource::collection($this->service->list($filters));
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
    public function destroy(Customer $customer): JsonResponse
    {
        $this->authorize('archive', $customer);

        $this->service->archive($customer);

        return response()->json(['message' => 'Customer archived successfully.']);
    }
}
