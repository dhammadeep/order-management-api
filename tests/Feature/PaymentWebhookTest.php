<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Order\Enums\OrderStatus;
use App\Http\Order\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Feature tests for signed, idempotent provider payment notifications.
 *
 * @author Dhammadeep
 */
final class PaymentWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const WEBHOOK_SECRET = 'phase-six-test-secret';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.payment_webhook.secret' => self::WEBHOOK_SECRET]);
    }

    public function test_valid_raw_body_signature_records_payment_and_marks_order_paid(): void
    {
        $order = $this->pendingOrder();
        $body = $this->payloadBody($order, ['transaction_id' => 'TXN-SUCCESS-1']);

        $this->postSignedBody($body)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.payment.order_id', $order->id)
            ->assertJsonPath('data.payment.transaction_id', 'TXN-SUCCESS-1')
            ->assertJsonPath('data.payment.amount', '1500.00')
            ->assertJsonPath('data.payment.status', 'success')
            ->assertJsonPath('data.duplicate', false);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => OrderStatus::Paid->value,
            'total_amount' => '1500.00',
        ]);
        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'transaction_id' => 'TXN-SUCCESS-1',
            'amount' => '1500.00',
            'status' => 'success',
        ]);
    }

    public function test_invalid_signature_is_rejected_without_database_changes(): void
    {
        $order = $this->pendingOrder();
        $body = $this->payloadBody($order);

        $this->postWebhook($body, str_repeat('a', 64))
            ->assertUnauthorized()
            ->assertJsonPath('success', false);

        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => OrderStatus::Pending->value]);
    }

    public function test_malformed_signature_is_rejected(): void
    {
        $order = $this->pendingOrder();

        $this->postWebhook($this->payloadBody($order), 'sha256=not-a-hex-digest')
            ->assertUnauthorized();

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_missing_signature_is_rejected_without_database_changes(): void
    {
        $order = $this->pendingOrder();

        $this->postWebhook($this->payloadBody($order), null)
            ->assertUnauthorized();

        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => OrderStatus::Pending->value]);
    }

    public function test_malformed_signed_json_returns_validation_error_without_database_changes(): void
    {
        $order = $this->pendingOrder();
        $malformedBody = '{"transaction_id":';

        $this->postSignedBody($malformedBody)
            ->assertUnprocessable()
            ->assertJsonPath('success', false);

        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => OrderStatus::Pending->value]);
    }

    public function test_repeated_delivery_is_idempotent_and_returns_duplicate_flag(): void
    {
        $order = $this->pendingOrder();
        $body = $this->payloadBody($order, ['transaction_id' => 'TXN-DUPLICATE-1']);

        $this->postSignedBody($body)->assertOk()->assertJsonPath('data.duplicate', false);
        $this->postSignedBody($body)
            ->assertOk()
            ->assertJsonPath('data.duplicate', true)
            ->assertJsonPath('message', 'Payment notification was already processed.');

        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => OrderStatus::Paid->value]);
    }

    public function test_transaction_id_cannot_be_reused_for_another_order(): void
    {
        $firstOrder = $this->pendingOrder();
        $secondOrder = $this->pendingOrder();

        $this->postSignedBody($this->payloadBody($firstOrder, ['transaction_id' => 'TXN-CONFLICT-1']))
            ->assertOk();
        $this->postSignedBody($this->payloadBody($secondOrder, ['transaction_id' => 'TXN-CONFLICT-1']))
            ->assertStatus(409)
            ->assertJsonPath('success', false);

        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseHas('orders', ['id' => $firstOrder->id, 'status' => OrderStatus::Paid->value]);
        $this->assertDatabaseHas('orders', ['id' => $secondOrder->id, 'status' => OrderStatus::Pending->value]);
    }

    public function test_payment_amount_must_match_saved_order_total(): void
    {
        $order = $this->pendingOrder();

        $this->postSignedBody($this->payloadBody($order, ['amount' => '1499.99']))
            ->assertUnprocessable()
            ->assertJsonStructure(['errors' => ['amount']]);

        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => OrderStatus::Pending->value]);
    }

    public function test_cancelled_order_cannot_transition_to_paid(): void
    {
        $order = $this->pendingOrder(['status' => OrderStatus::Cancelled]);

        $this->postSignedBody($this->payloadBody($order, ['transaction_id' => 'TXN-CANCELLED-1']))
            ->assertStatus(409);

        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => OrderStatus::Cancelled->value]);
    }

    public function test_shipped_order_cannot_transition_to_paid(): void
    {
        $order = $this->pendingOrder(['status' => OrderStatus::Shipped]);

        $this->postSignedBody($this->payloadBody($order, ['transaction_id' => 'TXN-SHIPPED-1']))
            ->assertStatus(409);

        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => OrderStatus::Shipped->value]);
    }

    public function test_non_success_provider_status_is_rejected(): void
    {
        $order = $this->pendingOrder();

        $this->postSignedBody($this->payloadBody($order, ['status' => 'failed']))
            ->assertUnprocessable()
            ->assertJsonStructure(['errors' => ['status']]);

        $this->assertDatabaseCount('payments', 0);
    }

    private function pendingOrder(array $attributes = []): Order
    {
        return Order::factory()->create(array_merge([
            'total_amount' => '1500.00',
            'status' => OrderStatus::Pending,
        ], $attributes));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function payloadBody(Order $order, array $overrides = []): string
    {
        return json_encode(array_merge([
            'transaction_id' => 'TXN-DEFAULT-1',
            'order_id' => $order->id,
            'amount' => '1500.00',
            'status' => 'success',
        ], $overrides), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
    }

    private function postSignedBody(string $body): TestResponse
    {
        return $this->postWebhook($body, hash_hmac('sha256', $body, self::WEBHOOK_SECRET));
    }

    private function postWebhook(string $body, ?string $signature): TestResponse
    {
        $server = [
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/json',
        ];

        if ($signature !== null) {
            $server['HTTP_X_SIGNATURE'] = $signature;
        }

        return $this->call('POST', '/api/webhooks/payment', [], [], [], $server, $body);
    }
}
