<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\User;
use App\Support\DangerousActionGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class SettingController extends Controller
{
    /**
     * Tables de données électorales vidées par la réinitialisation.
     * `settings` (config système) et `users` (traité à part, admin préservé)
     * ne figurent volontairement PAS dans cette liste.
     */
    private const RESETTABLE_TABLES = [
        'vote_logs',
        'bulletin_logs',
        'bulletin_images',
        'bureau_results',
        'bureau_statistics',
        'vote_resets',
        'authorized_devices',
        'device_login_attempts',
        'bureaux_vote',
        'vote_options',
        'model_has_roles',
        'model_has_permissions',
        'role_has_permissions',
        'roles',
        'permissions',
    ];

    public function index()
    {
        return Inertia::render('Admin/Settings/Index', [
            'settings' => Setting::orderBy('label')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'key'         => 'required|string|max:100|alpha_dash|unique:settings,key',
            'value'       => 'required|string|max:1000',
            'label'       => 'nullable|string|max:255',
            'description' => 'nullable|string|max:1000',
        ]);

        Setting::create($validated);

        return redirect()->route('admin.settings.index')->with('success', 'Paramètre créé.');
    }

    public function update(Request $request, Setting $setting)
    {
        $validated = $request->validate([
            'key'         => ['required', 'string', 'max:100', 'alpha_dash', Rule::unique('settings', 'key')->ignore($setting->id)],
            'value'       => 'required|string|max:1000',
            'label'       => 'nullable|string|max:255',
            'description' => 'nullable|string|max:1000',
        ]);

        $setting->update($validated);

        return redirect()->route('admin.settings.index')->with('success', 'Paramètre mis à jour.');
    }

    public function destroy(Setting $setting)
    {
        $setting->delete();

        return redirect()->route('admin.settings.index')->with('success', 'Paramètre supprimé.');
    }

    /**
     * Réinitialisation complète : vide toutes les données électorales (tous
     * bureaux, votes, bulletins, appareils autorisés, rôles/permissions...)
     * et recrée les données de base du seeder (rôles, candidats, bureaux,
     * opérateurs de démo). Le(s) compte(s) admin existant(s) ne sont JAMAIS
     * supprimés ni modifiés (UserSeeder::firstOrCreate les laisse intacts).
     *
     * Double confirmation obligatoire : mot de passe admin + mot-clé fixe.
     */
    public function resetDatabase(Request $request)
    {
        DangerousActionGuard::verify($request);

        $adminIds = User::role('admin')->pluck('id');

        try {
            Schema::disableForeignKeyConstraints();

            foreach (self::RESETTABLE_TABLES as $table) {
                DB::table($table)->truncate();
            }

            // Tous les comptes SAUF les admins (opérateurs, etc.)
            User::whereNotIn('id', $adminIds)->delete();
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        // Spatie met en cache la structure rôles/permissions : indispensable après
        // avoir vidé ces tables directement en SQL, sinon hasRole()/assignRole()
        // ci-dessous peuvent encore raisonner sur les anciens ID en cache.
        Artisan::call('permission:cache-reset');

        // Recrée rôles/permissions, candidats, bureaux, opérateurs de démo.
        // Le bloc admin de UserSeeder est un firstOrCreate : les admins
        // préservés ci-dessus ne sont ni recréés ni modifiés.
        Artisan::call('db:seed', ['--force' => true]);

        // Le(s) admin(s) restant(s) doivent réassigner leur rôle car
        // model_has_roles a été vidé ; assignRole('admin') dans UserSeeder
        // s'en charge déjà pour l'admin@eglise.mg par défaut. Si d'autres
        // comptes admin existaient sous un autre email, on les réassigne ici.
        foreach ($adminIds as $id) {
            $user = User::find($id);
            if ($user && ! $user->hasRole('admin')) {
                $user->assignRole('admin');
            }
        }

        return redirect()
            ->route('admin.dashboard')
            ->with('success', 'Base de données réinitialisée. Rôles, candidats, bureaux et opérateurs ont été recréés.');
    }
}
