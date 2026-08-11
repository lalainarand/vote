<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Paramètres généraux de l'application (clé/valeur). Table de configuration
     * système — jamais vidée par la réinitialisation de la base de données.
     */
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->string('label')->nullable();
            $table->string('description')->nullable();
            $table->timestamps();
        });

        DB::table('settings')->insert([
            'key'         => 'max_procuration',
            'value'       => '50',
            'label'       => 'Nombre max de votants par procuration',
            'description' => "Limite le nombre de votants saisi en une fois dans les modales de saisie manuelle par procuration (vote candidat et bulletins).",
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
