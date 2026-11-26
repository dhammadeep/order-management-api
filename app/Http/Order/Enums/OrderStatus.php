<?php

declare(strict_types=1);

namespace App\Http\Order\Enums;

/**
 * Lifecycle labels stored for an order.
 *
 * @author Dhammadeep
 */
enum OrderStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Cancelled = 'cancelled';
    case Shipped = 'shipped';
}
