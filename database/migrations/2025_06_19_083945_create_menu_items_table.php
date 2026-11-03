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
        Schema::create('menu_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_category_id')->constrained('menu_categories');
            $table->string('name');
            $table->boolean('is_available')->default(true);
            $table->string('image');
            $table->double('price');
            $table->string('description')->nullable();
            $table->unsignedInteger('preparation_time')->nullable();
            $table->double('gifted_loyalty_points')->nullable();
            $table->double('meal_price_in_points')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('menu_items');
    }
};
