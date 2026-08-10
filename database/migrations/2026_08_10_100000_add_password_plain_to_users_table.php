<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Copie du mot de passe en clair, chiffrée de façon réversible (cast Eloquent
     * "encrypted", basé sur APP_KEY) — PAS un hash. Permet à l'admin de consulter/
     * exporter les identifiants des opérateurs. Le champ "password" reste un hash
     * bcrypt classique et continue seul à servir à l'authentification.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('password_plain')->nullable()->after('password');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('password_plain');
        });
    }
};
