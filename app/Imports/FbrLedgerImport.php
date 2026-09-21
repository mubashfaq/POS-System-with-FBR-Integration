<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Reads the FBR "Sales Ledger" export.
 *
 * Each row is a complete, standalone invoice (not a line item of a larger
 * invoice) — the "Invoice No." column, suffix and all, is the exact value
 * FBR returned and that gets saved into orders.fbr_invoice_no. So we take
 * it as-is and just collect the unique set of values present in the file.
 */
class FbrLedgerImport implements ToCollection, WithHeadingRow
{
    /** @var array<string,bool> invoice number => true */
    public array $invoiceNumbers = [];

    public function collection($rows)
    {
        foreach ($rows as $row) {
            // WithHeadingRow slugifies "Invoice No." -> "invoice_no"
            $raw = trim((string) ($row['invoice_no'] ?? ''));

            if ($raw === '') {
                continue;
            }

            $this->invoiceNumbers[$raw] = true;
        }
    }
}