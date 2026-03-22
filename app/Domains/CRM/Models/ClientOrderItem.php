<?php

declare(strict_types=1);

namespace App\Domains\CRM\Models;

use App\Domains\Inventory\Models\ItemMaster;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Client Order Item - Individual line items in a client order
 */
class ClientOrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_order_id',
        'item_master_id',
        'item_description',
        'quantity',
        'unit_of_measure',
        'unit_price_centavos',
        'line_total_centavos',
        'negotiated_quantity',
        'negotiated_price_centavos',
        'line_notes',
        'line_order',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'negotiated_quantity' => 'decimal:4',
    ];

    // Relationships
    public function clientOrder(): BelongsTo
    {
        return $this->belongsTo(ClientOrder::class);
    }

    public function itemMaster(): BelongsTo
    {
        return $this->belongsTo(ItemMaster::class);
    }

    // Helper methods
    public function getUnitPrice(): float
    {
        return $this->unit_price_centavos / 100;
    }

    public function getLineTotal(): float
    {
        return $this->line_total_centavos / 100;
    }

    public function getNegotiatedPrice(): ?float
    {
        return $this->negotiated_price_centavos ? $this->negotiated_price_centavos / 100 : null;
    }

    public function hasNegotiation(): bool
    {
        return $this->negotiated_quantity !== null || $this->negotiated_price_centavos !== null;
    }
}
