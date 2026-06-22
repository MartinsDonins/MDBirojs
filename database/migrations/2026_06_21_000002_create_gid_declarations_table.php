<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-year storage for the full annual income declaration (Gada ienākumu deklarācija)
 * of a self-employed person.
 *
 *  - data      : manual / EDS-adopted field values, keyed by GID field key
 *                (e.g. {"d1_employment_income": 12000.00}). Journal- and tax-derived
 *                fields are computed live and are NOT stored here.
 *  - eds_data  : the last imported EDS declaration flattened to {path => value},
 *                used for the comparison view.
 *  - eds_meta  : {filename, imported_at} for the imported EDS file.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gid_declarations', function (Blueprint $table) {
            $table->id();
            $table->smallInteger('year')->unsigned()->unique();
            $table->jsonb('data')->nullable();      // manual / adopted field values
            $table->jsonb('eds_data')->nullable();  // flattened EDS XML (path => value)
            $table->jsonb('eds_meta')->nullable();  // {filename, imported_at}
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gid_declarations');
    }
};
