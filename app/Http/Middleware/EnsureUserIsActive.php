<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ferme immédiatement la session d'un utilisateur désactivé par un admin,
 * même si sa session était déjà ouverte (pas seulement au prochain login).
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if ($user && ! $user->is_active) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => 'Ce compte a été désactivé. Contactez un administrateur pour le réactiver.',
            ]);
        }

        return $next($request);
    }
}
