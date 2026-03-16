<?php

declare(strict_types=1);

namespace App\Domains\AP\Services;

use App\Domains\AP\Models\Vendor;
use App\Models\User;
use App\Shared\Contracts\ServiceContract;
use App\Shared\Exceptions\DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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
            'accreditation_notes' => $notes,
        ]);

        return $vendor->fresh();
    }

    /** Suspend a vendor — blocks creation of new Purchase Orders and locks any linked portal account. */
    public function suspend(Vendor $vendor, string $reason): Vendor
    {
        return DB::transaction(function () use ($vendor, $reason): Vendor {
            $vendor->update([
                'accreditation_status' => 'suspended',
                'accreditation_notes' => $reason,
            ]);

            // Lock the vendor portal user account so they cannot log in while suspended.
            User::where('vendor_id', $vendor->id)
                ->update(['locked_until' => now()->addYears(10)]);

            return $vendor->fresh();
        });
    }

    /** Provision a vendor portal user account. */
    public function provisionPortalAccount(Vendor $vendor): array
    {
        return DB::transaction(function () use ($vendor): array {
            $existing = User::where('vendor_id', $vendor->id)->first();
            if ($existing) {
                throw new DomainException(
                    message: "Vendor already has a portal account: {$existing->email}",
                    errorCode: 'VENDOR_ACCOUNT_EXISTS',
                    httpStatus: 422,
                );
            }

            if (! $vendor->email) {
                throw new DomainException(
                    message: 'Vendor must have an email address before creating a portal account. Please update the vendor record first.',
                    errorCode: 'VENDOR_EMAIL_MISSING',
                    httpStatus: 422,
                );
            }

            $tempPassword = 'Vendor'.Str::random(8).'!';

            $user = User::create([
                'name' => $vendor->contact_person ?? $vendor->name,
                'email' => $vendor->email,
                'password' => $tempPassword,
                'vendor_id' => $vendor->id,
                'email_verified_at' => now(),
                'password_changed_at' => null, // force change on first login
            ]);

            $user->syncRoles(['vendor']);

            return [
                'user_id' => $user->id,
                'email' => $user->email,
                'password' => $tempPassword,
                'role' => 'vendor',
            ];
        });
    }

    /** Reset the vendor portal account password and unlock the account. */
    public function resetPortalAccountPassword(Vendor $vendor): array
    {
        return DB::transaction(function () use ($vendor): array {
            $user = User::where('vendor_id', $vendor->id)->first();
            if (! $user) {
                throw new DomainException(
                    message: 'Vendor does not have a portal account yet.',
                    errorCode: 'VENDOR_ACCOUNT_MISSING',
                    httpStatus: 422,
                );
            }

            $tempPassword = 'Vendor'.Str::random(8).'!';

            $user->update([
                'password' => $tempPassword,
                'password_changed_at' => null, // force change on next login
                'failed_login_attempts' => 0,
                'locked_until' => null,
            ]);

            return [
                'user_id' => $user->id,
                'email' => $user->email,
                'password' => $tempPassword,
                'role' => 'vendor',
            ];
        });
    }
}
