<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('total_price', 10, 2)->default(0);
            $table->string('payment_method');
            $table->string('order_code')->unique();
            $table->enum('order_status', [
                'Pending Payment',
                'Payment Verification',
                'Points Verification',
                'Paid',
                'Preparing',
                'Ready',
                'Collected',
                'Cancelled',
            ])->default('Pending Payment');
            $table->string('payment_reference')->nullable();
            $table->boolean('reward_redeemed')->default(false);
            $table->integer('points_used')->default(0);
            $table->integer('points_earned')->default(0);
            $table->boolean('collected')->default(false);
            $table->string('updated_by')->nullable();
            $table->decimal('discount_applied', 10, 2)->nullable();
            $table->string('promo_title')->nullable();
            $table->text('free_items')->nullable();
            $table->decimal('wallet_amount_used', 10, 2)->default(0);
            $table->timestamps();
            $table->index('order_status');
            $table->index('payment_method');
            $table->index('collected');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
