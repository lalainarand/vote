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
    Schema::create('bureau_statistics', function (Blueprint $table) {
        $table->id();
        $table->foreignId('bureau_vote_id')->unique()->constrained('bureaux_vote')->cascadeOnDelete();
        $table->integer('registered_voters')->default(0);  // Inscrits
        $table->integer('voters')->default(0);             // Votants
        $table->integer('ballots_found')->default(0);      // Bulletins trouvés
        $table->enum('pv_source', ['operator', 'admin'])->default('operator');
        $table->text('pv_note')->nullable();               // Motif si saisie admin
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bureau_statistics');
    }
};
