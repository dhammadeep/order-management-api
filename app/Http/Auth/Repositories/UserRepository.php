<?php

declare(strict_types=1);

namespace App\Http\Auth\Repositories;

use App\Models\User;

/**
 * Database access for authentication users.
 *
 * @author Dhammadeep
 */
final class UserRepository
{
    /**
     * Create a user from trusted, already validated attributes.
     *
     * @param  array{name: string, email: string, password: string, role: string}  $attributes
     */
    public function create(array $attributes): User
    {
        return User::query()->create($attributes);
    }
}
