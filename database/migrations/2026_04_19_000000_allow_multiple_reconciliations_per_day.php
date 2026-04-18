<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cash_reconciliations', function (Blueprint $table) {
            $table->dropUnique(['reconciliation_date', 'payment_method']);
        });
    }

    public function down(): void
    {
        Schema::table('cash_reconciliations', function (Blueprint $table) {
            $table->unique(['reconciliation_date', 'payment_method']);
        });
    }
};
