<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('pay_runs', function (Blueprint $table) {
            $table->id();
            $table->date('week_start');
            $table->date('week_end');
            $table->enum('status', ['draft', 'paid'])->default('draft');
            $table->timestamp('paid_at')->nullable();
            $table->text('notes')->nullable();
            $table->string('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('pay_run_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pay_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('hourly_rate', 8, 2);
            $table->decimal('total_hours', 6, 2)->default(0);
            $table->decimal('gross_pay', 10, 2)->default(0);
            $table->decimal('employee_kpf', 10, 2)->default(0);
            $table->decimal('employer_kpf', 10, 2)->default(0);
            $table->decimal('net_pay', 10, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pay_run_entries');
        Schema::dropIfExists('pay_runs');
    }
};
