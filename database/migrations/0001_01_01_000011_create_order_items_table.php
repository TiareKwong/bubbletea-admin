<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // order_items has no timestamp columns (per OrderItem model)
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('flavor_id')->nullable()->constrained()->nullOnDelete();
            $table->string('size');
            $table->string('ice')->nullable();
            $table->string('sugar')->nullable();
            $table->json('toppings')->nullable();
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->decimal('price', 10, 2)->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
