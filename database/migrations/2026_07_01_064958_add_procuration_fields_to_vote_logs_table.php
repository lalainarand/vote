<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vote_logs', function (Blueprint $table) {
            $table->unsignedInteger('quantity')->default(1)->after('action');
            $table->boolean('is_procuration')->default(false)->after('quantity');
        });
    }

    public function down(): void
    {
        Schema::table('vote_logs', function (Blueprint $table) {
            $table->dropColumn(['quantity', 'is_procuration']);
        });
    }
};