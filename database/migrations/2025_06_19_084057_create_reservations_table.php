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
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers');
            $table->foreignId('table_layout_id')->constrained('table_layouts');
            $table->dateTime('start_at');
            $table->dateTime('end_at');
            $table->integer('number_of_people');
            $table->string('notes')->nullable();
            $table->enum('status', ['reserved','done', 'arrived', 'cancelled'])->default('reserved');
            $table->enum('cancelled_by', ['customer', 'system'])->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reservations');
    }
};
