<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Http\Product\Enums\ProductStatus;
use App\Http\Product\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
final class ProductFactory extends Factory
{
    protected $model = Product::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'sku' => 'SKU-'.strtoupper(fake()->unique()->bothify('????-####')),
            'price' => fake()->randomFloat(2, 1, 2500),
            'stock_quantity' => fake()->numberBetween(0, 500),
            'status' => ProductStatus::Active,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => [
            'status' => ProductStatus::Inactive,
        ]);
    }
}
