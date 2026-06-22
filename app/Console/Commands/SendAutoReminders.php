<?php

namespace App\Console\Commands;

use App\Models\MaintenancePlan;
use App\Models\Vehicle;
use App\Services\TelegramService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

/**
 * Apkopo "Auto" sadaļas atgādinājumus un nosūta tos pa e-pastu un Telegram.
 *
 * Atgādina par:
 *   - apkopēm, kas nokavētas vai drīz pienākas (apkopju plāns),
 *   - OCTA / tehniskās apskates termiņiem, kas drīz beidzas vai jau beigušies.
 *
 * Plānots reizi dienā (skat. routes/console.php).
 */
class SendAutoReminders extends Command
{
    protected $signature = 'auto:reminders {--dry-run : Tikai parādīt ziņu, nesūtīt}';

    protected $description = 'Nosūta auto apkopju un dokumentu termiņu atgādinājumus (e-pasts + Telegram)';

    public function handle(TelegramService $telegram): int
    {
        if (! config('services.auto.reminders_enabled')) {
            $this->info('Auto atgādinājumi ir atspējoti (AUTO_REMINDERS_ENABLED=false).');

            return self::SUCCESS;
        }

        $soonDays = (int) config('services.auto.soon_days', 30);
        $lines = array_merge(
            $this->maintenanceLines(),
            $this->documentLines($soonDays),
        );

        if (empty($lines)) {
            $this->info('Nav atgādinājumu — viss kārtībā.');

            return self::SUCCESS;
        }

        $heading = '🚗 Auto atgādinājumi ('.Carbon::today()->format('d.m.Y').')';
        $plain = $heading."\n\n".implode("\n", $lines);

        if ($this->option('dry-run')) {
            $this->line($plain);

            return self::SUCCESS;
        }

        $this->sendEmail($heading, $plain);
        $this->sendTelegram($telegram, $heading, $lines);

        $this->info('Atgādinājumi nosūtīti ('.count($lines).' ieraksti).');

        return self::SUCCESS;
    }

    /** Apkopju plāna rindas (nokavētas / drīz). */
    private function maintenanceLines(): array
    {
        $plans = MaintenancePlan::where('is_active', true)
            ->with('vehicle')
            ->get()
            ->filter(fn (MaintenancePlan $p) => in_array($p->due_status, ['overdue', 'soon'], true))
            ->sortBy(fn (MaintenancePlan $p) => $p->due_status === 'overdue' ? 0 : 1);

        $lines = [];

        foreach ($plans as $plan) {
            $icon = $plan->due_status === 'overdue' ? '⚠️' : '🔧';
            $detail = [];

            if ($plan->km_remaining !== null) {
                $detail[] = $plan->km_remaining < 0
                    ? 'nokavēts '.number_format(abs($plan->km_remaining), 0, ',', ' ').' km'
                    : 'pēc '.number_format($plan->km_remaining, 0, ',', ' ').' km';
            }
            if ($plan->next_due_date !== null) {
                $detail[] = 'līdz '.$plan->next_due_date->format('d.m.Y');
            }

            $lines[] = sprintf(
                '%s %s — %s (%s)',
                $icon,
                $plan->vehicle?->display_name ?? 'Auto',
                $plan->title,
                implode(', ', $detail) ?: MaintenancePlan::dueStatusLabel($plan->due_status),
            );
        }

        return $lines;
    }

    /** OCTA / tehniskās apskates termiņu rindas. */
    private function documentLines(int $soonDays): array
    {
        $today = Carbon::today();
        $threshold = $today->copy()->addDays($soonDays);
        $lines = [];

        $docs = [
            'insurance_expires_at' => 'OCTA',
            'casco_expires_at' => 'KASKO',
            'inspection_expires_at' => 'Tehniskā apskate',
        ];

        foreach (Vehicle::where('is_active', true)->get() as $vehicle) {
            foreach ($docs as $field => $label) {
                $date = $vehicle->$field;

                if (! $date || $date->gt($threshold)) {
                    continue;
                }

                if ($date->lt($today)) {
                    $lines[] = sprintf('⛔ %s — %s beidzās %s', $vehicle->display_name, $label, $date->format('d.m.Y'));
                } else {
                    $days = (int) $today->diffInDays($date);
                    $lines[] = sprintf('📅 %s — %s beidzas %s (pēc %d d.)', $vehicle->display_name, $label, $date->format('d.m.Y'), $days);
                }
            }
        }

        return $lines;
    }

    private function sendEmail(string $heading, string $body): void
    {
        $to = config('services.auto.reminder_email') ?: config('mail.from.address');

        if (blank($to)) {
            $this->warn('Nav norādīts saņēmēja e-pasts — e-pasts izlaists.');

            return;
        }

        Mail::raw($body, function ($message) use ($to, $heading) {
            $message->to($to)->subject($heading);
        });
    }

    private function sendTelegram(TelegramService $telegram, string $heading, array $lines): void
    {
        if (! $telegram->isConfigured()) {
            $this->warn('Telegram nav konfigurēts — izlaists.');

            return;
        }

        $message = '<b>'.e($heading).'</b>'."\n\n".e(implode("\n", $lines));
        $telegram->send($message);
    }
}
