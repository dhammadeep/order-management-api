<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Auth\Enums\UserRole;
use App\Http\Order\Models\Order;
use App\Http\Product\Models\Product;
use App\Mail\OrderConfirmationMail;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Tests\TestCase;

/**
 * Focused tests for order confirmation queue dispatch and retry configuration.
 *
 * @author Dhammadeep
 */
final class OrderConfirmationTest extends TestCase
{
    use RefreshDatabase;

    public function test_successful_order_queues_one_confirmation_for_saved_email_and_snapshots(): void
    {
        config(['mail.default' => 'log']);
        Mail::fake();

        $customer = User::factory()->create([
            'name' => 'Order Recipient',
            'email' => 'recipient@example.test',
            'role' => UserRole::Customer,
        ]);
        $product = Product::factory()->create([
            'name' => 'Saved Product Name',
            'sku' => 'SNAPSHOT-501',
            'price' => '21.75',
            'stock_quantity' => 3,
        ]);

        $this->placeOrder($customer, $product)->assertCreated();

        Mail::assertQueued(OrderConfirmationMail::class, function (OrderConfirmationMail $mail) use ($customer): bool {
            self::assertSame('recipient@example.test', $mail->to[0]['address']);
            self::assertSame(3, $mail->tries);
            self::assertSame([10, 30], $mail->backoff);
            self::assertInstanceOf(ShouldQueueAfterCommit::class, $mail);
            self::assertSame('redacted_log', $mail->mailer);
            self::assertSame('Saved Product Name', $mail->order->items->first()->product_name);
            self::assertSame('SNAPSHOT-501', $mail->order->items->first()->product_sku);
            self::assertSame('21.75', $mail->order->items->first()->unit_price);
            self::assertSame($customer->id, $mail->order->user_id);

            return true;
        });
        Mail::assertQueuedCount(1);
    }

    public function test_failed_order_does_not_queue_a_confirmation(): void
    {
        Mail::fake();

        $customer = User::factory()->create(['role' => UserRole::Customer]);
        $product = Product::factory()->create(['stock_quantity' => 0]);

        $this->placeOrder($customer, $product)->assertUnprocessable();

        Mail::assertNothingQueued();
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_queue_dispatch_failure_does_not_undo_a_committed_order_or_stock(): void
    {
        $customer = User::factory()->create([
            'email' => 'queue-failure@example.test',
            'role' => UserRole::Customer,
        ]);
        $product = Product::factory()->create(['stock_quantity' => 2]);
        Mail::shouldReceive('to')
            ->once()
            ->with($customer->email)
            ->andThrow(new RuntimeException('queue connection unavailable'));
        Log::spy();

        $response = $this->placeOrder($customer, $product)->assertCreated();

        $orderId = (int) $response->json('data.order.id');
        $this->assertDatabaseHas('orders', ['id' => $orderId, 'user_id' => $customer->id]);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock_quantity' => 1]);
        Log::shouldHaveReceived('error')
            ->once()
            ->with('Order confirmation email could not be queued.', [
                'order_id' => $orderId,
                'exception_type' => RuntimeException::class,
            ]);
    }

    public function test_mailable_uses_three_attempts_and_waits_for_database_commit(): void
    {
        $mail = new OrderConfirmationMail(Order::factory()->make());

        self::assertSame(3, $mail->tries);
        self::assertSame([10, 30], $mail->backoff);
        self::assertInstanceOf(ShouldQueueAfterCommit::class, $mail);
    }

    public function test_local_log_mailer_does_not_log_recipient_or_message_content(): void
    {
        config(['mail.default' => 'log']);
        $logger = \Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('info')
            ->once()
            ->with('Order confirmation handled by the local redacted mail transport.');
        Log::shouldReceive('channel')->once()->with(null)->andReturn($logger);

        Mail::mailer('redacted_log')
            ->to('private-recipient@example.test')
            ->sendNow(new OrderConfirmationMail(Order::factory()->create()));
    }

    private function placeOrder(User $customer, Product $product): TestResponse
    {
        return $this->withToken($customer->createToken('order-confirmation-test')->plainTextToken)
            ->postJson('/api/orders', [
                'items' => [['product_id' => $product->id, 'quantity' => 1]],
            ]);
    }
}
