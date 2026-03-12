<?php

namespace App\Services;

use App\Models\ImportBatch;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TransactionImportService
{
    protected TransactionNormalizationService $normalizationService;
    protected AutoApprovalService $autoApprovalService;

    public function __construct(
        TransactionNormalizationService $normalizationService,
        AutoApprovalService $autoApprovalService
    ) {
        $this->normalizationService = $normalizationService;
        $this->autoApprovalService = $autoApprovalService;
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
            'auto_approved' => 0,
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
                    $transaction = Transaction::create($normalized);
                    $stats['imported']++;

                    // Try auto-approval
                    if ($this->autoApprovalService->processTransaction($transaction)) {
                        $stats['auto_approved']++;
                    }

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
     * Import transactions from Paysera XLSX file.
     *
     * @param string $filePath Path to XLSX file
     * @param string $source   Source code (PAYSERA)
     * @param int    $accountId Account ID to import to
     * @return array Statistics [imported, skipped, errors]
     */
    public function importFromXlsx(string $filePath, string $source, int $accountId): array
    {
        $batch = ImportBatch::create([
            'filename'  => basename($filePath),
            'source'    => $source,
            'status'    => 'PENDING',
            'row_count' => 0,
        ]);

        $stats = [
            'imported'     => 0,
            'skipped'      => 0,
            'auto_approved' => 0,
            'errors'       => [],
        ];

        try {
            $entries = match ($source) {
                'PAYSERA' => $this->parsePaysera($filePath),
                default   => throw new \Exception("Unsupported XLSX source: $source"),
            };
            $batch->update(['row_count' => count($entries)]);

            DB::beginTransaction();

            foreach ($entries as $index => $entry) {
                try {
                    $normalized = $this->normalizationService->normalize($entry, $source);
                    $normalized['account_id']      = $accountId;
                    $normalized['import_batch_id'] = $batch->id;

                    if (Transaction::where('fingerprint', $normalized['fingerprint'])->exists()) {
                        $stats['skipped']++;
                        continue;
                    }

                    $transaction = Transaction::create($normalized);
                    $stats['imported']++;

                    if ($this->autoApprovalService->processTransaction($transaction)) {
                        $stats['auto_approved']++;
                    }
                } catch (\Exception $e) {
                    $stats['errors'][] = [
                        'row'     => $index + 1,
                        'message' => $e->getMessage(),
                    ];
                    Log::error("XLSX import error on row {$index}", [
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
     * Parse Paysera XLSX statement file.
     *
     * File structure (rows are 1-indexed):
     *   Rows 1–3 : Metadata / signature block (skip)
     *   Row 4    : Column header row starting with "Veids Datums un laiks" (skip)
     *   Row 5+   : Alternating pairs:
     *               - Main row   : Col B (statement nr) is NOT null
     *               - Description: Col B is null, Col A = "Maksājuma mērķis : <text>"
     *
     * Column mapping for main rows:
     *   A (1): "TYPE (sub) YYYY-MM-DD\nHH:MM:SS +TZ"
     *   B (2): "statement_nr\ntransfer_nr"
     *   C (3): "COUNTERPARTY NAME (client-code)"
     *   E (5): IBAN / EVP account
     *   H (8): Received amount (positive float, or null)
     *   I (9): Sent amount    (positive float, or null; stored negated)
     *
     * @param string $filePath
     * @return array Array of normalisation-ready rows (Swedbank-compatible keys)
     */
    protected function parsePaysera(string $filePath): array
    {
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($filePath);
        $sheet       = $spreadsheet->getActiveSheet();
        $maxRow      = $sheet->getHighestRow();

        // Locate the data-start row (the row after the header that contains "Veids")
        $dataStartRow = 5;
        for ($r = 1; $r <= min(10, $maxRow); $r++) {
            $cellVal = $sheet->getCellByColumnAndRow(1, $r)->getValue();
            if (is_string($cellVal) && mb_strpos($cellVal, 'Veids') !== false) {
                $dataStartRow = $r + 1;
                break;
            }
        }

        $entries = [];
        $r       = $dataStartRow;

        while ($r <= $maxRow) {
            $stmtNrCell = $sheet->getCellByColumnAndRow(2, $r)->getValue(); // Col B

            // Skip rows that are not main transaction rows
            if ($stmtNrCell === null) {
                $r++;
                continue;
            }

            // --- Main transaction row ---
            $col1 = (string) ($sheet->getCellByColumnAndRow(1, $r)->getValue() ?? '');
            $col3 = (string) ($sheet->getCellByColumnAndRow(3, $r)->getValue() ?? '');
            $col5 = (string) ($sheet->getCellByColumnAndRow(5, $r)->getValue() ?? '');
            $col8 = $sheet->getCellByColumnAndRow(8, $r)->getValue(); // received
            $col9 = $sheet->getCellByColumnAndRow(9, $r)->getValue(); // sent

            // Extract ISO date from Col A: "TYPE (sub) 2018-01-02\n10:42:18 +0200"
            preg_match('/(\d{4}-\d{2}-\d{2})/', $col1, $dateMatch);
            $date = $dateMatch[1] ?? date('Y-m-d');

            // Determine amount sign: received positive, sent negative
            if ($col8 !== null && is_numeric($col8) && (float) $col8 > 0) {
                $amount = (float) $col8;
            } elseif ($col9 !== null && is_numeric($col9) && (float) $col9 > 0) {
                $amount = -(float) $col9;
            } else {
                // Fallback: parse text in Col G "1.00 EUR\n1.00 EUR"
                $col7 = (string) ($sheet->getCellByColumnAndRow(7, $r)->getValue() ?? '');
                preg_match('/^(-?\d[\d,.]*)/', $col7, $amtMatch);
                $amount = isset($amtMatch[1]) ? (float) str_replace(',', '.', $amtMatch[1]) : 0.0;
            }

            // Strip Paysera client-code "(123456-12345)" or "(300060819)" from name
            $counterpartyName = preg_replace('/\s*\(\d[\d-]*\)\s*$/u', '', $col3);
            $counterpartyName = trim($counterpartyName) ?: null;

            // Statement number is the first line of Col B
            $stmtParts   = explode("\n", (string) $stmtNrCell);
            $stmtNumber  = $stmtParts[0] ?? (string) $stmtNrCell;

            // Peek at the next row: if Col B is null, it is a description row
            $description     = '';
            $nextStmtNr      = ($r + 1 <= $maxRow)
                ? $sheet->getCellByColumnAndRow(2, $r + 1)->getValue()
                : 'HAS_VALUE'; // treat as "no description row" beyond end

            if ($nextStmtNr === null) {
                $descRaw     = (string) ($sheet->getCellByColumnAndRow(1, $r + 1)->getValue() ?? '');
                // Strip "Maksājuma mērķis : " (or any leading label ending with ": ")
                $colonPos    = mb_strpos($descRaw, ': ');
                $description = $colonPos !== false
                    ? trim(mb_substr($descRaw, $colonPos + 2))
                    : $descRaw;
                $r += 2; // consumed main row + description row
            } else {
                $r++; // only main row (no description row follows)
            }

            $entries[] = [
                'Datums'              => $date,
                'Saņēmējs/Maksātājs' => $counterpartyName,
                'Apraksts'           => $description,
                'Summa'              => $amount,
                'Valūta'             => 'EUR',
                'Kontrahenta konts'  => $col5 ?: null,
                'Maksājuma atsauce'  => $stmtNumber,
                'Bankas_kods'        => null,
            ];
        }

        return $entries;
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
            
            // Entry-level reference (NtryRef) — unique per entry in the bank statement
            $ntryRef = (string) ($ntry->xpath('camt:NtryRef')[0] ?? '');

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

                // AcctSvcrRef = bank's own unique ID for this transaction (most reliable)
                // EndToEndId  = originator's reference (often "NOTPROVIDED" for own-account transfers)
                $acctSvcrRef = (string) ($txDtls->xpath('camt:Refs/camt:AcctSvcrRef')[0] ?? '');
                $endToEndId  = (string) ($txDtls->xpath('camt:Refs/camt:EndToEndId')[0]  ?? '');

                // Use AcctSvcrRef as the primary reference; fall back to EndToEndId
                $reference = $acctSvcrRef ?: $endToEndId;
            }

            // Extract Bank Transaction Code (Prtry/Cd)
            // Path: BkTxCd -> Prtry -> Cd
            $bankCode = (string) ($ntry->xpath('camt:BkTxCd/camt:Prtry/camt:Cd')[0] ?? '');

            // Build entry array.
            // NtryRef and AcctSvcrRef are included so that two identical-looking
            // transactions (same date/amount/description) get distinct fingerprints.
            $entries[] = [
                'Datums'              => $bookingDate,
                'Saņēmējs/Maksātājs' => $counterpartyName,
                'Apraksts'            => $description,
                'Summa'               => $cdtDbtInd === 'DBIT' ? '-' . $amount : $amount,
                'Valūta'              => $currency,
                'Maksājuma atsauce'   => $reference,
                'Kontrahenta konts'   => $counterpartyAccount,
                'Bankas_kods'         => $bankCode,
                'NtryRef'             => $ntryRef,
                'AcctSvcrRef'         => $acctSvcrRef ?? '',
            ];
        }
        
        return $entries;
    }
}
