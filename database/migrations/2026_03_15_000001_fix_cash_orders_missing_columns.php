<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Use raw PostgreSQL DDL to avoid hasColumn false-positive issues.
        // ADD COLUMN IF NOT EXISTS is idempotent — safe to run multiple times.
        DB::statement('ALTER TABLE cash_orders ADD COLUMN IF NOT EXISTS amount DECIMAL(15,2) NOT NULL DEFAULT 0');
        DB::statement('ALTER TABLE cash_orders ADD COLUMN IF NOT EXISTS date DATE');
        DB::statement('ALTER TABLE cash_orders ADD COLUMN IF NOT EXISTS currency VARCHAR(3) NOT NULL DEFAULT \'EUR\'');
        DB::statement('ALTER TABLE cash_orders ADD COLUMN IF NOT EXISTS basis TEXT');
        DB::statement('ALTER TABLE cash_orders ADD COLUMN IF NOT EXISTS person VARCHAR(255)');
        DB::statement('ALTER TABLE cash_orders ADD COLUMN IF NOT EXISTS notes TEXT');
    }

    public function down(): void
    {
        // Intentionally left empty — dropping columns is destructive.
    }
};
