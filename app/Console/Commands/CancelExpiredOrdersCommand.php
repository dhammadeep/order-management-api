<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Http\Order\Services\ExpiredOrderCancellationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Cancel pending orders that have held stock for more than 30 minutes.
 *
 * @author Dhammadeep
 */
final class CancelExpiredOrdersCommand extends Command
{
    protected $signature = 'orders:cancel-expired';

    protected $description = 'Cancel expired pending orders and restore reserved product stock';

    public function handle(ExpiredOrderCancellationService $cancellations): int
    {
        $counts = $cancellations->cancelExpiredOrders();
        $summary = sprintf(
            'Expired order cancellation complete: scanned=%d, cancelled=%d, skipped=%d, failed=%d.',
            $counts['scanned'],
            $counts['cancelled'],
            $counts['skipped'],
            $counts['failed'],
        );

        $this->info($summary);
        Log::info($summary, $counts);

        return $counts['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
