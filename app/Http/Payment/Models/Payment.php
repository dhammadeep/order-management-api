<?php

declare(strict_types=1);

namespace App\Http\Payment\Models;

use App\Http\Order\Models\Order;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A provider transaction verified against the order total.
 *
 * @author Dhammadeep
 */
final class Payment extends Model
{
    protected $fillable = [
        'order_id',
        'transaction_id',
        'amount',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
