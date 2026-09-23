<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE social_posts DROP CONSTRAINT social_posts_status_check');
        DB::statement("ALTER TABLE social_posts ADD CONSTRAINT social_posts_status_check CHECK (status IN ('queued', 'published', 'failed', 'deleted'))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE social_posts DROP CONSTRAINT social_posts_status_check');
        DB::statement("ALTER TABLE social_posts ADD CONSTRAINT social_posts_status_check CHECK (status IN ('queued', 'published', 'failed'))");
    }
};
