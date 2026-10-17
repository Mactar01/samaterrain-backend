<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        try {
            if (Schema::hasTable('users') && Schema::hasColumn('users', 'avatar')) {
                DB::statement('ALTER TABLE users MODIFY avatar LONGTEXT NULL');
            }
        } catch (\Throwable $e) {
            // Ignore if already modified or incompatible driver
        }

        try {
            if (Schema::hasTable('fields') && Schema::hasColumn('fields', 'photo')) {
                DB::statement('ALTER TABLE fields MODIFY photo LONGTEXT NULL');
            }
        } catch (\Throwable $e) {
            // Ignore
        }

        try {
            if (Schema::hasTable('field_images') && Schema::hasColumn('field_images', 'url')) {
                DB::statement('ALTER TABLE field_images MODIFY url LONGTEXT NOT NULL');
            }
        } catch (\Throwable $e) {
            // Ignore
        }
    }

    public function down(): void
    {
        // Non-destructive rollback
    }
};
