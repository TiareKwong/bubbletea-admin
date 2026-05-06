<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $existing = collect(DB::select("SHOW INDEX FROM `orders`"))->pluck('Key_name')->toArray();

        Schema::table('orders', function (Blueprint $table) use ($existing) {
            if (! in_array('orders_order_status_index', $existing)) {
                $table->index('order_status');
            }
            if (! in_array('orders_payment_method_index', $existing)) {
                $table->index('payment_method');
            }
            if (! in_array('orders_collected_index', $existing)) {
                $table->index('collected');
            }
            if (! in_array('orders_created_at_index', $existing)) {
                $table->index('created_at');
            }
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
