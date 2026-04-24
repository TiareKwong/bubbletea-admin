<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reimbursement_payments', function (Blueprint $table) {
            $table->id();
            $table->string('staff_name');
            $table->decimal('amount', 10, 2);
            $table->string('payment_method'); // Cash, EFTPOS, Bank Transfer
            $table->date('payment_date');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->foreign('branch_id')->references('id')->on('branches')->nullOnDelete();
            $table->string('notes')->nullable();
            $table->string('created_by');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reimbursement_payments');
    }
};
