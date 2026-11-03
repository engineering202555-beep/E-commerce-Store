<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('in_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('table_layout_id')->constrained('table_layouts');
            $table->foreignId('waiter_id')->constrained('waiters');
            $table->enum('status', ['pending', 'done'])->default('pending');
            $table->double('total_price');
            $table->boolean('payment_completed')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('in_orders');
    }
};
