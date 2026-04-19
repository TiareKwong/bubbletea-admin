<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('flavors', function (Blueprint $table) {
            $table->string('type', 50)->default('drink')->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('flavors', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
