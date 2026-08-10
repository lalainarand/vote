<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Les 26 tablettes autorisées. Un appareil n'est JAMAIS lié à un opérateur :
     * n'importe quel opérateur actif + approuvé peut utiliser n'importe laquelle
     * des tablettes autorisées. device_token est l'identifiant principal (posé
     * via cookie persistant lors de l'appairage) ; browser/platform/ip_address
     * ne sont que des informations complémentaires, jamais des identifiants.
     */
    public function up(): void
    {
        Schema::create('authorized_devices', function (Blueprint $table) {
            $table->id();
            $table->string('device_token')->unique();
            $table->string('device_name')->nullable();
            $table->string('browser')->nullable();
            $table->string('platform')->nullable();
            $table->string('ip_address')->nullable();
            $table->boolean('is_approved')->default(false);
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('authorized_devices');
    }
};
