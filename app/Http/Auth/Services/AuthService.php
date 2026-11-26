<?php

declare(strict_types=1);

namespace App\Http\Auth\Services;

use App\Http\Auth\Enums\UserRole;
use App\Http\Auth\Repositories\UserRepository;
use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Coordinates account creation and Sanctum token authentication.
 *
 * @author Dhammadeep
 */
final class AuthService
{
    public function __construct(private readonly UserRepository $users) {}

    /**
     * @param  array{name: string, email: string, password: string}  $attributes
     */
    public function registerCustomer(array $attributes): User
    {
        return $this->users->create([
            'name' => $attributes['name'],
            'email' => $attributes['email'],
            'password' => Hash::make($attributes['password']),
            'role' => UserRole::Customer->value,
        ]);
    }

    /**
     * @return array{user: User, access_token: string}
     *
     * @throws AuthenticationException
     */
    public function login(string $email, string $password): array
    {
        $guard = Auth::guard('web');

        if (! $guard->once(['email' => $email, 'password' => $password])) {
            throw new AuthenticationException('Invalid email or password.');
        }

        $user = $guard->user();

        if (! $user instanceof User) {
            throw new AuthenticationException('Invalid email or password.');
        }

        return [
            'user' => $user,
            'access_token' => $user->createToken('order-management-api')->plainTextToken,
        ];
    }

    public function logout(User $user): void
    {
        $currentToken = $user->currentAccessToken();

        if ($currentToken instanceof PersonalAccessToken) {
            $currentToken->delete();
        }
    }
}
