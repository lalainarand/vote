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
            ['nom' => 'Pst. Rakotonjanahary Jérome Roland',  'type' => 'candidat', 'ordre_affichage' => 1],
            ['nom' => 'Pst. Randriamampionina  Andrianirina A.',    'type' => 'candidat', 'ordre_affichage' => 2],
            ['nom' => 'Pst. Randrianatoandro Raymond',    'type' => 'candidat', 'ordre_affichage' => 3],
            ['nom' => 'Pst. Randrianatoandro David',    'type' => 'candidat', 'ordre_affichage' => 4],
            ['nom' => 'Pst. Raphael Gilbert',    'type' => 'candidat', 'ordre_affichage' => 5],
            ['nom' => 'Pst. Randriamparany Jean David',    'type' => 'candidat', 'ordre_affichage' => 6],
            ['nom' => 'Pst. Razafinjato Justin',    'type' => 'candidat', 'ordre_affichage' => 7],
            ['nom' => 'Randriamahantsoa Theophile(Mejm)',    'type' => 'candidat', 'ordre_affichage' => 8],
            ['nom' => 'Rakotonirina Armand (Mejm)',    'type' => 'candidat', 'ordre_affichage' => 9],
            ['nom' => 'Pst. Randrianirina Solondraibe',    'type' => 'candidat', 'ordre_affichage' => 10],
            ['nom' => 'Pst. Ramarolahy Andrianjaka',    'type' => 'candidat', 'ordre_affichage' => 12],
            ['nom' => 'Razakamanana Francois (Mejm)',    'type' => 'candidat', 'ordre_affichage' => 14],
            ['nom' => 'Fr. Randriambololona Lala',    'type' => 'candidat', 'ordre_affichage' => 15],   
            ['nom' => 'Fr. Rakotomalala Herinantenaina',    'type' => 'candidat', 'ordre_affichage' => 16],
            ['nom' => 'Pst. Rajaonirina Dieu donné',    'type' => 'candidat', 'ordre_affichage' => 18],
            ['nom' => 'Fr. Rasolofomanana Mikaia Samoela',    'type' => 'candidat', 'ordre_affichage' => 19],
            ['nom' => 'Pst. Ramaherison Lionel Norbert',    'type' => 'candidat', 'ordre_affichage' => 20],
            ['nom' => 'Fr. Rakotoniaina Jeannot Justin',    'type' => 'candidat', 'ordre_affichage' => 21],
            ['nom' => 'Fr. Rakotoarimamy Herisoa JEan Marc',    'type' => 'candidat', 'ordre_affichage' => 22],
            ['nom' => 'Fr. Razafimandimby Fidel',    'type' => 'candidat', 'ordre_affichage' => 23],
            ['nom' => 'Ramarolahy Norbert(Mejm)',    'type' => 'candidat', 'ordre_affichage' => 24],
            ['nom' => 'Fr. Andrianaja Ndimby Matoa',    'type' => 'candidat', 'ordre_affichage' => 25],
            ['nom' => 'Pst. Nomenjanahary Andry Toky Z.',    'type' => 'candidat', 'ordre_affichage' => 27],
            ['nom' => 'Pst. Rakotomalalaniaina Jean René',    'type' => 'candidat', 'ordre_affichage' => 29],
            ['nom' => 'Pst. Ranaivonirina Christian',    'type' => 'candidat', 'ordre_affichage' => 30],
            ['nom' => 'Pst. Rakotoarisoa Maminiaina',    'type' => 'candidat', 'ordre_affichage' => 31],
            ['nom' => 'Fr. Rivoarimanana Hery Lala',    'type' => 'candidat', 'ordre_affichage' => 32],
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