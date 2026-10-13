<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('field_id')->constrained('fields')->cascadeOnDelete();
            $table->foreignId('reservation_id')->nullable()->constrained('reservations')->nullOnDelete();
            $table->foreignId('organizer_id')->constrained('users')->cascadeOnDelete();
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->date('date');
            $table->time('start_time');
            $table->unsignedTinyInteger('max_players')->default(10);
            $table->enum('level', ['beginner', 'intermediate', 'advanced', 'mixed'])->default('mixed');
            $table->enum('status', ['open', 'full', 'ongoing', 'finished', 'cancelled'])->default('open');
            $table->boolean('is_public')->default(true);
            $table->timestamps();

            $table->index('date');
        });

        Schema::create('match_players', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->constrained('matches')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('status', ['confirmed', 'waitlisted', 'cancelled'])->default('confirmed');
            $table->timestamp('joined_at')->useCurrent();

            $table->unique(['match_id', 'user_id'], 'uq_match_player');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('match_players');
        Schema::dropIfExists('matches');
    }
};
