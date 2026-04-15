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
        Schema::table('orders', function (Blueprint $table) {
            $table->index('order_status');
            $table->index('payment_method');
            $table->index('collected');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['order_status']);
            $table->dropIndex(['payment_method']);
            $table->dropIndex(['collected']);
            $table->dropIndex(['created_at']);
        });
    }
};
