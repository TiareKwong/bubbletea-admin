<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_cup_logs', function (Blueprint $table) {
            $table->string('opening_by')->nullable()->after('opening');
            $table->string('closing_by')->nullable()->after('closing');
        });
    }

    public function down(): void
    {
        Schema::table('daily_cup_logs', function (Blueprint $table) {
            $table->dropColumn(['opening_by', 'closing_by']);
        });
    }
};
