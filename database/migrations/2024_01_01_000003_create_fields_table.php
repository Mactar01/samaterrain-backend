<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('owners')->cascadeOnDelete();
            $table->string('name', 200);
            $table->text('description')->nullable();
            $table->string('address', 300);
            $table->string('city', 100);
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->enum('type', [
                'natural_grass',
                'artificial_grass',
                'concrete',
                'futsal',
                'beach'
            ])->default('artificial_grass');
            $table->unsignedTinyInteger('capacity')->default(10);
            $table->string('size', 50)->nullable(); // ex: "40x20m"
            $table->decimal('price_per_hour', 10, 2);
            $table->string('currency', 5)->default('XOF'); // FCFA
            $table->json('amenities')->nullable(); // ["douche","vestiaire","éclairage","parking"]
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->timestamps();

            $table->index(['latitude', 'longitude']);
            $table->index('city');
            $table->index('owner_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fields');
    }
};
