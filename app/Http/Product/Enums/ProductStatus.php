<?php

declare(strict_types=1);

namespace App\Http\Product\Enums;

/**
 * Availability states for products.
 *
 * @author Dhammadeep
 */
enum ProductStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}
