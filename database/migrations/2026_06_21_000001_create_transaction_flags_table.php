<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transaction_flags', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('color', 32)->default('#ef4444');
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('flag_transaction', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained()->cascadeOnDelete();
            $table->foreignId('transaction_flag_id')->constrained()->cascadeOnDelete();
            $table->unique(['transaction_id', 'transaction_flag_id']);
        });

        // Seed 5 default work flags (idempotent: only when table is empty).
        if (DB::table('transaction_flags')->count() === 0) {
            $now = now();
            $defaults = [
                ['name' => 'Pārbaudīts',             'color' => '#22c55e', 'sort_order' => 1],
                ['name' => 'Jāpārbauda',             'color' => '#eab308', 'sort_order' => 2],
                ['name' => 'Jāsalabo pretdarījums',  'color' => '#ef4444', 'sort_order' => 3],
                ['name' => 'Jautājums grāmatvedim',  'color' => '#3b82f6', 'sort_order' => 4],
                ['name' => 'Svarīgs',                'color' => '#a855f7', 'sort_order' => 5],
            ];
            foreach ($defaults as $d) {
                DB::table('transaction_flags')->insert($d + ['created_at' => $now, 'updated_at' => $now]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('flag_transaction');
        Schema::dropIfExists('transaction_flags');
    }
};
