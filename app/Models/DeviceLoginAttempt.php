<?php

namespace App\Models;

use App\Support\UserAgentInfo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Journal des tentatives de connexion depuis un appareil non autorisé.
 * Base de l'alerte admin "🚨 Tentative depuis un appareil non autorisé".
 */
class DeviceLoginAttempt extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'device_token',
        'user_id',
        'ip_address',
        'browser',
        'platform',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Enregistre une tentative bloquée. $user n'est renseigné que si les
     * identifiants soumis étaient valides (mot de passe correct).
     */
    public static function logFromRequest(Request $request, ?User $user = null): self
    {
        return static::create([
            'device_token' => $request->cookie(AuthorizedDevice::COOKIE_NAME),
            'user_id'      => $user?->id,
            'ip_address'   => $request->ip(),
            'browser'      => UserAgentInfo::browser($request->userAgent()),
            'platform'     => UserAgentInfo::platform($request->userAgent()),
            'created_at'   => now(),
        ]);
    }
}
