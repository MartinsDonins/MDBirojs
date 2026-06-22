<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Plāns Telegram paziņojumu sūtīšanai caur Bot API.
 *
 * Konfigurācija (config/services.php → telegram):
 *   TELEGRAM_BOT_TOKEN — bota žetons no @BotFather
 *   TELEGRAM_CHAT_ID   — saņēmēja vai grupas chat_id
 */
class TelegramService
{
    public function isConfigured(): bool
    {
        return filled(config('services.telegram.bot_token'))
            && filled(config('services.telegram.chat_id'));
    }

    /**
     * Nosūta ziņu uz konfigurēto chat. Atgriež true, ja izdevās.
     * HTML formatējums (<b>, <i>) ir atļauts.
     */
    public function send(string $message): bool
    {
        if (! $this->isConfigured()) {
            return false;
        }

        $token = config('services.telegram.bot_token');

        try {
            $response = Http::timeout(15)->post("https://api.telegram.org/bot{$token}/sendMessage", [
                'chat_id' => config('services.telegram.chat_id'),
                'text' => $message,
                'parse_mode' => 'HTML',
                'disable_web_page_preview' => true,
            ]);

            if ($response->failed()) {
                Log::warning('Telegram ziņas sūtīšana neizdevās', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return false;
            }

            return true;
        } catch (\Throwable $e) {
            Log::warning('Telegram ziņas sūtīšanas kļūda: '.$e->getMessage());

            return false;
        }
    }
}
