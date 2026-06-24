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
    Schema::create('bureau_results', function (Blueprint $table) {
        $table->id();
        $table->foreignId('bureau_vote_id')->constrained('bureaux_vote')->cascadeOnDelete();
        $table->foreignId('vote_option_id')->constrained('vote_options')->cascadeOnDelete();
        $table->integer('count')->default(0);
        $table->enum('source', ['counting', 'manual_pv', 'admin_override'])->default('counting');
        $table->foreignId('entered_by')->nullable()->constrained('users')->nullOnDelete();
        $table->timestamp('entered_at')->nullable();
        $table->timestamps();

        $table->unique(['bureau_vote_id', 'vote_option_id']);
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bureau_results');
    }
};
