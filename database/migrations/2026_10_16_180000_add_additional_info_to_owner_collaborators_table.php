<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('owner_collaborators', function (Blueprint $table) {
            $table->json('additional_info')->nullable()->after('permissions');
        });
    }

    public function down(): void
    {
        Schema::table('owner_collaborators', function (Blueprint $table) {
            $table->dropColumn('additional_info');
        });
    }
};
