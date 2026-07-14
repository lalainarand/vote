<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bulletin_images', function (Blueprint $table) {
            $table->id();

            $table->foreignId('bureau_vote_id')
                ->constrained('bureaux_vote')
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->string('path');
            $table->string('filename');

            // Date et heure de prise de la photo
            $table->timestamp('taken_at');

            $table->timestamps();

            $table->index(['bureau_vote_id', 'taken_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bulletin_images');
    }
};