<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Uploaded EDS documents for the annual income declaration, kept per year so every
 * supporting file (the GID XML used for comparison, the human-readable HTML/PDF
 * printouts, and the IIN advance-tax XML) lives in one place and can be re-opened.
 *
 *  - kind  : gid_xml | gid_html | gid_pdf | iin_xml
 *  - path  : storage path (relative to the 'local' disk)
 *  - meta  : parsed summary for XML kinds (e.g. tax-year totals), null for HTML/PDF
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gid_documents', function (Blueprint $table) {
            $table->id();
            $table->smallInteger('year')->unsigned()->index();
            $table->string('kind', 20);
            $table->string('filename');
            $table->string('path');
            $table->string('mime')->nullable();
            $table->unsignedInteger('size')->default(0);
            $table->jsonb('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gid_documents');
    }
};
