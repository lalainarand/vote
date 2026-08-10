<?php

namespace App\Http\Controllers;

use App\Models\AuthorizedDevice;
use App\Support\UserAgentInfo;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Appairage d'une tablette : route publique (pas de session requise — une
 * tablette neuve n'en a pas encore) visitée UNE FOIS avec le lien fourni par
 * un admin pour une des 26 tablettes déjà enregistrées. Ne crée jamais
 * d'appareil : le token doit déjà exister et être approuvé.
 */
class DeviceRegistrationController extends Controller
{
    public function pair(Request $request, string $token)
    {
        $device = AuthorizedDevice::where('device_token', $token)->first();

        if (! $device || ! $device->is_approved) {
            return Inertia::render('Auth/DevicePairing', [
                'success' => false,
                'message' => $device
                    ? "Cet appareil a été révoqué. Contactez un administrateur."
                    : "Lien d'appairage invalide.",
            ]);
        }

        // Première capture des infos complémentaires (jamais l'identifiant principal)
        $device->update([
            'browser'      => UserAgentInfo::browser($request->userAgent()) ?? $device->browser,
            'platform'     => UserAgentInfo::platform($request->userAgent()) ?? $device->platform,
            'ip_address'   => $request->ip(),
            'last_used_at' => now(),
        ]);

        cookie()->queue(cookie()->forever(
            AuthorizedDevice::COOKIE_NAME,
            $device->device_token,
            null,               // path
            null,               // domain
            $request->secure(), // secure (HTTPS only in prod, allows http in local dev)
            true,               // httpOnly
            false,              // raw
            'Lax'               // sameSite
        ));

        return Inertia::render('Auth/DevicePairing', [
            'success' => true,
            'message' => "Cet appareil (\"{$device->device_name}\") est maintenant reconnu. Vous pouvez vous connecter.",
        ]);
    }
}
