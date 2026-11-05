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
    protected int $duplicates = 0;

    public array $result = [
        'imported' => 0,
        'duplicates' => 0,
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
        $this->duplicates = 0;
        $this->result = ['imported' => 0, 'duplicates' => 0, 'skipped' => 0, 'gds' => []];

        foreach ($rows as $rowIndex => $row) {
            $rowNum = $rowIndex + 1; // Excel row number (1-based)
            $rowArr = $row->toArray();

            $colA = trim((string) ($rowArr[0] ?? ''));
            $colB = trim((string) ($rowArr[1] ?? ''));

            // -------- 1. Detect GD title row (e.g. "LAPR-HC-4659-01-01-2025") -----------
            // Pattern: contains "-HC-" or starts with KAPE-/PAKI-/LAPR-
            if (
                $colA !== '' && preg_match('/^[A-Z]{2,5}-HC-\d+/i', $colA) && strpos(strtolower($colA), 'pruchases') === false
                && strpos(strtolower($colA), 'imports') === false
            ) {
                $this->currentGd = $colA;
                $this->inDataBlock = false; // header comes next
                continue;
            }

            // -------- 2. Detect header row: A=="Items" && B=="HS CODE" ---------------
            if (strcasecmp($colA, self::EXPECTED_HEADER_A) === 0 && stripos($colB, self::EXPECTED_HEADER_B) === 0) {
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
                $outcome = $this->importRow($rowArr, $rowNum);

                if ($outcome === 'duplicate') {
                    $this->duplicates++;
                } elseif ($outcome instanceof Product) {
                    $this->imported++;
                    $this->result['gds'][$this->currentGd] = ($this->result['gds'][$this->currentGd] ?? 0) + 1;
                } else {
                    $this->skipped++;
                }
            } catch (\Throwable $e) {
                $this->skipped++;
                Log::warning("Row {$rowNum} (GD {$this->currentGd}) skipped: " . $e->getMessage());
            }
        }

        $this->result['imported'] = $this->imported;
        $this->result['duplicates'] = $this->duplicates;
        $this->result['skipped'] = $this->skipped;
    }

    /**
     * Import one item row. Returns the Product or null if the row must be skipped.
     */
    protected function importRow(array $row, int $rowNum): Product|string|null
    {
        // ---- map columns ----
        $itemName = trim((string) ($row[0] ?? '')); // A
        $hsCode = trim((string) ($row[1] ?? '')); // B
        $weight = $row[3] ?? 0;                   // D
        $units = $row[4] ?? 0;                   // E
        $costOfGoods = $row[16] ?? 0;                   // Q
        $ratePerUnit = $row[17] ?? 0;                   // R
        $taxRate = $row[19] ?? 0;                   // T
        $salesTaxAmt = $row[20] ?? 0;                   // U

        // ---- skip conditions (unchanged) ----
        if ($itemName === '')
            return null;

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

        if ($hsCode === '')
            return null;

        foreach ([$costOfGoods, $ratePerUnit, $salesTaxAmt] as $v) {
            if (is_string($v) && strpos($v, '=') === 0)
                return null;
        }

        // ---- normalise ----
        $units = $this->num($units);
        $weight = $this->num($weight);
        $costOfGoods = $this->num($costOfGoods);
        $ratePerUnit = $this->num($ratePerUnit);
        $salesTaxAmt = $this->num($salesTaxAmt);
        $taxRate = $this->parsePercent($taxRate);

        $quantity = $units > 0 ? $units : 0;
        $uom = $units > 0 ? 'PCS' : ($weight > 0 ? 'KGS' : 'PCS');

        $costPerUnit = $ratePerUnit > 0 ? $ratePerUnit
            : ($quantity > 0 && $costOfGoods > 0 ? $costOfGoods / $quantity : $costOfGoods);

        // =========================================================
        //  DUPLICATE CHECK  ← this is what was missing
        // =========================================================
        $existing = $this->findExistingProduct($itemName, $hsCode);

        if ($existing) {
            Log::info("Row {$rowNum} (GD {$this->currentGd}): duplicate product skipped", [
                'product_id' => $existing->id,
                'name' => $existing->name,
                'hsCode' => $existing->hsCode,
            ]);
            return 'duplicate';
        }

        // ---- build product attributes (unchanged) ----
        $attributes = [
            'name' => $itemName,
            'business_id' => $this->businessId,
            'sku' => $this->makeUniqueSku($itemName),
            'price' => $costPerUnit,
            'purchase_price' => $costPerUnit,
            'quantity' => $quantity,
            'hsCode' => $hsCode,
            'rate_of_tax' => $taxRate,
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

    /**
     * Find an existing product for this business using (name + hsCode).
     * HS codes are normalized so "8544.4290" matches "85444290" / "8544.429".
     */
    protected function findExistingProduct(string $name, string $hsCode): ?Product
    {
        $businessId = $this->businessId;
        $name = trim($name);
        $hsNormal = $this->normalizeHsCode($hsCode);

        // 1) exact name + exact hsCode (fast path, uses any index on hsCode)
        $p = Product::where('business_id', $businessId)->where('hsCode', $hsCode)->where(function ($q) use ($name) {
            $q->where('name', $name)->orWhere('sku', $name);
        })->first();
        if ($p)
            return $p;

        // 2) exact name, then compare normalized hsCode in PHP (handles "8544.4290" vs "85444290")
        $candidates = Product::where('business_id', $businessId)->where(function ($q) use ($name) {
            $q->where('name', $name)->orWhere('sku', $name);
        })->get();

        foreach ($candidates as $c) {
            if ($this->normalizeHsCode((string) $c->hsCode) === $hsNormal) {
                return $c;
            }
        }

        return null;
    }

    /**
     * "8544.4290" → "8544429" (strip dots, spaces, trailing zeros).
     * "3304.9190" → "3304919" ... etc.
     * Keeps the leading segment intact — only strips separators + trailing zeros
     * after the decimal point, so ".4290" and ".429" both become ".429".
     */
    protected function normalizeHsCode(string $hs): string
    {
        $hs = trim($hs);
        // remove thousands separators / spaces
        $hs = str_replace([',', ' ', "\t", "\xC2\xA0"], '', $hs);

        // if it has a decimal point, strip trailing zeros after it
        if (strpos($hs, '.') !== false) {
            [$int, $dec] = explode('.', $hs, 2);
            $dec = rtrim($dec, '0');
            $hs = $int . ($dec === '' ? '' : '.' . $dec);
        }

        return $hs;
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