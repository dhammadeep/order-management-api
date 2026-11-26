<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Auth\Enums\UserRole;
use App\Http\Product\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * MySQL integration check using two concurrent HTTP requests contending for one locked stock row.
 *
 * @author Dhammadeep
 */
final class OrderConcurrencyTest extends TestCase
{
    use DatabaseMigrations;

    public function test_only_one_concurrent_request_can_buy_the_last_item(): void
    {
        $customers = User::factory()->count(2)->create(['role' => UserRole::Customer]);
        $product = Product::factory()->create(['stock_quantity' => 1, 'price' => '12.50']);
        $tokens = $customers->map(fn (User $customer): string => $customer->createToken('concurrency-test')->plainTextToken);

        $connection = DB::connection();
        $connection->beginTransaction();
        Product::query()->whereKey($product->id)->lockForUpdate()->firstOrFail();

        try {
            $promises = $tokens->map(fn (string $token) => Http::timeout(15)
                ->acceptJson()
                ->withToken($token)
                ->async()
                ->post('http://nginx/api/orders', [
                    'items' => [['product_id' => $product->id, 'quantity' => 1]],
                ]));

            usleep(500_000);
            $connection->commit();

            $statuses = $promises->map(fn ($promise): int => $promise->wait()->status())->sort()->values()->all();
        } finally {
            if ($connection->transactionLevel() > 0) {
                $connection->rollBack();
            }
        }

        self::assertSame([201, 422], $statuses);
        self::assertDatabaseCount('orders', 1);
        self::assertDatabaseHas('products', ['id' => $product->id, 'stock_quantity' => 0]);
    }
}
