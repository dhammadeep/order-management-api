<?php

declare(strict_types=1);

namespace App\Http\Order\Services;

use App\Http\Order\Enums\OrderStatus;
use App\Http\Order\Repositories\OrderRepository;
use App\Http\Product\Repositories\ProductRepository;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Cancels expired pending orders and restores their reserved inventory atomically.
 *
 * @author Dhammadeep
 */
final class ExpiredOrderCancellationService
{
    private const CANDIDATE_BATCH_SIZE = 100;

    public function __construct(
        private readonly OrderRepository $orders,
        private readonly ProductRepository $products,
    ) {}

    /**
     * @return array{scanned: int, cancelled: int, skipped: int, failed: int}
     */
    public function cancelExpiredOrders(): array
    {
        $cutoff = CarbonImmutable::now()->subMinutes(30);
        $counts = ['scanned' => 0, 'cancelled' => 0, 'skipped' => 0, 'failed' => 0];
        $afterId = 0;

        while (($orderIds = $this->orders->expiredPendingOrderIds($cutoff, $afterId, self::CANDIDATE_BATCH_SIZE))->isNotEmpty()) {
            foreach ($orderIds as $orderId) {
                $afterId = $orderId;
                $counts['scanned']++;

                try {
                    if ($this->cancelOneIfStillExpired($orderId, $cutoff)) {
                        $counts['cancelled']++;
                    } else {
                        $counts['skipped']++;
                    }
                } catch (Throwable $exception) {
                    $counts['failed']++;
                    Log::error('Expired order cancellation failed.', [
                        'order_id' => $orderId,
                        'exception_type' => $exception::class,
                    ]);
                }
            }
        }

        return $counts;
    }

    private function cancelOneIfStillExpired(int $orderId, CarbonImmutable $cutoff): bool
    {
        return DB::transaction(function () use ($orderId, $cutoff): bool {
            $order = $this->orders->lockById($orderId);

            if ($order === null || $order->status !== OrderStatus::Pending || ! $order->created_at->lt($cutoff)) {
                return false;
            }

            $quantitiesByProductId = $this->orders->productQuantitiesForOrder($order->id);
            $productIds = array_map('intval', array_keys($quantitiesByProductId));
            sort($productIds, SORT_NUMERIC);

            if ($productIds !== []) {
                $lockedProducts = $this->products->lockForOrder($productIds);

                if ($lockedProducts->count() !== count($productIds)) {
                    throw new RuntimeException('One or more products for the expired order no longer exist.');
                }

                $this->products->incrementStockQuantities($quantitiesByProductId);
            }

            $order->status = OrderStatus::Cancelled;
            $order->save();

            return true;
        }, attempts: 3);
    }
}
