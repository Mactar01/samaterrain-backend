<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('owner_collaborators') && !Schema::hasColumn('owner_collaborators', 'additional_info')) {
            try {
                Schema::table('owner_collaborators', function (Blueprint $table) {
                    $table->json('additional_info')->nullable();
                });
            } catch (\Throwable $e) {
                // Ignore duplicate column error if concurrent migration already added it
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('owner_collaborators') && Schema::hasColumn('owner_collaborators', 'additional_info')) {
            Schema::table('owner_collaborators', function (Blueprint $table) {
                $table->dropColumn('additional_info');
            });
        }
    }
};
