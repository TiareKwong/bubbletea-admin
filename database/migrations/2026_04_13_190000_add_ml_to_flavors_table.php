<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('flavors', function (Blueprint $table) {
            $table->unsignedSmallInteger('small_ml')->nullable()->after('small_price');
            $table->unsignedSmallInteger('regular_ml')->nullable()->after('regular_price');
            $table->unsignedSmallInteger('large_ml')->nullable()->after('large_price');
        });
    }

    public function down(): void
    {
        Schema::table('flavors', function (Blueprint $table) {
            $table->dropColumn(['small_ml', 'regular_ml', 'large_ml']);
        });
    }
};
