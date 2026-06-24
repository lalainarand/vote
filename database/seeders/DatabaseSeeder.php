<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();
        $this->call([
            RolePermissionSeeder::class,   // 1. ⚠️ EN PREMIER — rôles + permissions
            VoteOptionSeeder::class,       // 2. Candidats
            BureauVoteSeeder::class,       // 3. Bureaux
            UserSeeder::class,             // 4. Users (assigne les rôles)
        ]);
        // User::factory()->create([
        //     'name' => 'Test User',
        //     'email' => 'test@example.com',
        // ]);
    }
}
// php artisan migrate:fresh --seed