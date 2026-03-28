<?php

declare(strict_types=1);

namespace App\Http\Controllers\AP;

use App\Domains\AP\Models\Vendor;
use App\Domains\AP\Services\VendorService;
use App\Http\Controllers\Controller;
use App\Http\Requests\AP\CreateVendorRequest;
use App\Http\Resources\AP\VendorResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class VendorController extends Controller
{
    public function __construct(
        private readonly VendorService $service,
    ) {}

    /**
     * List vendors.
     *   ?is_active=1|0
     *   ?is_ewt_subject=1|0
     *   ?search=name_or_tin
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Vendor::class);

        $query = Vendor::with(['ewtRate', 'portalUser'])
            ->when(
                $request->filled('is_active'),
                fn ($q) => $q->where('is_active', $request->boolean('is_active')),
            )
            ->when(
                $request->filled('is_ewt_subject'),
                fn ($q) => $q->where('is_ewt_subject', $request->boolean('is_ewt_subject')),
            )
            ->when(
                $request->filled('accreditation_status'),
                fn ($q) => $q->where('accreditation_status', $request->input('accreditation_status')),
            )
            ->when(
                $request->filled('search'),

                fn ($q) => $q->where(function ($inner) use ($request) {
                    $inner->where('name', 'like', '%'.$request->input('search').'%')
                        ->orWhere('tin', 'like', '%'.$request->input('search').'%');
                }),
            )
            ->orderBy('name');

        return VendorResource::collection($query->paginate(50));
    }

    public function store(CreateVendorRequest $request): VendorResource
    {
        $this->authorize('create', Vendor::class);

        $vendor = $this->service->create($request->validated(), auth()->id());

        return new VendorResource($vendor->load('ewtRate', 'portalUser'));
    }

    public function show(Vendor $vendor): VendorResource
    {
        $this->authorize('view', $vendor);

        return new VendorResource($vendor->load('ewtRate'));
    }

    public function update(CreateVendorRequest $request, Vendor $vendor): VendorResource
    {
        $this->authorize('update', $vendor);

        $updated = $this->service->update($vendor, $request->validated());

        return new VendorResource($updated->load('ewtRate'));
    }

    /** Soft-delete (archive) vendor — blocked when there are open invoices. */
    public function destroy(Request $request, Vendor $vendor): JsonResponse
    {
        $this->authorize('archive', $vendor);

        $this->service->archive($vendor, $request->user());

        return response()->json(['message' => 'Vendor archived successfully.']);
    }

    /** List archived (soft-deleted) vendors. */
    public function archived(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Vendor::class);

        $records = $this->service->listArchived(
            perPage: $request->integer('per_page', 20),
            search: $request->input('search'),
        );

        return VendorResource::collection($records);
    }

    /** Restore a soft-deleted vendor from the archive. */
    public function restore(Request $request, int $id): VendorResource
    {
        $vendor = $this->service->restore($id, $request->user());

        return new VendorResource($vendor->load('ewtRate'));
    }

    /** Permanently delete a vendor — superadmin only. */
    public function forceDelete(Request $request, int $id): JsonResponse
    {
        abort_unless($request->user()->hasRole('super_admin'), 403, 'Only super admins can permanently delete records.');

        $this->service->forceDelete($id, $request->user());

        return response()->json(['message' => 'Vendor permanently deleted.']);
    }

    /** Mark a vendor as accredited — allows use on Purchase Orders. */
    public function accredit(Request $request, Vendor $vendor): VendorResource
    {
        $this->authorize('accredit', $vendor);

        $notes = $request->string('notes')->toString() ?: null;
        $updated = $this->service->accredit($vendor, $notes);

        return new VendorResource($updated->load('ewtRate'));
    }

    /** Suspend a vendor — blocks new Purchase Orders. */
    public function suspend(Request $request, Vendor $vendor): VendorResource
    {
        $this->authorize('suspend', $vendor);

        $request->validate([
            'reason' => ['required', 'string', 'min:5'],
        ]);

        $updated = $this->service->suspend($vendor, (string) $request->input('reason'));

        return new VendorResource($updated->load('ewtRate'));
    }

    /**
     * Provision a vendor portal user account.
     *
     * Admin-only. Creates a User linked to the vendor via vendor_id,
     * assigns the 'vendor' role, and returns the generated credentials.
     */
    public function provisionPortalAccount(Vendor $vendor): JsonResponse
    {
        $this->authorize('provisionAccount', $vendor);
        $data = $this->service->provisionPortalAccount($vendor);

        return response()->json([
            'success' => true,
            'message' => 'Vendor portal account created successfully.',
            'data' => $data,
        ], 201);
    }

    /** Reset the vendor portal account password. */
    public function resetPortalAccountPassword(Vendor $vendor): JsonResponse
    {
        $this->authorize('provisionAccount', $vendor);

        $data = $this->service->resetPortalAccountPassword($vendor);

        return response()->json([
            'success' => true,
            'message' => 'Vendor portal password reset successfully.',
            'data' => $data,
        ]);
    }

    /** Get vendor's item catalog. */
    public function items(Request $request, Vendor $vendor): JsonResponse
    {
        $this->authorize('view', $vendor);

        $query = $vendor->vendorItems()
            ->when($request->boolean('is_active'), fn ($q) => $q->where('is_active', true))
            ->orderBy('item_name');

        return response()->json([
            'data' => $query->get()->map(fn ($item) => [
                'id' => $item->id,
                'item_code' => $item->item_code,
                'name' => $item->item_name,
                'unit_of_measure' => $item->unit_of_measure,
                'unit_price_centavos' => $item->unit_price,
                'is_active' => (bool) $item->is_active,
            ]),
        ]);
    }

    public function scorecard(Vendor $vendor): JsonResponse
    {
        $this->authorize('view', $vendor);

        return response()->json([
            'data' => $this->service->scorecard($vendor),
        ]);
    }
}
