<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('time_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('field_id')->constrained('fields')->cascadeOnDelete();
            $table->date('date');
            $table->time('start_time');
            $table->time('end_time');
            $table->enum('status', ['available', 'reserved', 'blocked'])->default('available');
            $table->decimal('price', 10, 2)->nullable(); // Surcharge du prix par défaut du terrain
            $table->timestamps();

            // Un créneau unique par terrain, date et heure de début
            $table->unique(['field_id', 'date', 'start_time'], 'uq_time_slots');
            $table->index(['field_id', 'date'], 'idx_time_slots_field_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('time_slots');
    }
};
