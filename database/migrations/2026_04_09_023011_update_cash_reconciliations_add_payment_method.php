<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cash_reconciliations', function (Blueprint $table) {
            // Add payment_method after id
            $table->string('payment_method', 50)->after('id')->default('Cash');

            // Replace the single-column unique on reconciliation_date with a
            // composite unique so we store one row per method per day.
            $table->dropUnique(['reconciliation_date']);
            $table->unique(['reconciliation_date', 'payment_method']);
        });
    }

    public function down(): void
    {
        Schema::table('cash_reconciliations', function (Blueprint $table) {
            $table->dropUnique(['reconciliation_date', 'payment_method']);
            $table->unique('reconciliation_date');
            $table->dropColumn('payment_method');
        });
    }
};
