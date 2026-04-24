<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->enum('reimbursement_status', ['unpaid', 'reimbursed'])->nullable()->after('branch_id');
            $table->unsignedBigInteger('reimbursement_payment_id')->nullable()->after('reimbursement_status');
            $table->foreign('reimbursement_payment_id')
                ->references('id')->on('reimbursement_payments')
                ->nullOnDelete();
        });

        // Mark any existing own_money expenses as unpaid.
        \Illuminate\Support\Facades\DB::statement("
            UPDATE expenses SET reimbursement_status = 'unpaid'
            WHERE paid_from = 'own_money' AND reimbursement_status IS NULL
        ");
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropForeign(['reimbursement_payment_id']);
            $table->dropColumn(['reimbursement_status', 'reimbursement_payment_id']);
        });
    }
};
