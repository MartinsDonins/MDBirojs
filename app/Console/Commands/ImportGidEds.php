<?php

namespace App\Console\Commands;

use App\Models\GidDeclaration;
use App\Services\Gid\GidDeclarationService;
use Illuminate\Console\Command;

/**
 * Bulk-imports filed EDS GID declarations (GID-*.xml) as comparison data for the
 * annual declaration page, so the user does not have to upload each year by hand.
 *
 * For every file it stores the flattened EDS data + meta on the matching year and
 * auto-maps the D3 income/expense fields ({@see GidDeclarationService::applyAutoMap}).
 * Idempotent — re-running just refreshes the stored data.
 *
 * Default source folder is BRAIN/ (where the sample exports live); pass another
 * directory as the first argument to import from elsewhere.
 */
class ImportGidEds extends Command
{
    protected $signature = 'gid:import-eds {dir? : Mape ar GID-*.xml failiem (noklusē BRAIN/)} {--dry-run : Tikai parādīt, neko nesaglabāt}';

    protected $description = 'Importē EDS GID XML failus kā salīdzināšanas datus un sasaista D3 ieņēmumu/izdevumu laukus';

    public function handle(GidDeclarationService $service): int
    {
        $dir = $this->argument('dir') ?: base_path('BRAIN');
        $files = glob(rtrim($dir, '/\\').'/GID-*.xml') ?: [];

        if (empty($files)) {
            $this->warn("Nav atrasti GID-*.xml faili mapē: {$dir}");

            return self::SUCCESS;
        }

        sort($files);
        $dry = (bool) $this->option('dry-run');
        $imported = 0;

        foreach ($files as $file) {
            $name = basename($file);
            $flat = GidDeclarationService::withComputedTotals(GidDeclarationService::parseEdsXml($file));

            if (empty($flat)) {
                $this->warn("⚠️  {$name}: neizdevās nolasīt XML — izlaists.");

                continue;
            }

            $year = $this->resolveYear($flat, $name);
            if ($year === null) {
                $this->warn("⚠️  {$name}: neizdevās noteikt gadu — izlaists.");

                continue;
            }

            $map = GidDeclarationService::resolveAutoMap($flat);
            $mapInfo = $map
                ? collect($map)->map(fn ($p, $k) => $k.'→'.\Illuminate\Support\Str::afterLast($p, '/'))->implode(', ')
                : 'nav kartējuma';

            if ($dry) {
                $this->line(sprintf('• %s → %d. gads (%d lauki; %s)', $name, $year, count($flat), $mapInfo));

                continue;
            }

            $service->importEds($year, $file, $name);
            $imported++;
            $this->info(sprintf('✓ %s → %d. gads (%d lauki; %s)', $name, $year, count($flat), $mapInfo));
        }

        if (! $dry) {
            $this->newLine();
            $this->info("Importēti {$imported} GID EDS faili. Kopā ierakstu: ".GidDeclaration::count());
        }

        return self::SUCCESS;
    }

    /** Tax year from the EDS data (TaksGads), falling back to the filename. */
    private function resolveYear(array $flat, string $filename): ?int
    {
        foreach ($flat as $path => $val) {
            if (str_ends_with($path, '/TaksGads') && ctype_digit($val)) {
                return (int) $val;
            }
        }

        return preg_match('/(20\d{2})/', $filename, $m) ? (int) $m[1] : null;
    }
}
