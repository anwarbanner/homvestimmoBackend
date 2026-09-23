<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The original enum/CHECK constraint on "status" only ever got the
 * "deleted" value added on Postgres (see the 2026_09_14_225551 migration,
 * which is a no-op on every other driver). That left SQLite — used by the
 * whole test suite and CI — silently rejecting a "deleted" update that
 * works fine in production. A plain string column, validated at the
 * application level (already the only place this value is ever set), is
 * simpler and removes this drift risk for good.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE social_posts DROP CONSTRAINT IF EXISTS social_posts_status_check');
            DB::statement('ALTER TABLE social_posts ALTER COLUMN status TYPE VARCHAR(255)');
            DB::statement("ALTER TABLE social_posts ALTER COLUMN status SET DEFAULT 'queued'");

            return;
        }

        Schema::table('social_posts', function (Blueprint $table) {
            $table->string('status')->default('queued')->change();
        });
    }

    public function down(): void
    {
        // Not reverted to the old per-driver enum/CHECK constraint on purpose.
    }
};
