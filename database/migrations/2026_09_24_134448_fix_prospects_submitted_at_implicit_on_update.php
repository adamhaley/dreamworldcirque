<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `submitted_at` is a plain `TIMESTAMP NOT NULL` column with no explicit
     * default -- on MySQL with `explicit_defaults_for_timestamp` OFF (the
     * default on many hosts, including production here), the *first* such
     * column in a table is implicitly given `DEFAULT CURRENT_TIMESTAMP ON
     * UPDATE CURRENT_TIMESTAMP`, even though nothing in the migration or the
     * app asked for that. Every UPDATE to a prospects row -- a status change,
     * a reply edit, anything -- was silently resetting submitted_at to the
     * moment of that edit. Explicitly declaring a fixed (non-CURRENT_TIMESTAMP)
     * default suppresses MySQL's implicit rule; SQLite (used in tests) never
     * had this behavior, so there's nothing to fix there.
     */
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE prospects MODIFY submitted_at TIMESTAMP NOT NULL DEFAULT '1970-01-01 00:00:01'");
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('ALTER TABLE prospects MODIFY submitted_at TIMESTAMP NOT NULL');
    }
};
