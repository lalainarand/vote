<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Journal des tentatives de connexion depuis un appareil non autorisé
     * (device_token absent, inconnu, ou révoqué). Sert de base à l'alerte admin.
     */
    public function up(): void
    {
        Schema::create('device_login_attempts', function (Blueprint $table) {
            $table->id();
            $table->string('device_token')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('ip_address')->nullable();
            $table->string('browser')->nullable();
            $table->string('platform')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_login_attempts');
    }
};
