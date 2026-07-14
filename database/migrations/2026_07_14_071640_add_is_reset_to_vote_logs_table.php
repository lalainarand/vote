<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vote_logs', function (Blueprint $table) {
            $table->boolean('is_reset')->default(false)->after('is_procuration');
        });
    }

    public function down(): void
    {
        Schema::table('vote_logs', function (Blueprint $table) {
            $table->dropColumn('is_reset');
        });
    }
};