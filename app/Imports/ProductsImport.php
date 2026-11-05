<?php

namespace App\Imports;

use App\Models\Product;
use App\Models\ProductLog;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Services\ProductLogService;
use App\Services\StockReconciliationService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;

class ProductsImport implements ToCollection, WithCalculatedFormulas
{
    protected int $businessId;
    protected array $productColumns;
    protected array $purchaseColumns;

    // state machine
    protected bool $inDataBlock = false;
    protected ?string $currentGd = null;
    protected int $imported = 0;
    protected int $skipped = 0;

    // stats returned to controller
    public array $result = [
        'imported' => 0,
        'skipped' => 0,
        'gds' => [],
    ];

    // Columns that MUST match header row; if header differs, block is skipped
    private const EXPECTED_HEADER_A = 'Items';
    private const EXPECTED_HEADER_B = 'HS CODE';

    public function __construct($businessId)
    {
        $this->businessId = (int) $businessId;
        $this->productColumns = Schema::getColumnListing('products');
        $this->purchaseColumns = Schema::getColumnListing('purchases');
    }

    public function collection(Collection $rows)
    {
        // Reset state
        $this->inDataBlock = false;
        $this->currentGd = null;
        $this->imported = 0;
        $this->skipped = 0;
        $this->result = ['imported' => 0, 'skipped' => 0, 'gds' => []];

        foreach ($rows as $rowIndex => $row) {
            $rowNum = $rowIndex + 1; // Excel row number (1-based)
            $rowArr = $row->toArray();

            $colA = trim((string) ($rowArr[0] ?? ''));
            $colB = trim((string) ($rowArr[1] ?? ''));

            // -------- 1. Detect GD title row (e.g. "LAPR-HC-4659-01-01-2025") -----------
            // Pattern: contains "-HC-" or starts with KAPE-/PAKI-/LAPR-
            if (
                $colA !== '' && preg_match('/^[A-Z]{2,5}-HC-\d+/i', $colA)
                && strpos(strtolower($colA), 'pruchases') === false
                && strpos(strtolower($colA), 'imports') === false
            ) {
                $this->currentGd = $colA;
                $this->inDataBlock = false; // header comes next
                continue;
            }

            // -------- 2. Detect header row: A=="Items" && B=="HS CODE" ---------------
            if (
                strcasecmp($colA, self::EXPECTED_HEADER_A) === 0
                && stripos($colB, self::EXPECTED_HEADER_B) === 0
            ) {
                $this->inDataBlock = true;
                continue;
            }

            // -------- 3. Detect end of data block ------------------------------------
            // "G. Total", or fully empty A+B, or summary markers
            $lowerA = strtolower($colA);
            $isTotalRow = $lowerA === '' // full blank
                || strpos($lowerA, 'g. total') !== false
                || strpos($lowerA, 'grand total') !== false
                || strpos($lowerA, 'as per gd') !== false
                || strpos($lowerA, 'pruchases') !== false
                || strpos($lowerA, 'imports') !== false;

            if ($isTotalRow) {
                $this->inDataBlock = false;
                continue;
            }

            // -------- 4. Skip if not in a data block ---------------------------------
            if (!$this->inDataBlock) {
                continue;
            }

            // -------- 5. We're in a data row — validate & import ---------------------
            try {
                $created = $this->importRow($rowArr, $rowNum);
                if ($created) {
                    $this->imported++;
                    $this->result['gds'][$this->currentGd] =
                        ($this->result['gds'][$this->currentGd] ?? 0) + 1;
                } else {
                    $this->skipped++;
                }
            } catch (\Throwable $e) {
                $this->skipped++;
                Log::warning("Row {$rowNum} (GD {$this->currentGd}) skipped: " . $e->getMessage());
            }
        }

        $this->result['imported'] = $this->imported;
        $this->result['skipped'] = $this->skipped;
    }

    /**
     * Import one item row. Returns the Product or null if the row must be skipped.
     */
    protected function importRow(array $row, int $rowNum): ?Product
    {
        // ---- map columns ----
        $itemName = trim((string) ($row[0] ?? '')); // A
        $hsCode = trim((string) ($row[1] ?? '')); // B
        $weight = $row[3] ?? 0;                   // D
        $units = $row[4] ?? 0;                   // E (qty)
        $costOfGoods = $row[16] ?? 0;                   // Q
        $ratePerUnit = $row[17] ?? 0;                   // R
        $taxRate = $row[19] ?? 0;                   // T (e.g. 0.18 or "18%")
        $salesTaxAmt = $row[20] ?? 0;                   // U

        // ---- skip conditions ----
        if ($itemName === '')
            return null;

        // Header-like or footer-like content
        $lower = strtolower($itemName);
        if (
            strpos($lower, 'total') !== false
            || strpos($lower, 'subtotal') !== false
            || strpos($lower, 'as per gd') !== false
            || strpos($lower, 'pruchases') !== false
            || strpos($lower, 'imports') !== false
        ) {
            return null;
        }

        // Require HS code
        if ($hsCode === '')
            return null;

        // Reject rows whose "value" cells are still formulas (means user opened
        // in Excel and imported without recalculating)
        foreach ([$costOfGoods, $ratePerUnit, $salesTaxAmt] as $v) {
            if (is_string($v) && strpos($v, '=') === 0)
                return null;
        }

        // ---- normalise numbers ----
        $units = $this->num($units);
        $weight = $this->num($weight);
        $costOfGoods = $this->num($costOfGoods);
        $ratePerUnit = $this->num($ratePerUnit);
        $salesTaxAmt = $this->num($salesTaxAmt);
        $taxRate = $this->parsePercent($taxRate);

        // Determine quantity: prefer "No. of U" (E); if 0, use weight (D) as KGs
        $quantity = $units > 0 ? $units : 0;
        $uom = $units > 0 ? 'PCS' : ($weight > 0 ? 'KGS' : 'PCS');

        // Determine per-unit cost
        $costPerUnit = $ratePerUnit > 0 ? $ratePerUnit
            : ($quantity > 0 && $costOfGoods > 0 ? $costOfGoods / $quantity : $costOfGoods);

        // ---- build product attributes ----
        $attributes = [
            'name' => $itemName,
            'business_id' => $this->businessId,
            'sku' => $this->makeUniqueSku($itemName),
            'price' => $costPerUnit,
            'purchase_price' => $costPerUnit,
            'quantity' => $quantity,
            'hsCode' => $hsCode,
            'rate_of_tax' => $taxRate,          // e.g. 0.18
            'uom' => $uom,
            'status' => 1,
            'slug' => Str::slug($itemName) . '-' . Str::random(6),
        ];
        $attributes = array_intersect_key($attributes, array_flip($this->productColumns));

        return DB::transaction(function () use ($attributes, $costPerUnit, $quantity) {

            $product = Product::create($attributes);

            $subTotal = $costPerUnit * $quantity;

            $purchasePayload = [
                'user_id' => Auth::id(),
                'supplier_id' => 0,
                'business_id' => $this->businessId,
                'sub_total' => $subTotal,
                'grand_total' => $subTotal,
                'status' => 1,
                'date' => now(),
            ];
            $purchasePayload = array_intersect_key($purchasePayload, array_flip($this->purchaseColumns));

            $purchase = Purchase::create($purchasePayload);

            $purchaseItem = PurchaseItem::create([
                'purchase_id' => $purchase->id,
                'product_id' => $product->id,
                'purchase_price' => $costPerUnit,
                'price' => $costPerUnit,
                'quantity' => $quantity,
            ]);

            StockReconciliationService::reconcileNewBatch($purchaseItem);

            ProductLogService::log(ProductLog::ACTION_CREATED, $product, null, ProductLogService::snapshot($product->fresh()));

            return $product;
        });
    }

    // ---------------------------------------------------------------------
    private function num($v): float
    {
        if ($v === null || $v === '')
            return 0.0;
        if (is_string($v) && strpos($v, '=') === 0)
            return 0.0; // raw formula
        // strip currency / commas / percent
        $clean = preg_replace('/[^\d.\-]/', '', (string) $v);
        return is_numeric($clean) ? (float) $clean : 0.0;
    }

    /** Accept 0.18, "18%", "18" -> returns 0.18 */
    private function parsePercent($v): float
    {
        if ($v === null || $v === '')
            return 0.0;
        $s = trim((string) $v);
        $isPercent = str_contains($s, '%');
        $n = (float) preg_replace('/[^\d.\-]/', '', $s);
        if ($isPercent || $n > 1) {
            $n = $n / 100;
        }
        return round($n, 4);
    }

    private function makeUniqueSku(string $base): string
    {
        $base = Str::slug($base) ?: 'sku';
        $base = substr($base, 0, 200);
        $sku = $base;
        $i = 1;

        while (Product::where('business_id', $this->businessId)->where('sku', $sku)->exists()) {
            $sku = $base . '-' . $i++;
        }
        return $sku;
    }
}