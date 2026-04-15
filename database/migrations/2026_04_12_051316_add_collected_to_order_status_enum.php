<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE orders MODIFY COLUMN order_status ENUM(
            'Pending Payment',
            'Payment Verification',
            'Points Verification',
            'Paid',
            'Preparing',
            'Ready',
            'Collected',
            'Cancelled'
        ) NOT NULL DEFAULT 'Pending Payment'");

        // Fix existing orders where collected=1 but status wasn't updated
        DB::table('orders')
            ->where('collected', 1)
            ->where('order_status', '!=', 'Cancelled')
            ->update(['order_status' => 'Collected']);
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE orders MODIFY COLUMN order_status ENUM(
            'Pending Payment',
            'Payment Verification',
            'Points Verification',
            'Paid',
            'Preparing',
            'Ready',
            'Cancelled'
        ) NOT NULL DEFAULT 'Pending Payment'");
    }
};
