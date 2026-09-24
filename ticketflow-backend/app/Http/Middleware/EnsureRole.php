<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Garde par rôle : middleware('role:organizer') ou ('role:admin,support').
 * Simple et lisible ; les autorisations fines (propriété d'une ressource) passent par les Policies.
 */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string $roles): mixed
    {
        $user = $request->user();
        if (!$user || !in_array($user->role, explode(',', $roles), true)) {
            abort(403, 'Accès refusé.');
        }
        return $next($request);
    }
}
