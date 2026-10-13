<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('owners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('business_name', 200)->nullable();
            $table->string('address', 300)->nullable();
            $table->string('siret', 50)->nullable(); // NINEA/RCCM pour l'Afrique de l'Ouest
            $table->timestamp('verified_at')->nullable();
            $table->decimal('commission_rate', 5, 2)->default(10.00);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('owners');
    }
};
