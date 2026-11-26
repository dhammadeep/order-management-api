<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Order\Enums\OrderStatus;
use App\Http\Order\Models\Order;
use App\Http\Order\Models\OrderItem;
use App\Http\Product\Models\Product;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * MySQL integration test for simultaneous payment and expired-order cancellation.
 *
 * @author Dhammadeep
 */
final class ExpiredCancellationPaymentConcurrencyTest extends TestCase
{
    use DatabaseMigrations;

    public function test_concurrent_payment_and_cancellation_leave_one_consistent_order_outcome(): void
    {
        $product = Product::factory()->create(['stock_quantity' => 3]);
        $order = Order::factory()->create([
            'total_amount' => '1500.00',
            'status' => OrderStatus::Pending,
            'created_at' => now()->subMinutes(31),
            'updated_at' => now()->subMinutes(31),
        ]);
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 2,
        ]);

        $payload = base64_encode(json_encode([
            'transaction_id' => 'TXN-CANCEL-RACE-'.$order->id,
            'order_id' => $order->id,
            'amount' => '1500.00',
            'status' => 'success',
        ], JSON_THROW_ON_ERROR));
        $paymentWorkerCode = <<<PHP
require 'vendor/autoload.php';
\$app = require 'bootstrap/app.php';
\$app->make(\Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();
try {
    \$payload = json_decode(base64_decode('{$payload}'), true, flags: JSON_THROW_ON_ERROR);
    \$app->make(\App\\Http\\Payment\\Services\\PaymentService::class)->processSuccessfulWebhook(\$payload);
    exit(0);
} catch (\App\\Http\\Payment\\Exceptions\\PaymentConflictException) {
    exit(2);
}
PHP;
        $paymentWorker = new Process(['php', '-r', $paymentWorkerCode], base_path());
        $paymentWorker->setTimeout(20);
        $command = new Process(['php', 'artisan', 'orders:cancel-expired']);
        $command->setTimeout(20);

        $connection = DB::connection();
        $connection->beginTransaction();
        Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

        try {
            $command->start();
            $paymentWorker->start();
            usleep(300_000);
            $connection->commit();

            $paymentWorker->wait();
            $command->wait();
        } finally {
            if ($connection->transactionLevel() > 0) {
                $connection->rollBack();
            }
        }

        self::assertTrue($command->isSuccessful(), $command->getErrorOutput().$command->getOutput());
        self::assertContains($paymentWorker->getExitCode(), [0, 2], $paymentWorker->getErrorOutput().$paymentWorker->getOutput());

        $finalOrder = Order::query()->findOrFail($order->id);

        if ($finalOrder->status === OrderStatus::Paid) {
            self::assertSame(0, $paymentWorker->getExitCode());
            self::assertDatabaseHas('payments', ['order_id' => $order->id, 'transaction_id' => 'TXN-CANCEL-RACE-'.$order->id]);
            self::assertDatabaseHas('products', ['id' => $product->id, 'stock_quantity' => 3]);
        } else {
            self::assertSame(OrderStatus::Cancelled, $finalOrder->status);
            self::assertSame(2, $paymentWorker->getExitCode());
            self::assertDatabaseMissing('payments', ['order_id' => $order->id]);
            self::assertDatabaseHas('products', ['id' => $product->id, 'stock_quantity' => 5]);
        }
    }
}
