<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bureaux_vote', function (Blueprint $table) {
            $table->boolean('is_procuration')
                ->default(false);
               
        });
    }

    public function down(): void
    {
        Schema::table('bureaux_vote', function (Blueprint $table) {
            $table->dropColumn('is_procuration');
        });
    }
};