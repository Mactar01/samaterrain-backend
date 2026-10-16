<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('owners') && !Schema::hasColumn('owners', 'settings')) {
            try {
                Schema::table('owners', function (Blueprint $table) {
                    $table->json('settings')->nullable();
                });
            } catch (\Throwable $e) {
                // Ignore duplicate column error if concurrent migration already added it
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('owners') && Schema::hasColumn('owners', 'settings')) {
            Schema::table('owners', function (Blueprint $table) {
                $table->dropColumn('settings');
            });
        }
    }
};
