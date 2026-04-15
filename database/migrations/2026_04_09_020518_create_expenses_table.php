<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->string('description');
            $table->string('category', 50);
            $table->decimal('amount', 10, 2);
            $table->string('purchased_by', 100);
            $table->date('expense_date');
            $table->text('notes')->nullable();
            $table->string('created_by', 100);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
