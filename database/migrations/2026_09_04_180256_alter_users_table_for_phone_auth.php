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
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
            
            // Si 'phone' n'est pas encore unique
            // Il faut faire attention si des entrÃ©es ont dÃ©jÃ  le mÃªme numÃ©ro ou null. 
            // On le met unique, et on ajoute les colonnes OTP.
            $table->string('phone')->nullable(false)->unique()->change();
            
            $table->string('otp_code', 10)->nullable()->after('password');
            $table->timestamp('otp_expires_at')->nullable()->after('otp_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable(false)->change();
            $table->dropUnique(['phone']);
            $table->string('phone')->nullable()->change();
            
            $table->dropColumn(['otp_code', 'otp_expires_at']);
        });
    }
};
