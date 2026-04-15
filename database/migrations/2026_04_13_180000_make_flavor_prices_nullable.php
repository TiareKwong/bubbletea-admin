<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('flavors', function (Blueprint $table) {
            $table->decimal('regular_price', 5, 2)->nullable()->default(null)->change();
            $table->decimal('large_price', 5, 2)->nullable()->default(null)->change();
        });
    }

    public function down(): void
    {
        Schema::table('flavors', function (Blueprint $table) {
            $table->decimal('regular_price', 5, 2)->nullable(false)->default(0.00)->change();
            $table->decimal('large_price', 5, 2)->nullable(false)->default(0.00)->change();
        });
    }
};
