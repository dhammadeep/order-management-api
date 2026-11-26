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

        $sales = DB::table('order_items as sold_items')
            ->join('orders as sold_orders', 'sold_orders.id', '=', 'sold_items.order_id')
            ->whereIn('sold_orders.status', [OrderStatus::Paid->value, OrderStatus::Shipped->value])
            ->where('sold_orders.created_at', '>=', $from)
            ->where('sold_orders.created_at', '<=', $through)
            ->select('sold_items.product_id')
            ->selectRaw('SUM(sold_items.quantity) AS total_quantity_sold')
            ->selectRaw('MAX(sold_items.id) AS snapshot_item_id')
            ->groupBy('sold_items.product_id');

        return DB::query()
            ->fromSub($sales, 'sales')
            ->join('order_items as latest_snapshot', 'latest_snapshot.id', '=', 'sales.snapshot_item_id')
            ->select([
                'sales.product_id',
                'latest_snapshot.product_name',
                'latest_snapshot.product_sku',
                'sales.total_quantity_sold',
            ])
            ->orderByDesc('sales.total_quantity_sold')
            ->orderBy('sales.product_id')
            ->limit(5)
            ->get();
    }
}
