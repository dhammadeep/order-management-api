<?php

declare(strict_types=1);

namespace App\Http\Order\Policies;

use App\Http\Auth\Enums\UserRole;
use App\Http\Order\Models\Order;
use App\Models\User;

/**
 * Defines which authenticated roles can create and read orders.
 *
 * @author Dhammadeep
 */
final class OrderPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, [UserRole::Admin, UserRole::Customer], true);
    }

    public function create(User $user): bool
    {
        return $user->role === UserRole::Customer;
    }

    public function view(User $user, Order $order): bool
    {
        return $user->role === UserRole::Admin || (int) $order->user_id === (int) $user->getKey();
    }
}
