<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_item_id')->constrained()->cascadeOnDelete();
            $table->decimal('quantity', 10, 2);
            $table->decimal('initial_quantity', 10, 2);
            $table->date('expiry_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('received_at');
            $table->string('created_by');
            $table->timestamps();
        });

        // Migrate existing stock into a single opening batch per item
        $items = \DB::table('stock_items')->where('current_quantity', '>', 0)->get();
        foreach ($items as $item) {
            \DB::table('stock_batches')->insert([
                'stock_item_id'    => $item->id,
                'quantity'         => $item->current_quantity,
                'initial_quantity' => $item->current_quantity,
                'expiry_date'      => $item->nearest_expiry_date,
                'notes'            => null,
                'received_at'      => now(),
                'created_by'       => 'System',
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_batches');
    }
};
