<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vote_resets', function (Blueprint $table) {
            $table->id();

            $table->foreignId('bureau_vote_id')
                ->constrained('bureaux_vote')
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            // Ancien état avant réinitialisation
            // { "candidates": { "12": 34, "13": 21 }, "bulletin_count": 55 }
            $table->json('snapshot');

            $table->string('reason')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index('bureau_vote_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vote_resets');
    }
};