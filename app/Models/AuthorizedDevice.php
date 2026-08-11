<?php

namespace App\Models;

use App\Support\UserAgentInfo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AuthorizedDevice extends Model
{
    /**
     * Nom du cookie persistant posé sur une tablette lors de l'appairage.
     * C'est l'identifiant principal de l'appareil — IP/navigateur/plateforme
     * ne sont que des informations complémentaires, jamais des identifiants.
     */
    public const COOKIE_NAME = 'device_token';

    protected $fillable = [
        'device_token',
        'device_name',
        'browser',
        'platform',
        'ip_address',
        'is_approved',
        'approved_by',
        'approved_at',
        'last_used_at',
        'last_used_by',
    ];

    protected $casts = [
        'is_approved'   => 'boolean',
        'approved_at'   => 'datetime',
        'last_used_at'  => 'datetime',
    ];

    /**
     * Admin ayant approuvé (créé) cet appareil.
     */
    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Dernier opérateur ayant utilisé cet appareil — purement informatif :
     * l'appareil n'est jamais réservé à cet opérateur, un autre opérateur
     * actif + approuvé peut s'y connecter juste après.
     */
    public function lastUsedBy()
    {
        return $this->belongsTo(User::class, 'last_used_by');
    }

    /**
     * Jeton unique et persistant identifiant l'appareil — c'est l'identifiant
     * principal (posé en cookie lors de l'appairage). IP/navigateur/plateforme
     * ne sont que des informations complémentaires.
     */
    public static function generateToken(): string
    {
        return Str::random(48);
    }

    /**
     * Résout l'appareil autorisé correspondant au cookie device_token de la
     * requête, s'il existe et est approuvé. Ne crée jamais rien : un token
     * absent ou inconnu retourne simplement null (appareil non autorisé).
     */
    public static function resolveFromRequest(Request $request): ?self
    {
        $token = $request->cookie(self::COOKIE_NAME);

        if (! $token) {
            return null;
        }

        return static::where('device_token', $token)
            ->where('is_approved', true)
            ->first();
    }

    /**
     * Met à jour les infos complémentaires (dernière utilisation, navigateur,
     * plateforme, IP, dernier opérateur) à chaque connexion réussie depuis cet
     * appareil.
     */
    public function touchUsage(Request $request, ?User $user = null): void
    {
        $this->update([
            'last_used_at' => now(),
            'last_used_by' => $user?->id ?? $this->last_used_by,
            'browser'      => UserAgentInfo::browser($request->userAgent()) ?? $this->browser,
            'platform'     => UserAgentInfo::platform($request->userAgent()) ?? $this->platform,
            'ip_address'   => $request->ip(),
        ]);
    }
}
