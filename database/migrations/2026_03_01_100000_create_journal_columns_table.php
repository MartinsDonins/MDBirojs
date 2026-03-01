<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_columns', function (Blueprint $table) {
            $table->id();
            $table->string('group', 20);        // 'income' | 'expense'
            $table->string('name', 100);         // full name, e.g. "Saimnieciskā darbība"
            $table->string('abbr', 30);          // abbreviation, e.g. "Saimn.darb."
            $table->jsonb('vid_columns');         // array of VID column numbers, e.g. [4,5,6]
            $table->boolean('is_visible')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // Default income columns
        DB::table('journal_columns')->insert([
            [
                'group'      => 'income',
                'name'       => 'Saimnieciskā darbība',
                'abbr'       => 'Saimn.darb.',
                'vid_columns'=> json_encode([4, 5, 6]),
                'is_visible' => true,
                'sort_order' => 10,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'group'      => 'income',
                'name'       => 'Neapliekamie',
                'abbr'       => 'Neapl.',
                'vid_columns'=> json_encode([10]),
                'is_visible' => true,
                'sort_order' => 20,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'group'      => 'income',
                'name'       => 'Nav attiecināmi',
                'abbr'       => 'Nav att.',
                'vid_columns'=> json_encode([8]),
                'is_visible' => true,
                'sort_order' => 30,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        // Default expense columns
        DB::table('journal_columns')->insert([
            [
                'group'      => 'expense',
                'name'       => 'Saistīti ar saimniecisko darbību',
                'abbr'       => 'Saist.SD',
                'vid_columns'=> json_encode([19, 20, 21, 22, 23]),
                'is_visible' => true,
                'sort_order' => 10,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'group'      => 'expense',
                'name'       => 'Nesaistīti ar saimniecisko darbību',
                'abbr'       => 'Nesaist.',
                'vid_columns'=> json_encode([18]),
                'is_visible' => true,
                'sort_order' => 20,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'group'      => 'expense',
                'name'       => 'Nav attiecināmi',
                'abbr'       => 'Nav att.',
                'vid_columns'=> json_encode([16]),
                'is_visible' => true,
                'sort_order' => 30,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_columns');
    }
};
