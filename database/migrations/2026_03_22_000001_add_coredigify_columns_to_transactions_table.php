<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE transactions ADD COLUMN IF NOT EXISTS coredigify_sent_at TIMESTAMP NULL');
        DB::statement('ALTER TABLE transactions ADD COLUMN IF NOT EXISTS coredigify_sync_error TEXT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE transactions DROP COLUMN IF EXISTS coredigify_sent_at');
        DB::statement('ALTER TABLE transactions DROP COLUMN IF EXISTS coredigify_sync_error');
    }
};
