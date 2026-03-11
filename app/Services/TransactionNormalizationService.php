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
            'counterparty_name' => $this->normalizeCounterpartyName($row['Saņēmējs/Maksātājs'] ?? null),
            'counterparty_account' => $row['Kontrahenta konts'] ?? null,
            'description' => $row['Apraksts'] ?? null,
            'reference' => $row['Maksājuma atsauce'] ?? null,
            'type' => $amount > 0 ? 'INCOME' : 'EXPENSE',
            'status' => 'DRAFT',
            'raw_payload' => array_merge($row, ['Bankas_kods' => $row['Bankas_kods'] ?? null]),
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
            'counterparty_name' => $this->normalizeCounterpartyName($row['Name'] ?? null),
            'type' => $amount > 0 ? 'INCOME' : 'EXPENSE',
            'status' => 'DRAFT',
            'raw_payload' => $row,
            'fingerprint' => $this->generateFingerprint($row),
        ];
    }

    /**
     * Strip bank-formatting artifacts from counterparty names.
     *
     * Handles two known Swedbank CAMT.053 artefacts:
     *  1. Leading period/space: ".ROŽKALNI. CAMPHILL NODIBINĀJUMS …"
     *  2. Trailing bank-internal numeric reference in parentheses:
     *     "… NODIBINĀJUMS (2016050200283830-1)"
     *     The reference is 10+ digits optionally followed by -N.
     *     Short numeric suffixes like "(123)" are left intact.
     *
     * Internal dots used as abbreviations (e.g. "A.S.") are preserved.
     */
    protected function normalizeCounterpartyName(?string $name): ?string
    {
        if ($name === null) {
            return null;
        }
        // 1. Remove leading dots and spaces
        $name = preg_replace('/^[\s.]+/', '', $name);
        // 2. Remove trailing bank-reference "(10+ digits[-N])"
        $name = preg_replace('/\s*\(\d{10,}[\d-]*\)\s*$/', '', $name);
        $name = trim($name);
        return $name !== '' ? $name : null;
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
