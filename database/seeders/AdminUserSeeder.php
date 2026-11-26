<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Http\Auth\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * Create or update the configured local administrator account.
 *
 * @author Dhammadeep
 */
final class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $name = env('ADMIN_NAME');
        $email = env('ADMIN_EMAIL');
        $password = env('ADMIN_PASSWORD');

        if (! is_string($name) || $name === '' || ! is_string($email) || $email === '' || ! is_string($password) || $password === '') {
            throw new RuntimeException('Set ADMIN_NAME, ADMIN_EMAIL, and ADMIN_PASSWORD before seeding the admin user.');
        }

        User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make($password),
                'role' => UserRole::Admin,
            ],
        );
    }
}
