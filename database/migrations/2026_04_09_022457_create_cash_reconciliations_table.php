<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_reconciliations', function (Blueprint $table) {
            $table->id();
            $table->date('reconciliation_date')->unique();
            $table->decimal('expected_cash', 10, 2);
            $table->decimal('actual_cash', 10, 2);
            $table->decimal('difference', 10, 2);  // actual − expected
            $table->text('notes')->nullable();
            $table->string('submitted_by', 100);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_reconciliations');
    }
};
