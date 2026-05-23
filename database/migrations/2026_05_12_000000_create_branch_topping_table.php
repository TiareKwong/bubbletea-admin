<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Toppings with NO rows here = available at ALL branches.
    // Toppings WITH rows here = only available at the listed branches.
    public function up(): void
    {
        Schema::create('branch_topping', function (Blueprint $table) {
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('topping_id')->constrained()->cascadeOnDelete();
            $table->primary(['branch_id', 'topping_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_topping');
    }
};
