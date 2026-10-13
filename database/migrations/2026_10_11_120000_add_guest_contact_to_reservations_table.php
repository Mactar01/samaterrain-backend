<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Réservations manuelles (client au téléphone / sur place) : on garde le nom et le numéro
 * du client sur la réservation au lieu de créer un compte joueur à son nom.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->string('guest_name', 100)->nullable()->after('user_id');
            $table->string('guest_phone', 20)->nullable()->after('guest_name');
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn(['guest_name', 'guest_phone']);
        });
    }
};
