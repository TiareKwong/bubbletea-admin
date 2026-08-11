<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('timesheets', function (Blueprint $table) {
            // The composite unique index also backs the user_id foreign key,
            // so a plain index on user_id must exist before it can be dropped.
            $table->index('user_id');
            $table->dropUnique(['user_id', 'work_date']);
        });
    }

    public function down(): void
    {
        Schema::table('timesheets', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
            $table->unique(['user_id', 'work_date']);
        });
    }
};
