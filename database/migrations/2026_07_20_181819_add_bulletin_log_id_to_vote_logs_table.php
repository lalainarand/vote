<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vote_logs', function (Blueprint $table) {
            $table->foreignId('bulletin_log_id')
                ->nullable()
                ->constrained('bulletin_logs')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('vote_logs', function (Blueprint $table) {
            $table->dropForeign(['bulletin_log_id']);
            $table->dropColumn('bulletin_log_id');
        });
    }
};