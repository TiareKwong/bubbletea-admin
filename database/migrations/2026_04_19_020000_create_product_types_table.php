<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique();
            $table->timestamps();
        });

        // Seed the four default types
        DB::table('product_types')->insert([
            ['name' => 'drink',     'created_at' => now(), 'updated_at' => now()],
            ['name' => 'ice_cream', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'merch',     'created_at' => now(), 'updated_at' => now()],
            ['name' => 'carrier',   'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('product_types');
    }
};
