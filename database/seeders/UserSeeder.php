<?php

// database/seeders/UserSeeder.php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\BureauVote;
use Illuminate\Support\Facades\Hash;

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
                'password'       => Hash::make('password987654321'),
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
            // ['name' => 'Opérateur BV0012', 'email' => 'op12@eglise.mg', 'code' => 'BV012'],
            // ['name' => 'Opérateur BV0013', 'email' => 'op13@eglise.mg', 'code' => 'BV013'],

        ];

        foreach ($operators as $op) {
            $bureau = BureauVote::where('code', $op['code'])->first();

            $user = User::updateOrCreate(
                ['email' => $op['email']],
                [
                    'name'           => $op['name'],
                    'email'          => $op['email'],
                    'password'       => Hash::make('password987654321'),
                    'bureau_vote_id' => $bureau->id,
                ]
            );
            $user->assignRole('operator'); // ← Spatie
        }
    }
}
