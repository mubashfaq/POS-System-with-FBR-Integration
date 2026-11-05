<?php

namespace App\Imports;

use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Services\StockReconciliationService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;

class StockImport implements ToCollection, WithCalculatedFormulas
{
    protected int $businessId;
    protected array $purchaseColumns;

    protected bool $inDataBlock = false;
    protected ?string $currentGd = null;

    public array $result = [
        'matched' => 0,
        'updated' => 0,
        'failed' => 0,
        'skipped' => 0,
        'errors' => [],
        'success' => [],
        'gds' => [],
        'purchase_id' => null,
        'grand_total' => 0,
    ];

    private const EXPECTED_HEADER_A = 'Items';
    private const EXPECTED_HEADER_B = 'HS CODE';

    /** Accumulated purchase lines (consumed after all rows parsed) */
    protected array $purchaseProducts = [];
    protected float $subTotal = 0;

    public function __construct($businessId)
    {
        $this->businessId = (int) $businessId;
        $this->purchaseColumns = Schema::getColumnListing('purchases');
    }

    public function collection(Collection $rows)
    {
        $this->inDataBlock = false;
        $this->currentGd = null;
        $this->purchaseProducts = [];
        $this->subTotal = 0;
        $this->result = [
            'matched' => 0,
            'updated' => 0,
            'failed' => 0,
            'skipped' => 0,
            'errors' => [],
            'success' => [],
            'gds' => [],
            'purchase_id' => null,
            'grand_total' => 0,
        ];

        foreach ($rows as $rowIndex => $row) {
            $rowNum = $rowIndex + 1;
            $rowArr = $row->toArray();

            $colA = trim((string) ($rowArr[0] ?? ''));
            $colB = trim((string) ($rowArr[1] ?? ''));

            // 1. GD title row
            if (
                $colA !== '' && preg_match('/^[A-Z]{2,5}-HC-\d+/i', $colA) && stripos($colA, 'pruchases') === false
                && stripos($colA, 'imports') === false
            ) {
                $this->currentGd = $colA;
                $this->inDataBlock = false;
                continue;
            }

            // 2. Header row
            if (strcasecmp($colA, self::EXPECTED_HEADER_A) === 0 && stripos($colB, self::EXPECTED_HEADER_B) === 0) {
                $this->inDataBlock = true;
                continue;
            }

            // 3. End-of-block / footer
            $lowerA = strtolower($colA);
            if (
                $lowerA === ''
                || strpos($lowerA, 'g. total') !== false
                || strpos($lowerA, 'grand total') !== false
                || strpos($lowerA, 'as per gd') !== false
                || strpos($lowerA, 'pruchases') !== false
                || strpos($lowerA, 'imports') !== false
            ) {
                $this->inDataBlock = false;
                continue;
            }

            if (!$this->inDataBlock) {
                continue;
            }

            // 4. Process data row (accumulate, don't insert yet)
            try {
                $this->processRow($rowArr, $rowNum);
            } catch (\Throwable $e) {
                $this->result['failed']++;
                $this->result['errors'][] = "Row {$rowNum}: " . $e->getMessage();
                Log::warning("Stock import row {$rowNum} failed: " . $e->getMessage());
            }
        }

        // 5. All rows parsed — create ONE purchase + items
        $this->persistPurchase();
    }

    protected function processRow(array $row, int $rowNum): void
    {
        $itemName = trim((string) ($row[0] ?? '')); // A
        $hsCode = trim((string) ($row[1] ?? '')); // B
        $weight = $row[3] ?? 0;                   // D
        $units = $row[4] ?? 0;                   // E
        $costOfGoods = $row[16] ?? 0;                   // Q
        $ratePerUnit = $row[17] ?? 0;                   // R

        if ($itemName === '') {
            $this->result['skipped']++;
            return;
        }

        $lower = strtolower($itemName);
        if (
            strpos($lower, 'total') !== false
            || strpos($lower, 'subtotal') !== false
            || strpos($lower, 'as per gd') !== false
            || strpos($lower, 'pruchases') !== false
            || strpos($lower, 'imports') !== false
        ) {
            $this->result['skipped']++;
            return;
        }

        if ($hsCode === '') {
            $this->result['skipped']++;
            return;
        }

        foreach ([$costOfGoods, $ratePerUnit] as $v) {
            if (is_string($v) && strpos($v, '=') === 0) {
                $this->result['skipped']++;
                return; // formula not calculated
            }
        }

        $units = $this->num($units);
        $weight = $this->num($weight);
        $costOfGoods = $this->num($costOfGoods);
        $ratePerUnit = $this->num($ratePerUnit);

        $quantity = $units > 0 ? $units : 0;
        // if ($quantity <= 0) {
        //     // fall back to weight (KGs) when units column is empty (bulk materials)
        //     $quantity = $weight;
        // }
        if ($quantity <= 0) {
            $this->result['skipped']++;
            return;
        }

        $costPerUnit = $ratePerUnit > 0 ? $ratePerUnit : ($costOfGoods > 0 ? $costOfGoods / $quantity : 0);

        // ---- find product: try slug, then sku, then name, scoped by hsCode ----
        $product = $this->findProduct($itemName, $hsCode);

        if (!$product) {
            $this->result['failed']++;
            $this->result['errors'][] = "Row {$rowNum}: Product '{$itemName}' (HS: {$hsCode}) not found.";
            return;
        }

        $this->result['matched']++;

        $purchasePrice = $costPerUnit > 0 ? $costPerUnit : ($product->purchase_price ?? 0);
        $price = $product->price ?? $purchasePrice;
        $lineTotal = $purchasePrice * $quantity;

        $this->purchaseProducts[] = [
            'product' => $product,
            'quantity' => $quantity,
            'purchase_price' => $purchasePrice,
            'price' => $price,
            'total' => $lineTotal,
        ];
        $this->subTotal += $lineTotal;

        $this->result['gds'][$this->currentGd] = ($this->result['gds'][$this->currentGd] ?? 0) + 1;
    }

    protected function persistPurchase(): void
    {
        if (empty($this->purchaseProducts)) {
            return;
        }

        DB::transaction(function () {

            $purchasePayload = [
                'user_id' => Auth::id(),
                'supplier_id' => 0,
                'business_id' => $this->businessId,
                'sub_total' => $this->subTotal,
                'tax' => 0,
                'discount_value' => 0,
                'shipping' => 0,
                'grand_total' => $this->subTotal,
                'date' => now(),
                'status' => 1,
            ];
            $purchasePayload = array_intersect_key($purchasePayload, array_flip($this->purchaseColumns));

            $purchase = Purchase::create($purchasePayload);

            foreach ($this->purchaseProducts as $item) {
                /** @var Product $product */
                $product = $item['product'];
                $oldStock = (int) ($product->quantity ?? 0);

                $purchaseItem = PurchaseItem::create([
                    'purchase_id' => $purchase->id,
                    'product_id' => $product->id,
                    'purchase_price' => $item['purchase_price'],
                    'price' => $item['price'],
                    'quantity' => $item['quantity'],
                    'remaining_qty' => $item['quantity'],
                ]);

                StockReconciliationService::reconcileNewBatch($purchaseItem);

                $product->increment('quantity', $item['quantity']);
                $newStock = (int) $product->fresh()->quantity;

                $this->result['updated']++;
                $this->result['success'][] =
                    "Product '{$product->name}' (HS: {$product->hsCode}) stock {$oldStock} → {$newStock} (+{$item['quantity']}).";
            }

            $this->result['purchase_id'] = $purchase->id;
            $this->result['grand_total'] = (float) $purchase->grand_total;
        });
    }

    protected function findProduct(string $name, string $hsCode): ?Product
    {
        $slug = \Illuminate\Support\Str::slug($name);

        // 1. exact slug + hsCode
        $p = Product::where('business_id', $this->businessId)->where('hsCode', $hsCode)->where(
            fn($q) => $q->where('slug', $slug)->orWhere('name', $name)->orWhere('sku', $name)
        )->first();
        if ($p)
            return $p;

        // 2. normalized hsCode (strip trailing zeros/dots)
        $hsNorm = rtrim(rtrim($hsCode, '0'), '.');
        $candidates = Product::where('business_id', $this->businessId)
            ->where(fn($q) => $q->where('slug', $slug)->orWhere('name', $name)->orWhere('sku', $name))
            ->get();
        foreach ($candidates as $c) {
            if (rtrim(rtrim((string) $c->hsCode, '0'), '.') === $hsNorm) {
                return $c;
            }
        }

        // 3. name-only fallback (no HS match)
        return Product::where('business_id', $this->businessId)->where(fn($q) => $q->where('slug', $slug)->orWhere('name', $name))->first();
    }

    private function num($v): float
    {
        if ($v === null || $v === '')
            return 0.0;
        if (is_string($v) && strpos($v, '=') === 0)
            return 0.0;
        $clean = preg_replace('/[^\d.\-]/', '', (string) $v);
        return is_numeric($clean) ? (float) $clean : 0.0;
    }
}