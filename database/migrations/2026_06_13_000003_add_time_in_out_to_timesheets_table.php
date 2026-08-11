<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('timesheets', function (Blueprint $table) {
            $table->time('time_in')->nullable()->after('work_date');
            $table->time('time_out')->nullable()->after('time_in');
        });
    }

    public function down(): void
    {
        Schema::table('timesheets', function (Blueprint $table) {
            $table->dropColumn(['time_in', 'time_out']);
        });
    }
};
