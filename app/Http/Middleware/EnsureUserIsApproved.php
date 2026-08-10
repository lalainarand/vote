<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Un compte avec is_approved = false peut se connecter (identifiants + is_active
 * corrects) mais reste cantonné à la page d'attente tant qu'un admin n'a pas
 * explicitement autorisé l'accès (bouton "Autoriser l'accès").
 */
class EnsureUserIsApproved
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if ($user) {
            $onPendingPage = $request->routeIs('pending-approval');
            $onLogoutRoute = $request->routeIs('logout');

            if (! $user->is_approved && ! $onPendingPage && ! $onLogoutRoute) {
                return redirect()->route('pending-approval');
            }

            // Un compte déjà approuvé qui reviendrait sur la page d'attente
            // (ex: onglet resté ouvert) est renvoyé vers son espace normal.
            if ($user->is_approved && $onPendingPage) {
                return redirect()->route('redirect');
            }
        }

        return $next($request);
    }
}
