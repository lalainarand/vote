<?php

// database/seeders/BureauVoteSeeder.php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\BureauVote;

class BureauVoteSeeder extends Seeder
{
    public function run(): void
    {
        $bureaux = [
            ['code' => 'BV001', 'nom' => 'Bureau 1 - Salle principale'],
            ['code' => 'BV002', 'nom' => 'Bureau 2 - Salle annexe'],
            ['code' => 'BV003', 'nom' => 'Bureau 3 - Hall'],
        ];

        foreach ($bureaux as $bv) {
            BureauVote::updateOrCreate(
                ['code' => $bv['code']],
                $bv
            );
        }
    }
}