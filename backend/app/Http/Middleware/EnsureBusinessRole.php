<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureBusinessRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        // Platform administrators retain unrestricted platform access.
        if ($user->isPlatformAdmin()) {
            return $next($request);
        }

        // Business owners always retain full access to their business.
        if ($request->attributes->get('is_business_owner', false)) {
            return $next($request);
        }

        $role = $request->attributes->get('business_role');

        if (! $role || ! in_array($role, $roles, true)) {
            return response()->json([
                'message' => 'You do not have permission to perform this action.',
            ], 403);
        }

        return $next($request);
    }
}
