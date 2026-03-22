<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Delivery\Models\DeliveryReceipt;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * DeliveryReceiptFactory — provides test defaults for delivery receipts.
 *
 * @extends Factory<DeliveryReceipt>
 */
final class DeliveryReceiptFactory extends Factory
{
    protected $model = DeliveryReceipt::class;

    public function definition(): array
    {
        return [
            'customer_id' => null,
            'vendor_id' => null,
            'delivery_schedule_id' => null,
            'direction' => 'outbound',
            'status' => 'draft',
            'receipt_date' => now(),
            'remarks' => null,
            'received_by_id' => null,
            'vehicle_id' => null,
            'driver_name' => null,
        ];
    }

    /**
     * Set status to confirmed.
     */
    public function confirmed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'confirmed',
        ]);
    }

    /**
     * Set status to delivered.
     */
    public function delivered(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'delivered',
        ]);
    }

    /**
     * Set status to cancelled.
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'cancelled',
        ]);
    }
}
