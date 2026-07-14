<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // 1. Ajout de is_restored dans vote_logs
        Schema::table('vote_logs', function (Blueprint $table) {
            $table->boolean('is_restored')->default(false)->after('is_procuration');
        });

        // 2. Ajout de is_restored dans bulletin_logs
        Schema::table('bulletin_logs', function (Blueprint $table) {
            $table->boolean('is_restored')->default(false)->after('is_manuel');
        });

        // 3. Ajout de restored_at dans vote_resets (pour tracker si un reset a été réactivé)
        Schema::table('vote_resets', function (Blueprint $table) {
            $table->timestamp('restored_at')->nullable()->after('reason');
        });
    }

    public function down()
    {
        Schema::table('vote_logs', function (Blueprint $table) {
            $table->dropColumn('is_restored');
        });

        Schema::table('bulletin_logs', function (Blueprint $table) {
            $table->dropColumn('is_restored');
        });

        Schema::table('vote_resets', function (Blueprint $table) {
            $table->dropColumn('restored_at');
        });
    }
};