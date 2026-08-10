<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bureaux_vote', function (Blueprint $table) {
            // Validation "de confirmation" par un admin, distincte du statut piloté par
            // l'opérateur (status). Purement déclarative : elle ne modifie aucun chiffre,
            // elle trace juste qu'un admin a relu et confirmé un bureau déjà validé.
            $table->foreignId('admin_validated_by')
                ->nullable()
                ->after('is_procuration')
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('admin_validated_at')
                ->nullable()
                ->after('admin_validated_by');
        });
    }

    public function down(): void
    {
        Schema::table('bureaux_vote', function (Blueprint $table) {
            $table->dropConstrainedForeignId('admin_validated_by');
            $table->dropColumn('admin_validated_at');
        });
    }
};
