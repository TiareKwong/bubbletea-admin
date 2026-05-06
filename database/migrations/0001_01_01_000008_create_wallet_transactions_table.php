<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // wallet_transactions has no auto-managed timestamps ($timestamps = false)
        // but does have a created_at column set manually on insert
        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->decimal('amount', 10, 2);
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->string('actioned_by')->nullable();
            $table->timestamp('removed_at')->nullable();
            $table->string('removed_by')->nullable();
            $table->text('removal_reason')->nullable();
            $table->timestamp('created_at')->nullable()->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_transactions');
    }
};
