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
                'password'       => Hash::make('password'),
                'bureau_vote_id' => null,
            ]
        );
        $admin->assignRole('admin'); // ← Spatie

        // Opérateurs
        $operators = [
            ['name' => 'Opérateur BV001', 'email' => 'op1@eglise.mg', 'code' => 'BV001'],
            ['name' => 'Opérateur BV002', 'email' => 'op2@eglise.mg', 'code' => 'BV002'],
            ['name' => 'Opérateur BV003', 'email' => 'op3@eglise.mg', 'code' => 'BV003'],
        ];

        foreach ($operators as $op) {
            $bureau = BureauVote::where('code', $op['code'])->first();
            
            $user = User::updateOrCreate(
                ['email' => $op['email']],
                [
                    'name'           => $op['name'],
                    'email'          => $op['email'],
                    'password'       => Hash::make('password'),
                    'bureau_vote_id' => $bureau->id,
                ]
            );
            $user->assignRole('operator'); // ← Spatie
        }
    }
}