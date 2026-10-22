<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Role;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $allowedRoles = array_map(
            static fn (string $role): Role => Role::from($role),
            $roles,
        );

        $user = $request->user();

        abort_unless(
            $user instanceof User && $user->hasRole(...$allowedRoles),
            Response::HTTP_FORBIDDEN,
        );

        return $next($request);
    }
}
