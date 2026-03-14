<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profit_loss_settings', function (Blueprint $table) {
            $table->id();
            $table->smallInteger('year')->unsigned()->unique();
            $table->decimal('tax_rate', 5, 2)->default(23.00); // Iedzīvotāja ienākuma nodokļa likme %
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profit_loss_settings');
    }
};
