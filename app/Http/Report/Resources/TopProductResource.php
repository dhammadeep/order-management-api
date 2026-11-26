<?php

declare(strict_types=1);

namespace App\Http\Report\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Public representation of one top-selling product snapshot.
 *
 * @author Dhammadeep
 *
 * @property int $product_id
 * @property string $product_name
 * @property string $product_sku
 * @property int|string $total_quantity_sold
 */
final class TopProductResource extends JsonResource
{
    /**
     * @return array<string, int|string>
     */
    public function toArray(Request $request): array
    {
        return [
            'product_id' => (int) $this->product_id,
            'product_name' => $this->product_name,
            'product_sku' => $this->product_sku,
            'total_quantity_sold' => (int) $this->total_quantity_sold,
        ];
    }
}
