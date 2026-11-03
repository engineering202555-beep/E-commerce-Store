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
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('in_order_id')->nullable()->constrained('in_orders');
            $table->foreignId('delivery_order_id')->nullable()->constrained('delivery_orders');
            $table->foreignId('favourite_id')->nullable()->constrained('favourites');
            $table->foreignId('menu_item_id')->constrained('menu_items');
            $table->foreignId('chef_id')->nullable()->constrained('chefs');
            $table->enum('status', ['pending','preparing','done','cancelled',])->default('pending');
            $table->string('notes')->nullable();
            $table->integer('quantity');
            $table->enum('order_type', ['in', 'delivery']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
