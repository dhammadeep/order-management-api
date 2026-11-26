<?php

declare(strict_types=1);

namespace App\Http\Auth\Middleware;

use App\Helpers\ResponseHelper;
use App\Http\Auth\Enums\UserRole;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Allow a route only when the authenticated user has one of its listed roles.
 *
 * @author Dhammadeep
 */
final class EnsureUserHasRole
{
    /**
     * @param  list<string>  $roles
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return ResponseHelper::respond(401, message: 'Unauthenticated.');
        }

        $allowedRoles = collect($roles)
            ->map(fn (string $role): ?UserRole => UserRole::tryFrom($role))
            ->filter()
            ->all();

        if (! in_array($user->role, $allowedRoles, true)) {
            return ResponseHelper::respond(403, message: 'You are not authorized to access this resource.');
        }

        return $next($request);
    }
}
