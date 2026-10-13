<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware de vérification du rôle utilisateur.
 *
 * Usage dans les routes :
 *   Route::middleware('role:owner')
 *   Route::middleware('role:admin')
 *   Route::middleware('role:owner,admin')  ← plusieurs rôles acceptés
 */
class CheckRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Non authentifié.'], 401);
        }

        if (! in_array($user->role, $roles)) {
            return response()->json([
                'message' => 'Accès refusé. Rôle insuffisant.',
            ], 403);
        }

        return $next($request);
    }
}
