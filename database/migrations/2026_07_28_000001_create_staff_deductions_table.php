<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('staff_deductions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->decimal('amount', 10, 2);
            $table->string('reason')->nullable();
            $table->string('source')->default('manual'); // 'cash_short' | 'manual'
            $table->foreignId('cash_reconciliation_id')->nullable()->constrained()->nullOnDelete();
            $table->string('created_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_deductions');
    }
};
