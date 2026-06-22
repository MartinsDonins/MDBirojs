<?php

namespace App\Console\Commands;

use App\Models\GidDocument;
use App\Services\Gid\GidDeclarationService;
use Illuminate\Console\Command;

/**
 * Bulk-imports filed EDS documents (GID XML/HTML/PDF and IIN XML) from a folder into
 * the per-year document library, so the user does not have to upload each by hand.
 *
 * Each file is classified and filed under its tax year automatically; GID XML also
 * populates the comparison data + field auto-mapping. Re-running replaces same-named
 * files (idempotent).
 *
 * Default source folder is BRAIN/ (where the sample exports live); pass another
 * directory as the first argument to import from elsewhere.
 */
class ImportGidEds extends Command
{
    protected $signature = 'gid:import-eds {dir? : Mape ar EDS failiem (noklusē BRAIN/)} {--dry-run : Tikai parādīt, neko nesaglabāt}';

    protected $description = 'Importē EDS dokumentus (GID XML/HTML/PDF, IIN XML) dokumentu bibliotēkā un sasaista laukus';

    public function handle(GidDeclarationService $service): int
    {
        $dir = $this->argument('dir') ?: base_path('BRAIN');

        $files = collect(['GID-*.xml', 'GID-*.html', 'GID-*.htm', 'GID-*.pdf', 'IIN-*.xml'])
            ->flatMap(fn ($pattern) => glob(rtrim($dir, '/\\').'/'.$pattern) ?: [])
            ->unique()
            ->sort()
            ->values();

        if ($files->isEmpty()) {
            $this->warn("Nav atrasti EDS faili mapē: {$dir}");

            return self::SUCCESS;
        }

        $dry = (bool) $this->option('dry-run');
        $imported = 0;

        foreach ($files as $file) {
            $name = basename($file);
            $kind = GidDeclarationService::detectKind($file, $name);

            if ($dry) {
                $this->line(sprintf('• %s → %s', $name, GidDocument::KINDS[$kind][0] ?? $kind));

                continue;
            }

            try {
                $doc = $service->storeFromPath($file, $name);
                $imported++;
                $this->info(sprintf('✓ %s → %d. gads · %s', $name, $doc->year, $doc->kindLabel()));
            } catch (\Throwable $e) {
                $this->warn(sprintf('⚠️  %s: %s', $name, $e->getMessage()));
            }
        }

        if (! $dry) {
            $this->newLine();
            $this->info("Importēti {$imported} dokumenti. Kopā bibliotēkā: ".GidDocument::count());
        }

        return self::SUCCESS;
    }
}
