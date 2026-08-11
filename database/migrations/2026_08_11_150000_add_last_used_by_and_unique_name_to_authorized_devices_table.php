<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('authorized_devices', function (Blueprint $table) {
            // Dernier opérateur ayant utilisé cet appareil (informatif : l'appareil
            // n'est jamais réservé à cet opérateur, n'importe quel opérateur actif
            // + approuvé peut continuer à s'y connecter).
            $table->foreignId('last_used_by')
                ->nullable()
                ->after('last_used_at')
                ->constrained('users')
                ->nullOnDelete();

            $table->unique('device_name');
        });
    }

    public function down(): void
    {
        Schema::table('authorized_devices', function (Blueprint $table) {
            $table->dropUnique(['device_name']);
            $table->dropConstrainedForeignId('last_used_by');
        });
    }
};
