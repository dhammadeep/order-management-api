<?php

declare(strict_types=1);

namespace App\Http\Report\Repositories;

use App\Http\Order\Enums\OrderStatus;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Executes aggregate database queries for sales reports.
 *
 * @author Dhammadeep
 */
final class ReportRepository
{
    /**
     * @return Collection<int, object>
     */
    public function topProducts(string $fromDate, string $toDate): Collection
    {
        $from = CarbonImmutable::parse($fromDate, config('app.timezone'))->startOfDay();
        $through = CarbonImmutable::parse($toDate, config('app.timezone'))->endOfDay();

        return DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereIn('orders.status', [OrderStatus::Paid->value, OrderStatus::Shipped->value])
            ->where('orders.created_at', '>=', $from)
            ->where('orders.created_at', '<=', $through)
            ->select([
                'order_items.product_id',
                'order_items.product_name',
                'order_items.product_sku',
            ])
            ->selectRaw('SUM(order_items.quantity) AS total_quantity_sold')
            ->groupBy('order_items.product_id', 'order_items.product_name', 'order_items.product_sku')
            ->orderByDesc('total_quantity_sold')
            ->orderBy('order_items.product_id')
            ->limit(5)
            ->get();
    }
}
