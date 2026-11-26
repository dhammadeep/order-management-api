<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Helpers\ResponseHelper;
use App\Http\Auth\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

/**
 * Feature coverage for registration, token sessions, and role authorization.
 *
 * @author Dhammadeep
 */
final class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::get('/api/test/admin-only', fn () => ResponseHelper::respond(
            code: 200,
            message: 'Authorized.',
        ))->middleware(['auth:sanctum', 'role:admin']);
    }

    public function test_customer_can_register_successfully(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Alex Customer',
            'email' => 'alex@example.test',
            'password' => 'customer-password',
            'password_confirmation' => 'customer-password',
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.name', 'Alex Customer')
            ->assertJsonPath('data.user.role', 'customer')
            ->assertJsonMissingPath('data.user.password');

        $user = User::query()->where('email', 'alex@example.test')->firstOrFail();

        $this->assertSame(UserRole::Customer, $user->role);
        $this->assertTrue(Hash::check('customer-password', $user->password));
    }

    public function test_registration_rejects_duplicate_email(): void
    {
        User::factory()->create(['email' => 'duplicate@example.test']);

        $this->postJson('/api/register', [
            'name' => 'Another Customer',
            'email' => 'duplicate@example.test',
            'password' => 'customer-password',
            'password_confirmation' => 'customer-password',
        ])->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['errors' => ['email']]);
    }

    public function test_public_registration_cannot_assign_admin_role(): void
    {
        $this->postJson('/api/register', [
            'name' => 'Untrusted Admin',
            'email' => 'untrusted@example.test',
            'password' => 'customer-password',
            'password_confirmation' => 'customer-password',
            'role' => 'admin',
        ])->assertCreated()
            ->assertJsonPath('data.user.role', 'customer');

        $this->assertDatabaseHas('users', [
            'email' => 'untrusted@example.test',
            'role' => UserRole::Customer->value,
        ]);
    }

    public function test_valid_login_returns_a_sanctum_bearer_token(): void
    {
        User::factory()->create([
            'email' => 'login@example.test',
            'password' => Hash::make('customer-password'),
        ]);

        $this->postJson('/api/login', [
            'email' => 'login@example.test',
            'password' => 'customer-password',
        ])->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.user.email', 'login@example.test')
            ->assertJsonMissingPath('data.user.password')
            ->assertJsonStructure(['data' => ['access_token']]);

        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_invalid_login_returns_unauthorized(): void
    {
        User::factory()->create([
            'email' => 'login@example.test',
            'password' => Hash::make('customer-password'),
        ]);

        $this->postJson('/api/login', [
            'email' => 'login@example.test',
            'password' => 'incorrect-password',
        ])->assertUnauthorized()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Unauthenticated.');
    }

    public function test_logout_revokes_only_the_current_token(): void
    {
        $user = User::factory()->create();
        $currentToken = $user->createToken('current-token');
        $otherToken = $user->createToken('other-token');

        $this->withToken($currentToken->plainTextToken)
            ->postJson('/api/logout')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertNull(PersonalAccessToken::findToken($currentToken->plainTextToken));
        $this->assertNotNull(PersonalAccessToken::findToken($otherToken->plainTextToken));

        Auth::forgetGuards();
        $this->withToken($currentToken->plainTextToken)->getJson('/api/me')->assertUnauthorized();
    }

    public function test_protected_endpoints_require_authentication(): void
    {
        $this->getJson('/api/me')->assertUnauthorized();
        $this->postJson('/api/logout')->assertUnauthorized();
    }

    public function test_authenticated_user_can_retrieve_their_safe_profile(): void
    {
        $user = User::factory()->create(['email' => 'profile@example.test']);
        $token = $user->createToken('profile-token')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('data.user.email', 'profile@example.test')
            ->assertJsonPath('data.user.role', 'customer')
            ->assertJsonMissingPath('data.user.password');
    }

    public function test_customer_cannot_access_an_admin_only_route(): void
    {
        $customer = User::factory()->create(['role' => UserRole::Customer]);
        $token = $customer->createToken('customer-token')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/test/admin-only')
            ->assertForbidden()
            ->assertJsonPath('success', false);
    }
}
