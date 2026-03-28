<?php

declare(strict_types=1);

namespace App\Domains\Delivery\Services;

use App\Domains\Delivery\Models\DeliveryReceipt;
use App\Models\User;
use App\Shared\Contracts\ServiceContract;
use App\Shared\Exceptions\DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Proof of Delivery Service — Item 42.
 *
 * Captures delivery confirmation evidence:
 *   - Digital signature (base64 PNG)
 *   - Photo of delivered goods
 *   - Receiver name and designation
 *   - GPS coordinates (optional)
 *   - Delivery timestamp
 *
 * Called when driver confirms delivery at customer site.
 * POD data stored on the delivery receipt record.
 */
final class ProofOfDeliveryService implements ServiceContract
{
    /**
     * Record proof of delivery for a delivery receipt.
     *
     * @param  array{
     *     receiver_name: string,
     *     receiver_designation?: string,
     *     signature_base64?: string,
     *     photo_base64?: string,
     *     latitude?: float,
     *     longitude?: float,
     *     delivery_notes?: string,
     * }  $podData
     */
    public function recordPod(DeliveryReceipt $dr, array $podData, User $actor): DeliveryReceipt
    {
        if (! in_array($dr->status, ['in_transit', 'ready_for_pickup'], true)) {
            throw new DomainException(
                "Cannot record POD for delivery in status '{$dr->status}'. Must be in_transit or ready_for_pickup.",
                'DEL_INVALID_POD_STATUS',
                422,
            );
        }

        return DB::transaction(function () use ($dr, $podData, $actor): DeliveryReceipt {
            $signaturePath = null;
            $photoPath = null;

            // Store signature image if provided
            if (! empty($podData['signature_base64'])) {
                $signaturePath = $this->storeBase64File(
                    $podData['signature_base64'],
                    "pod/signatures/dr-{$dr->id}-signature.png",
                );
            }

            // Store photo if provided
            if (! empty($podData['photo_base64'])) {
                $photoPath = $this->storeBase64File(
                    $podData['photo_base64'],
                    "pod/photos/dr-{$dr->id}-photo.jpg",
                );
            }

            $dr->update([
                'status' => 'delivered',
                'pod_receiver_name' => $podData['receiver_name'],
                'pod_receiver_designation' => $podData['receiver_designation'] ?? null,
                'pod_signature_path' => $signaturePath,
                'pod_photo_path' => $photoPath,
                'pod_latitude' => $podData['latitude'] ?? null,
                'pod_longitude' => $podData['longitude'] ?? null,
                'pod_notes' => $podData['delivery_notes'] ?? null,
                'pod_recorded_at' => now(),
                'pod_recorded_by_id' => $actor->id,
                'receipt_date' => now()->toDateString(),
                'received_by_id' => $actor->id,
            ]);

            return $dr->fresh() ?? $dr;
        });
    }

    /**
     * Verify if a delivery receipt has complete POD.
     *
     * @return array{has_pod: bool, receiver_name: string|null, has_signature: bool, has_photo: bool, recorded_at: string|null}
     */
    public function verifyPod(DeliveryReceipt $dr): array
    {
        return [
            'has_pod' => $dr->pod_receiver_name !== null,
            'receiver_name' => $dr->pod_receiver_name,
            'receiver_designation' => $dr->pod_receiver_designation ?? null,
            'has_signature' => $dr->pod_signature_path !== null,
            'has_photo' => $dr->pod_photo_path !== null,
            'has_gps' => $dr->pod_latitude !== null && $dr->pod_longitude !== null,
            'recorded_at' => $dr->pod_recorded_at?->toIso8601String(),
        ];
    }

    /**
     * Store base64-encoded file to storage.
     */
    private function storeBase64File(string $base64Data, string $path): string
    {
        // Remove data URI prefix if present
        $data = preg_replace('/^data:image\/\w+;base64,/', '', $base64Data);
        $decodedData = base64_decode($data, true);

        if ($decodedData === false) {
            throw new DomainException('Invalid base64 file data.', 'DEL_INVALID_FILE', 422);
        }

        Storage::disk('local')->put($path, $decodedData);

        return $path;
    }
}
