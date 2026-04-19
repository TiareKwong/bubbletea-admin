<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Flavors with NO rows here = available at ALL branches.
    // Flavors WITH rows here = only available at the listed branches.
    public function up(): void
    {
        Schema::create('branch_flavor', function (Blueprint $table) {
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->integer('flavor_id');
            $table->foreign('flavor_id')->references('id')->on('flavors')->cascadeOnDelete();
            $table->primary(['branch_id', 'flavor_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_flavor');
    }
};
