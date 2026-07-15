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
            ['code' => 'BV001', 'nom' => 'Bureau 1 - Salle principale1'],
            ['code' => 'BV002', 'nom' => 'Bureau 2 - Salle principale2'],
            ['code' => 'BV003', 'nom' => 'Bureau 3 - Salle principale3'],
            ['code' => 'BV004', 'nom' => 'Bureau 4 - Salle principale4'],
            ['code' => 'BV005', 'nom' => 'Bureau 5 - Salle principale5'],
            ['code' => 'BV006', 'nom' => 'Bureau 6 - Salle principale6'],
            ['code' => 'BV007', 'nom' => 'Bureau 7 - Salle principale7'],
            ['code' => 'BV008', 'nom' => 'Bureau 8 - Salle principale8'],
            ['code' => 'BV009', 'nom' => 'Bureau 9 - Salle principale9'],
            ['code' => 'BV010', 'nom' => 'Bureau 10 - Salle principale10'],
            ['code' => 'BV011', 'nom' => 'Bureau 11 - Salle principale11'],
            // ['code' => 'BV012', 'nom' => 'Bureau 12 - Salle principale12'],
            // ['code' => 'BV013', 'nom' => 'Bureau 13 - Salle principale13'],
            // ['code' => 'BV014', 'nom' => 'Bureau 14 - Salle principale14'],
            // ['code' => 'BV015', 'nom' => 'Bureau 15 - Salle principale15'],
            // ['code' => 'BV016', 'nom' => 'Bureau 16 - Salle principale16'],
            // ['code' => 'BV017', 'nom' => 'Bureau 17 - Salle principale17'],
            // ['code' => 'BV018', 'nom' => 'Bureau 18 - Salle principale18'],
            // ['code' => 'BV019', 'nom' => 'Bureau 19 - Salle principale19'],
            // ['code' => 'BV020', 'nom' => 'Bureau 20 - Salle principale20'],
        ];

        foreach ($bureaux as $bv) {
            BureauVote::updateOrCreate(
                ['code' => $bv['code']],
                $bv
            );
        }
    }
}
