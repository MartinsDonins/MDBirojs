<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\Transaction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CoreDigifyService
{
    /**
     * VID column IDs that represent "ienākumi no saimnieciskās darbības".
     */
    private const BUSINESS_INCOME_VID_COLUMNS = [4, 5, 6];

    /**
     * Check whether a transaction qualifies for CoreDigify sync.
     */
    public function isBusinessIncome(Transaction $transaction): bool
    {
        if ($transaction->type !== 'INCOME') {
            return false;
        }

        if (!$transaction->relationLoaded('category')) {
            $transaction->load('category');
        }

        return in_array(
            $transaction->category?->vid_column,
            self::BUSINESS_INCOME_VID_COLUMNS,
            true
        );
    }

    /**
     * Send a single payment notification to CoreDigify.
     *
     * @return array{success: bool, status: int|null, body: array, error: string|null}
     */
    public function sendPayment(Transaction $transaction): array
    {
        $enabled = AppSetting::get('coredigify_enabled', config('services.coredigify.enabled', false));
        if (!$enabled) {
            return ['success' => false, 'status' => null, 'body' => [], 'error' => 'CoreDigify integration disabled'];
        }

        $url = AppSetting::getRaw('coredigify_api_url') ?: config('services.coredigify.url', '');
        if (empty($url)) {
            return ['success' => false, 'status' => null, 'body' => [], 'error' => 'CoreDigify API URL not configured'];
        }

        if (!$transaction->relationLoaded('account')) {
            $transaction->load(['account', 'category', 'cashOrder']);
        }

        $payload = $this->buildPayload($transaction);

        $apiKey = AppSetting::getRaw('coredigify_api_key') ?: config('services.coredigify.key', '');

        try {
            $response = Http::withToken($apiKey)
                ->timeout(15)
                ->post($url, $payload);

            $success = $response->successful();

            if (!$success) {
                Log::error('CoreDigify sync failed', [
                    'transaction_id' => $transaction->id,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            }

            return [
                'success' => $success,
                'status'  => $response->status(),
                'body'    => $response->json() ?? [],
                'error'   => $success ? null : ('HTTP ' . $response->status() . ': ' . $response->body()),
            ];
        } catch (\Throwable $e) {
            Log::error('CoreDigify sync exception', [
                'transaction_id' => $transaction->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'status'  => null,
                'body'    => [],
                'error'   => $e->getMessage(),
            ];
        }
    }

    /**
     * Send multiple qualifying transactions to CoreDigify.
     *
     * @param  Collection<int, Transaction>  $transactions
     * @return array{sent: int, skipped: int, errors: array}
     */
    public function sendBatch(Collection $transactions): array
    {
        $sent    = 0;
        $skipped = 0;
        $errors  = [];

        foreach ($transactions as $transaction) {
            if (!$this->isBusinessIncome($transaction)) {
                $skipped++;
                continue;
            }

            $result = $this->sendPayment($transaction);

            if ($result['success']) {
                $transaction->update([
                    'coredigify_sent_at'   => now(),
                    'coredigify_sync_error' => null,
                ]);
                $sent++;
            } else {
                $transaction->update([
                    'coredigify_sync_error' => $result['error'],
                ]);
                $errors[] = [
                    'transaction_id' => $transaction->id,
                    'error'          => $result['error'],
                ];
            }
        }

        return compact('sent', 'skipped', 'errors');
    }

    /**
     * Test the connection to CoreDigify.
     *
     * @return array{success: bool, status: int|null, body: array, error: string|null}
     */
    public function testConnection(): array
    {
        $enabled = AppSetting::get('coredigify_enabled', false);
        if (!$enabled) {
            return ['success' => false, 'status' => null, 'body' => [], 'error' => 'CoreDigify integrācija ir atspējota iestatījumos.'];
        }

        $url = AppSetting::getRaw('coredigify_api_url') ?: config('services.coredigify.url', '');
        if (empty($url)) {
            return ['success' => false, 'status' => null, 'body' => [], 'error' => 'CoreDigify API URL nav nokonfigurēts.'];
        }

        $apiKey = AppSetting::getRaw('coredigify_api_key') ?: config('services.coredigify.key', '');

        try {
            $payload = [
                'source' => 'MDBirojs',
                'test_connection' => true,
                'occurred_at' => now()->toDateString(),
            ];

            Log::debug('CoreDigify test connection request', [
                'url'     => $url,
                'headers' => ['Authorization' => 'Bearer ' . substr($apiKey, 0, 8) . '...'],
                'payload' => $payload,
            ]);

            $response = Http::withToken($apiKey)
                ->timeout(10)
                ->post($url, $payload);

            $success = $response->successful();

            Log::debug('CoreDigify test connection response', [
                'status'  => $response->status(),
                'headers' => $response->headers(),
                'body'    => $response->body(),
            ]);

            return [
                'success' => $success,
                'status'  => $response->status(),
                'body'    => $response->json() ?? [],
                'error'   => $success ? null : ('HTTP ' . $response->status() . ': ' . $response->body()),
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'status'  => null,
                'body'    => [],
                'error'   => $e->getMessage(),
            ];
        }
    }

    /**
     * Build the JSON payload for a transaction.
     */
    private function buildPayload(Transaction $transaction): array
    {
        return [
            'source'              => 'MDBirojs',
            'transaction_id'      => $transaction->id,
            'occurred_at'         => $transaction->occurred_at?->toDateString(),
            'booked_at'           => $transaction->booked_at?->toDateString(),
            'amount'              => (float) $transaction->amount,
            'amount_eur'          => (float) $transaction->amount_eur,
            'currency'            => $transaction->currency,
            'exchange_rate'       => (float) ($transaction->exchange_rate ?? 1),
            'counterparty_name'   => $transaction->counterparty_name,
            'counterparty_account'=> $transaction->counterparty_account,
            'description'         => $transaction->description,
            'reference'           => $transaction->reference,
            'type'                => $transaction->type,
            'account_name'        => $transaction->account?->name,
            'category_name'       => $transaction->category?->name,
            'vid_column'          => $transaction->category?->vid_column,
            'cash_order_number'   => $transaction->cashOrder?->number,
        ];
    }
}
