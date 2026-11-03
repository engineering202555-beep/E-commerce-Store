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
        Schema::create('table_layouts', function (Blueprint $table) {
            $table->id();
            $table->integer('number_of_chairs');
            $table->enum('status', ['empty','busy', 'reserved'])->default('empty');
            $table->integer('x_position')->default(0);
            $table->integer('y_position')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('table_layouts');
    }
};
