<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('profit_loss_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('profit_loss_settings', 'min_wage')) {
                // Minimālā alga (€/mēn.) — robeža starp samazināto un pilno VSAA
                $table->decimal('min_wage', 8, 2)->default(700.00)->after('tax_rate');
            }
            if (!Schema::hasColumn('profit_loss_settings', 'vsaa_full_rate')) {
                // Pilnā VSAA likme (%) — tiek pielietota uz min_wage daļu (ienākumi ≥ min_wage)
                $table->decimal('vsaa_full_rate', 5, 2)->default(31.07)->after('min_wage');
            }
            if (!Schema::hasColumn('profit_loss_settings', 'vsaa_reduced_rate')) {
                // Samazinātā VSAA likme (%) — tiek pielietota:
                //   a) uz visiem ienākumiem ja < min_wage
                //   b) uz pārsniegumu virs min_wage ja ienākumi ≥ min_wage
                $table->decimal('vsaa_reduced_rate', 5, 2)->default(10.00)->after('vsaa_full_rate');
            }
        });
    }

    public function down(): void
    {
        Schema::table('profit_loss_settings', function (Blueprint $table) {
            foreach (['min_wage', 'vsaa_full_rate', 'vsaa_reduced_rate'] as $col) {
                if (Schema::hasColumn('profit_loss_settings', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
