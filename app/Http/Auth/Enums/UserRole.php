<?php

declare(strict_types=1);

namespace App\Http\Auth\Enums;

/**
 * Roles supported by the application.
 *
 * @author Dhammadeep
 */
enum UserRole: string
{
    case Admin = 'admin';
    case Customer = 'customer';
}
