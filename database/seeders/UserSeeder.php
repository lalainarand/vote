<?php

// database/seeders/UserSeeder.php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\BureauVote;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Admin — firstOrCreate (PAS updateOrCreate) : si le compte admin existe déjà,
        // on ne le touche surtout pas (mot de passe éventuellement personnalisé par
        // l'admin lui-même). Ne sert qu'à amorcer le tout premier admin d'une
        // installation neuve, et rend le seeder sûr à rejouer (ex: réinitialisation
        // de la base de données, qui préserve volontairement le compte admin).
        $admin = User::firstOrCreate(
            ['email' => 'admin@eglise.mg'],
            [
                'name'           => 'Administrateur',
                'password'       => Hash::make('#password98765432101#'),
                'password_plain' => '#password98765432101#',
                'bureau_vote_id' => null,
                'is_active'      => true,
                'is_approved'    => true,
            ]
        );
        $admin->assignRole('admin'); // ← Spatie (idempotent, sûr même si déjà assigné)

        // Opérateurs
        $operators = [
            ['name' => 'Opérateur BV001', 'email' => 'op1@eglise.mg', 'code' => 'BV001', 'is_active' => true, 'is_approved' => false],
            ['name' => 'Opérateur BV002', 'email' => 'op2@eglise.mg', 'code' => 'BV002', 'is_active' => true, 'is_approved' => false],
            ['name' => 'Opérateur BV003', 'email' => 'op3@eglise.mg', 'code' => 'BV003', 'is_active' => true, 'is_approved' => false],
            ['name' => 'Opérateur BV004', 'email' => 'op4@eglise.mg', 'code' => 'BV004', 'is_active' => true, 'is_approved' => false],
            ['name' => 'Opérateur BV005', 'email' => 'op5@eglise.mg', 'code' => 'BV005', 'is_active' => true, 'is_approved' => false],
            ['name' => 'Opérateur BV006', 'email' => 'op6@eglise.mg', 'code' => 'BV006', 'is_active' => true, 'is_approved' => false],
            ['name' => 'Opérateur BV007', 'email' => 'op7@eglise.mg', 'code' => 'BV007', 'is_active' => true, 'is_approved' => false],
            ['name' => 'Opérateur BV008', 'email' => 'op8@eglise.mg', 'code' => 'BV008', 'is_active' => true, 'is_approved' => false],
            ['name' => 'Opérateur BV009', 'email' => 'op9@eglise.mg', 'code' => 'BV009', 'is_active' => true, 'is_approved' => false],
            ['name' => 'Opérateur BV0010', 'email' => 'op10@eglise.mg', 'code' => 'BV010', 'is_active' => true, 'is_approved' => false],
            ['name' => 'Opérateur BV0011', 'email' => 'op11@eglise.mg', 'code' => 'BV011', 'is_active' => true, 'is_approved' => false],
        ];

        // On garde les mots de passe en clair uniquement le temps du seed,
        // pour pouvoir les communiquer aux opérateurs (impossible à retrouver ensuite, ils ne sont stockés que hashés).
        $credentials = [];

        foreach ($operators as $op) {
            $bureau = BureauVote::where('code', $op['code'])->first();

            if (!$bureau) {
                $this->command?->warn("Bureau introuvable pour le code {$op['code']}, opérateur {$op['email']} ignoré.");
                continue;
            }

            // Mot de passe aléatoire et unique par opérateur (12 caractères, conforme à la
            // politique du projet : chiffres, lettres, et uniquement # * . " @ -)
            $plainPassword = User::generatePassword();

            $user = User::updateOrCreate(
                ['email' => $op['email']],
                [
                    'name'           => $op['name'],
                    'email'          => $op['email'],
                    'password'       => Hash::make($plainPassword),
                    'password_plain' => $plainPassword,
                    'bureau_vote_id' => $bureau->id,
                ]
            );
            $user->assignRole('operator'); // ← Spatie

            $credentials[] = [
                'code'     => $op['code'],
                'name'     => $op['name'],
                'email'    => $op['email'],
                'password' => $plainPassword,
            ];
        }

        // Affichage dans la console (si lancé via artisan db:seed)
        if ($this->command && !empty($credentials)) {
            $this->command->table(
                ['Bureau', 'Nom', 'Email', 'Mot de passe'],
                collect($credentials)->map(fn ($c) => [$c['code'], $c['name'], $c['email'], $c['password']])->all()
            );
        }

        // Sauvegarde dans un fichier privé pour transmission ultérieure aux opérateurs.
        if (!empty($credentials)) {
            $lines = collect($credentials)
                ->map(fn ($c) => "{$c['code']} | {$c['name']} | {$c['email']} | {$c['password']}")
                ->implode(PHP_EOL);

            $filename = 'seeders/operator-credentials-' . now()->format('Y-m-d_His') . '.txt';
            Storage::disk('local')->put($filename, $lines);

            $this->command?->info("Identifiants opérateurs sauvegardés dans storage/app/{$filename}");
            $this->command?->warn('Pensez à supprimer ce fichier une fois les identifiants transmis, et à ne jamais le committer.');
        }
    }
}