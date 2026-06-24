<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('bureaux_vote', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();   // BV001, BV002...
            $table->string('nom');              // "Bureau 1 - Salle principale"
            $table->enum('status', [
                'pending',
                'counting',
                'pv_entry',
                'pv_admin',
                'validated',
                'anomaly'
            ])->default('pending');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bureaux_vote');
    }
};
