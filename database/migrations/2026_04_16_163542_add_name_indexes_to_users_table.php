<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $existing = collect(\Illuminate\Support\Facades\DB::select("SHOW INDEX FROM `users`"))->pluck('Key_name')->toArray();

        Schema::table('users', function (Blueprint $table) use ($existing) {
            if (! in_array('users_first_name_index', $existing)) {
                $table->index('first_name');
            }
            if (! in_array('users_last_name_index', $existing)) {
                $table->index('last_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['first_name']);
            $table->dropIndex(['last_name']);
        });
    }
};
