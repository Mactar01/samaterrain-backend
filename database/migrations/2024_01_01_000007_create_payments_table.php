<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained('reservations')->cascadeOnDelete();
            $table->decimal('amount', 10, 2);
            $table->string('currency', 5)->default('XOF');
            $table->enum('method', ['wave', 'orange_money', 'free_money', 'card', 'cash']);
            $table->enum('status', ['pending', 'completed', 'failed', 'refunded'])->default('pending');
            $table->string('transaction_ref', 200)->nullable(); // Référence du prestataire
            $table->timestamp('paid_at')->nullable();
            $table->json('metadata')->nullable(); // Données brutes du prestataire
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
