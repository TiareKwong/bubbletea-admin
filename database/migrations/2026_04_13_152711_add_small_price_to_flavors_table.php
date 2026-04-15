<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('flavors', function (Blueprint $table) {
            $table->decimal('small_price', 5, 2)->nullable()->after('large_price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('flavors', function (Blueprint $table) {
            $table->dropColumn('small_price');
        });
    }
};
