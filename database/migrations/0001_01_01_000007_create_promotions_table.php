<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // promotions table has created_at but no updated_at (per Promotion model)
        Schema::create('promotions', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('image_url')->nullable();
            $table->string('status')->default('active');
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->string('type')->default('buy_x_get_y_free');
            $table->unsignedTinyInteger('buy_quantity')->nullable();
            $table->unsignedTinyInteger('free_quantity')->nullable();
            $table->string('free_item_size')->default('Any');
            $table->string('free_item_category')->nullable();
            $table->decimal('discount_percent', 5, 2)->nullable();
            $table->string('applies_to')->default('all');
            $table->string('target_category')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotions');
    }
};
