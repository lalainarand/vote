<?php

namespace App\Http\Middleware;

use App\Models\AuthorizedDevice;
use App\Models\DeviceLoginAttempt;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Vérifie l'appareil sur CHAQUE requête authentifiée, pas seulement au login :
 * une session copiée sur un autre navigateur (cookie de session volé/partagé)
 * n'aurait pas le cookie device_token de la tablette d'origine et doit être
 * bloquée elle aussi. Ne s'applique qu'aux opérateurs (voir LoginRequest pour
 * le raisonnement : soumettre aussi les admins créerait un blocage total
 * au premier démarrage, puisque c'est depuis l'admin qu'on enregistre les
 * tablettes).
 */
class EnsureDeviceIsAuthorized
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if ($user && $user->isOperator()) {
            $onLogoutRoute = $request->routeIs('logout');
            $onPairingRoute = $request->routeIs('device.pair');

            if (! $onLogoutRoute && ! $onPairingRoute) {
                $device = AuthorizedDevice::resolveFromRequest($request);

                if (! $device) {
                    DeviceLoginAttempt::logFromRequest($request, $user);

                    Auth::logout();
                    $request->session()->invalidate();
                    $request->session()->regenerateToken();

                    return redirect()->route('login')->withErrors([
                        'email' => "Cet appareil n'est pas autorisé à accéder au système de comptage. Contactez un administrateur.",
                    ]);
                }
            }
        }

        return $next($request);
    }
}
