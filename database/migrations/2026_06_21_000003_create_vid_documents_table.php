<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-year register of documents submitted to the VID EDS system
 * (e.g. "IIN avansa maksājumu aprēķins no saimnieciskās darbības").
 *
 * Each row tracks one submitted document, its EDS status (code + label,
 * e.g. "05" / "Pieņemts"), the submission date and free-form notes/link.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vid_documents', function (Blueprint $table) {
            $table->id();
            $table->smallInteger('year')->unsigned()->index();

            // EDS status: short code (e.g. "05") + human label (e.g. "Pieņemts")
            $table->string('status_code', 10)->nullable();
            $table->string('status', 50)->nullable();

            $table->string('document_name');

            $table->date('submitted_at')->nullable();

            $table->text('notes')->nullable();
            $table->string('link')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vid_documents');
    }
};
