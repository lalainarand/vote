<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Double confirmation obligatoire (mot de passe admin + mot-clé réservé) avant
 * toute action destructive/à fort impact : réinitialisation de la base de
 * données, marquage d'un bureau en anomalie (exclusion des résultats)...
 *
 * Le mot-clé n'est JAMAIS exposé côté client (voir Admin/Settings/Index.vue) :
 * seul ce fichier serveur le connaît.
 */
class DangerousActionGuard
{
    private const KEYWORD = 'JM-JM-1960';

    /**
     * Valide la présence de password/keyword puis leur exactitude. Lève une
     * ValidationException (redirection standard Inertia avec les erreurs) si
     * l'un des deux est incorrect — n'exécute jamais l'action appelante.
     */
    public static function verify(Request $request): void
    {
        $validated = $request->validate([
            'password' => 'required|string',
            'keyword'  => 'required|string',
        ]);

        if (! Hash::check($validated['password'], $request->user()->password)) {
            throw ValidationException::withMessages(['password' => 'Mot de passe incorrect.']);
        }

        if ($validated['keyword'] !== self::KEYWORD) {
            throw ValidationException::withMessages(['keyword' => 'Mot-clé de confirmation incorrect.']);
        }
    }
}
