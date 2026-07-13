<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bulletin_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('bureau_vote_id')
                ->constrained('bureaux_vote')
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->enum('action', ['+1', '-1']);
            $table->unsignedInteger('quantity')->default(1);
            $table->boolean('is_manuel')->default(false);
            $table->timestamp('created_at')->nullable();

            $table->index(['bureau_vote_id', 'action']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bulletin_logs');
    }
};