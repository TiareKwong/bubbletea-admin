<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('promotions', function (Blueprint $table) {
            $table->string('type')->default('buy_x_get_y_free')->after('valid_until');
            $table->unsignedTinyInteger('buy_quantity')->nullable()->after('type');
            $table->unsignedTinyInteger('free_quantity')->nullable()->after('buy_quantity');
            $table->decimal('discount_percent', 5, 2)->nullable()->after('free_quantity');
            $table->string('applies_to')->default('all')->after('discount_percent');
            $table->string('target_category')->nullable()->after('applies_to');
        });
    }

    public function down(): void
    {
        Schema::table('promotions', function (Blueprint $table) {
            $table->dropColumn(['type', 'buy_quantity', 'free_quantity', 'discount_percent', 'applies_to', 'target_category']);
        });
    }
};
