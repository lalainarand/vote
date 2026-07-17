<?php

// database/seeders/UserSeeder.php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\BureauVote;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Admin
        $admin = User::updateOrCreate(
            ['email' => 'admin@eglise.mg'],
            [
                'name'           => 'Administrateur',
                'email'          => 'admin@eglise.mg',
                'password'       => Hash::make('#password98765432101#'),
                'bureau_vote_id' => null,
            ]
        );
        $admin->assignRole('admin'); // ← Spatie

        // Opérateurs
        $operators = [
            ['name' => 'Opérateur BV001', 'email' => 'op1@eglise.mg', 'code' => 'BV001'],
            ['name' => 'Opérateur BV002', 'email' => 'op2@eglise.mg', 'code' => 'BV002'],
            ['name' => 'Opérateur BV003', 'email' => 'op3@eglise.mg', 'code' => 'BV003'],
            ['name' => 'Opérateur BV004', 'email' => 'op4@eglise.mg', 'code' => 'BV004'],
            ['name' => 'Opérateur BV005', 'email' => 'op5@eglise.mg', 'code' => 'BV005'],
            ['name' => 'Opérateur BV006', 'email' => 'op6@eglise.mg', 'code' => 'BV006'],
            ['name' => 'Opérateur BV007', 'email' => 'op7@eglise.mg', 'code' => 'BV007'],
            ['name' => 'Opérateur BV008', 'email' => 'op8@eglise.mg', 'code' => 'BV008'],
            ['name' => 'Opérateur BV009', 'email' => 'op9@eglise.mg', 'code' => 'BV009'],
            ['name' => 'Opérateur BV0010', 'email' => 'op10@eglise.mg', 'code' => 'BV010'],
            ['name' => 'Opérateur BV0011', 'email' => 'op11@eglise.mg', 'code' => 'BV011'],
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

            // Mot de passe aléatoire et unique par opérateur (12 caractères, majuscules/minuscules/chiffres/symboles)
            $plainPassword = Str::password(12);

            $user = User::updateOrCreate(
                ['email' => $op['email']],
                [
                    'name'           => $op['name'],
                    'email'          => $op['email'],
                    'password'       => Hash::make($plainPassword),
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
        // ⚠️ Ne jamais committer ce fichier : il contient des mots de passe en clair.
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