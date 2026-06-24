<?php

// database/seeders/VoteOptionSeeder.php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\VoteOption;

class VoteOptionSeeder extends Seeder
{
    public function run(): void
    {
        $options = [
            ['nom' => 'Pasteur Jean',  'type' => 'candidat', 'ordre_affichage' => 1],
            ['nom' => 'Sœur Marie',    'type' => 'candidat', 'ordre_affichage' => 2],
            ['nom' => 'Frère Paul',    'type' => 'candidat', 'ordre_affichage' => 3],
            ['nom' => 'Blanc',         'type' => 'blanc',    'ordre_affichage' => 98],
            ['nom' => 'Nul',           'type' => 'nul',      'ordre_affichage' => 99],
        ];

        foreach ($options as $opt) {
            VoteOption::updateOrCreate(
                ['nom' => $opt['nom']],
                $opt
            );
        }
    }
}