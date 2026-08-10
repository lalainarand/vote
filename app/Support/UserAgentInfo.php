<?php

namespace App\Support;

/**
 * Extraction best-effort du navigateur et de la plateforme (OS) à partir de
 * l'en-tête User-Agent. Aucune dépendance externe : reste volontairement
 * simple, ces infos ne servent qu'à titre indicatif (jamais d'identifiant).
 */
class UserAgentInfo
{
    public static function browser(?string $userAgent): ?string
    {
        if (! $userAgent) {
            return null;
        }

        if (str_contains($userAgent, 'Edg/')) {
            return 'Edge';
        }
        if (str_contains($userAgent, 'OPR/')) {
            return 'Opera';
        }
        if (str_contains($userAgent, 'SamsungBrowser')) {
            return 'Samsung Internet';
        }
        if (str_contains($userAgent, 'Firefox/')) {
            return 'Firefox';
        }
        if (str_contains($userAgent, 'CriOS')) {
            return 'Chrome (iOS)';
        }
        if (str_contains($userAgent, 'Chrome/')) {
            return 'Chrome';
        }
        if (str_contains($userAgent, 'Safari/')) {
            return 'Safari';
        }

        return null;
    }

    public static function platform(?string $userAgent): ?string
    {
        if (! $userAgent) {
            return null;
        }

        if (str_contains($userAgent, 'Android')) {
            return 'Android';
        }
        if (str_contains($userAgent, 'iPad') || str_contains($userAgent, 'iPhone')) {
            return 'iOS';
        }
        if (str_contains($userAgent, 'Windows')) {
            return 'Windows';
        }
        if (str_contains($userAgent, 'Macintosh')) {
            return 'macOS';
        }
        if (str_contains($userAgent, 'Linux')) {
            return 'Linux';
        }

        return null;
    }
}
