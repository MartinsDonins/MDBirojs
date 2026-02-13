<?php

namespace App\Services;

use App\Models\ImportBatch;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TransactionImportService
{
    protected TransactionNormalizationService $normalizationService;

    public function __construct(TransactionNormalizationService $normalizationService)
    {
        $this->normalizationService = $normalizationService;
    }

    /**
     * Import transactions from ISO 20022 XML file.
     *
     * @param string $filePath Path to XML file
     * @param string $source Source code (SWED, SEB, etc.)
     * @param int $accountId Account ID to import to
     * @return array Statistics [imported, skipped, errors]
     */
    public function importFromXml(string $filePath, string $source, int $accountId): array
    {
        // Create import batch
        $batch = ImportBatch::create([
            'filename' => basename($filePath),
            'source' => $source,
            'status' => 'PENDING',
            'row_count' => 0,
        ]);

        $stats = [
            'imported' => 0,
            'skipped' => 0,
            'errors' => [],
        ];

        try {
            // Parse XML
            $entries = $this->parseIso20022Xml($filePath);
            $batch->update(['row_count' => count($entries)]);

            DB::beginTransaction();

            foreach ($entries as $index => $entry) {
                try {
                    // Normalize data
                    $normalized = $this->normalizationService->normalize($entry, $source);
                    $normalized['account_id'] = $accountId;
                    $normalized['import_batch_id'] = $batch->id;

                    // Check for duplicate by fingerprint
                    if (Transaction::where('fingerprint', $normalized['fingerprint'])->exists()) {
                        $stats['skipped']++;
                        continue;
                    }

                    // Create transaction
                    Transaction::create($normalized);
                    $stats['imported']++;

                } catch (\Exception $e) {
                    $stats['errors'][] = [
                        'row' => $index + 1,
                        'message' => $e->getMessage(),
                    ];
                    Log::error("Import error on row {$index}", [
                        'error' => $e->getMessage(),
                        'entry' => $entry,
                    ]);
                }
            }

            DB::commit();

            $batch->update([
                'status' => empty($stats['errors']) ? 'PROCESSED' : 'FAILED',
                'errors' => $stats['errors'],
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            $batch->update([
                'status' => 'FAILED',
                'errors' => [['message' => $e->getMessage()]],
            ]);
            throw $e;
        }

        return $stats;
    }

    /**
     * Parse ISO 20022 camt.053 XML file.
     *
     * @param string $filePath
     * @return array Array of transaction entries
     */
    protected function parseIso20022Xml(string $filePath): array
    {
        $xml = simplexml_load_file($filePath);
        
        // Register namespace
        $xml->registerXPathNamespace('camt', 'urn:iso:std:iso:20022:tech:xsd:camt.053.001.02');
        
        $entries = [];
        
        // Find all Ntry (Entry) elements
        $ntryElements = $xml->xpath('//camt:Ntry');
        
        foreach ($ntryElements as $ntry) {
            $ntry->registerXPathNamespace('camt', 'urn:iso:std:iso:20022:tech:xsd:camt.053.001.02');
            
            // Extract basic fields
            $amount = (string) $ntry->xpath('camt:Amt')[0];
            $currency = (string) $ntry->xpath('camt:Amt/@Ccy')[0];
            $cdtDbtInd = (string) $ntry->xpath('camt:CdtDbtInd')[0]; // CRDT or DBIT
            
            // Date
            $bookingDate = (string) ($ntry->xpath('camt:BookgDt/camt:Dt')[0] ?? '');
            
            // Transaction details
            $txDtls = $ntry->xpath('camt:NtryDtls/camt:TxDtls')[0] ?? null;
            
            $counterpartyName = '';
            $counterpartyAccount = '';
            $description = '';
            $reference = '';
            
            if ($txDtls) {
                $txDtls->registerXPathNamespace('camt', 'urn:iso:std:iso:20022:tech:xsd:camt.053.001.02');
                
                // Counterparty (Debtor or Creditor depending on direction)
                if ($cdtDbtInd === 'DBIT') {
                    // Debit - we paid, so Creditor is the counterparty
                    $counterpartyName = (string) ($txDtls->xpath('camt:RltdPties/camt:Cdtr/camt:Nm')[0] ?? '');
                    $counterpartyAccount = (string) ($txDtls->xpath('camt:RltdPties/camt:CdtrAcct/camt:Id/camt:IBAN')[0] ?? '');
                } else {
                    // Credit - we received, so Debtor is the counterparty
                    $counterpartyName = (string) ($txDtls->xpath('camt:RltdPties/camt:Dbtr/camt:Nm')[0] ?? '');
                    $counterpartyAccount = (string) ($txDtls->xpath('camt:RltdPties/camt:DbtrAcct/camt:Id/camt:IBAN')[0] ?? '');
                }
                
                // Description
                $description = (string) ($txDtls->xpath('camt:RmtInf/camt:Ustrd')[0] ?? '');
                
                // Reference
                $reference = (string) ($txDtls->xpath('camt:Refs/camt:EndToEndId')[0] ?? '');
            }
            
            // Build entry array
            $entries[] = [
                'Datums' => $bookingDate,
                'Saņēmējs/Maksātājs' => $counterpartyName,
                'Apraksts' => $description,
                'Summa' => $cdtDbtInd === 'DBIT' ? '-' . $amount : $amount,
                'Valūta' => $currency,
                'Maksājuma atsauce' => $reference,
                'Kontrahenta konts' => $counterpartyAccount,
            ];
        }
        
        return $entries;
    }
}
