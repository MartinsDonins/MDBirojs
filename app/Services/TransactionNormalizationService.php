<?php

namespace App\Services;

use App\Models\Transaction;
use Carbon\Carbon;

class TransactionNormalizationService
{
    protected CurrencyService $currencyService;

    public function __construct(CurrencyService $currencyService)
    {
        $this->currencyService = $currencyService;
    }

    /**
     * Normalize raw data into Transaction attributes.
     */
    public function normalize(array $rawData, string $source): array
    {
        // Strategy pattern could be used here for different sources (Swedbank, Paypal, etc.)
        // For now, we'll use a switch or simple mapping
        
        return match ($source) {
            'SWED' => $this->normalizeSwedbank($rawData),
            'PAYPAL' => $this->normalizePaypal($rawData),
            default => throw new \Exception("Unknown source: $source"),
        };
    }

    protected function normalizeSwedbank(array $row): array
    {
        // Mapping for both CSV and XML formats
        // CSV: 'Datums', 'Saņēmējs/Maksātājs', 'Apraksts', 'Summa', 'Valūta', 'Maksājuma atsauce'
        // XML: Same keys from TransactionImportService parsing
        
        $amount = $this->parseAmount($row['Summa'] ?? 0);
        $currency = $row['Valūta'] ?? 'EUR';
        $date = Carbon::parse($row['Datums'] ?? now());

        return [
            'occurred_at' => $date,
            'amount' => $amount,
            'currency' => $currency,
            'amount_eur' => $this->currencyService->convert($amount, $currency, 'EUR', $date),
            'counterparty_name' => $row['Saņēmējs/Maksātājs'] ?? null,
            'counterparty_account' => $row['Kontrahenta konts'] ?? null,
            'description' => $row['Apraksts'] ?? null,
            'reference' => $row['Maksājuma atsauce'] ?? null,
            'type' => $amount > 0 ? 'INCOME' : 'EXPENSE',
            'status' => 'DRAFT',
            'raw_payload' => $row,
            'fingerprint' => $this->generateFingerprint($row),
        ];
    }

    protected function normalizePaypal(array $row): array
    {
        // Logic for PayPal (Gross, Fee, Net)
        // Usually PayPal has separate columns for Gross, Fee, Net
        // We might need to split this into multiple transactions/lines if we track fees separately
        
        $amount = $this->parseAmount($row['Net'] ?? 0); // Or Gross?
        $currency = $row['Currency'] ?? 'EUR';
        
        return [
            'occurred_at' => Carbon::parse($row['Date'] ?? now()),
            'amount' => $amount,
            'currency' => $currency,
            'amount_eur' => $this->currencyService->convert($amount, $currency, 'EUR'),
            'counterparty_name' => $row['Name'] ?? null,
            'type' => $amount > 0 ? 'INCOME' : 'EXPENSE',
            'status' => 'DRAFT',
            'raw_payload' => $row,
            'fingerprint' => $this->generateFingerprint($row),
        ];
    }

    protected function parseAmount($value): float
    {
        if (is_string($value)) {
            $value = str_replace(',', '.', $value);
            $value = preg_replace('/[^\d.-]/', '', $value);
        }
        return (float) $value;
    }

    protected function generateFingerprint(array $row): string
    {
        return hash('sha256', json_encode($row));
    }
}
