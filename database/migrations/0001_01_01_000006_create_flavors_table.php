<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // flavors table has created_at but no updated_at (per Flavor model)
        Schema::create('flavors', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type', 50)->default('drink');
            $table->string('category')->nullable();
            $table->string('image_url')->nullable();
            $table->string('status')->default('Available');
            $table->decimal('small_price', 5, 2)->nullable();
            $table->unsignedSmallInteger('small_ml')->nullable();
            $table->decimal('regular_price', 5, 2)->nullable();
            $table->unsignedSmallInteger('regular_ml')->nullable();
            $table->decimal('large_price', 5, 2)->nullable();
            $table->unsignedSmallInteger('large_ml')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flavors');
    }
};
