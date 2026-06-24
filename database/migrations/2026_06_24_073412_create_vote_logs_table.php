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
        Schema::create('vote_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bureau_vote_id')->constrained('bureaux_vote')->cascadeOnDelete();
            $table->foreignId('vote_option_id')->constrained('vote_options')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('action', ['+1', '-1']);
            $table->timestamp('created_at')->useCurrent();
            // Pas de updated_at : un log est immuable
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vote_logs');
    }
};
