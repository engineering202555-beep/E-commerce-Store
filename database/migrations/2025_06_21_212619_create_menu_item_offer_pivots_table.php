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
        Schema::create('menu_item_offer_pivots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_item_id')->constrained();
            $table->foreignId('offer_id')->constrained();
            $table->decimal('discounted_price', 8, 2);   //8 the total numbers for digits before and after(.) + 2 the number for digits after(.)
            $table->unsignedInteger('quantity');     //example 2 from pizza
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('menu_item_offer_pivots');
    }
};
