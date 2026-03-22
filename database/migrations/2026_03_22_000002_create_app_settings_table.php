<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        // Seed defaults
        $now = now();
        DB::table('app_settings')->insertOrIgnore([
            ['key' => 'coredigify_enabled',      'value' => '0',         'created_at' => $now, 'updated_at' => $now],
            ['key' => 'coredigify_api_url',       'value' => '',          'created_at' => $now, 'updated_at' => $now],
            ['key' => 'coredigify_api_key',       'value' => '',          'created_at' => $now, 'updated_at' => $now],
            ['key' => 'coredigify_incoming_key',  'value' => (string) Str::uuid(), 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('app_settings');
    }
};
