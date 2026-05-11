<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_cup_logs', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('cup_type_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('opening')->nullable();
            $table->unsignedInteger('closing')->nullable();
            $table->unsignedInteger('reusable_returns')->default(0);
            $table->string('logged_by', 100)->nullable();
            $table->timestamps();
            $table->unique(['date', 'branch_id', 'cup_type_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_cup_logs');
    }
};
