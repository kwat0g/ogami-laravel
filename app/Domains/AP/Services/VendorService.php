<?php

declare(strict_types=1);

namespace App\Domains\AP\Services;

use App\Domains\AP\Models\Vendor;
use App\Shared\Contracts\ServiceContract;
use App\Shared\Exceptions\DomainException;
use Illuminate\Support\Facades\DB;

final class VendorService implements ServiceContract
{
    // ── Create ────────────────────────────────────────────────────────────────

    public function create(array $data, int $userId): Vendor
    {
        $data['created_by'] = $userId;
        $data['is_active'] = $data['is_active'] ?? true;

        return Vendor::create($data);
    }

    // ── Update ────────────────────────────────────────────────────────────────

    public function update(Vendor $vendor, array $data): Vendor
    {
        // Prevent re-activating a vendor that has a TIN conflict
        if (
            isset($data['tin'])
            && filled($data['tin'])
            && $data['tin'] !== $vendor->tin
        ) {
            $conflict = Vendor::where('tin', $data['tin'])
                ->where('id', '!=', $vendor->id)
                ->withTrashed()
                ->exists();

            if ($conflict) {
                throw new DomainException(
                    message: "TIN '{$data['tin']}' is already registered to another vendor.",
                    errorCode: 'VENDOR_TIN_DUPLICATE',
                    httpStatus: 422,
                );
            }
        }

        $vendor->update($data);

        return $vendor->fresh();
    }

    // ── Archive (soft-delete) ─────────────────────────────────────────────────

    /**
     * Deactivate and soft-delete a vendor.
     * Blocked when the vendor has open (non-paid) invoices.
     */
    public function archive(Vendor $vendor): void
    {
        $hasOpenInvoices = $vendor->invoices()
            ->whereNotIn('status', ['paid', 'deleted'])
            ->exists();

        if ($hasOpenInvoices) {
            throw new DomainException(
                message: "Vendor '{$vendor->name}' has open invoices and cannot be archived.",
                errorCode: 'VENDOR_HAS_OPEN_INVOICES',
                httpStatus: 409,
            );
        }

        DB::transaction(function () use ($vendor) {
            $vendor->update(['is_active' => false]);
            $vendor->delete(); // soft-delete via SoftDeletes
        });
    }

    // ── Accreditation ─────────────────────────────────────────────────────────

    /** Mark a vendor as accredited — allows use on Purchase Orders. */
    public function accredit(Vendor $vendor, ?string $notes = null): Vendor
    {
        $vendor->update([
            'accreditation_status' => 'accredited',
            'accreditation_notes'  => $notes,
        ]);

        return $vendor->fresh();
    }

    /** Suspend a vendor — blocks creation of new Purchase Orders. */
    public function suspend(Vendor $vendor, string $reason): Vendor
    {
        $vendor->update([
            'accreditation_status' => 'suspended',
            'accreditation_notes'  => $reason,
        ]);

        return $vendor->fresh();
    }
}
