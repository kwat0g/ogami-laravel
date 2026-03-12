<?php

declare(strict_types=1);

namespace App\Http\Controllers\AP;

use App\Domains\AP\Models\Vendor;
use App\Domains\AP\Services\VendorService;
use App\Http\Controllers\Controller;
use App\Http\Requests\AP\CreateVendorRequest;
use App\Http\Resources\AP\VendorResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;

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

        $query = Vendor::with('ewtRate')
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

        return new VendorResource($vendor->load('ewtRate'));
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
    public function destroy(Vendor $vendor): JsonResponse
    {
        $this->authorize('archive', $vendor);

        $this->service->archive($vendor);

        return response()->json(['message' => 'Vendor archived successfully.']);
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

        // Check if vendor already has a linked portal account
        $existing = User::where('vendor_id', $vendor->id)->first();
        if ($existing) {
            return response()->json([
                'success' => false,
                'error_code' => 'VENDOR_ACCOUNT_EXISTS',
                'message' => "Vendor already has a portal account: {$existing->email}",
            ], 422);
        }

        // Require vendor email to exist
        if (! $vendor->email) {
            return response()->json([
                'success' => false,
                'error_code' => 'VENDOR_EMAIL_MISSING',
                'message' => 'Vendor must have an email address before creating a portal account. Please update the vendor record first.',
            ], 422);
        }

        // Generate temp password
        $tempPassword = 'Vendor' . Str::random(8) . '!';

        $user = User::create([
            'name'                => $vendor->contact_person ?? $vendor->name,
            'email'               => $vendor->email,
            'password'            => $tempPassword,
            'vendor_id'           => $vendor->id,
            'email_verified_at'   => now(),
            'password_changed_at' => now(),
        ]);

        $user->syncRoles(['vendor']);

        return response()->json([
            'success'  => true,
            'message'  => 'Vendor portal account created successfully.',
            'data'     => [
                'user_id'  => $user->id,
                'email'    => $user->email,
                'password' => $tempPassword,
                'role'     => 'vendor',
            ],
        ], 201);
    }
}
