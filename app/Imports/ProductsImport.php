<?php

namespace App\Imports;

use App\Models\Product;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Unit;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithStartRow;
use Illuminate\Support\Facades\Auth;

class ProductsImport implements ToModel, WithStartRow
{

    protected $businessId;

    public function __construct($businessId)
    {
        $this->businessId = $businessId;
    }
    // dd($this->businessId);
    /**
     * Tell Excel where the DATA starts (row 9, after header on row 8)
     */
    public function startRow(): int
    {
        return 9; // Start reading data from row 9
    }

    public function model(array $row)
    {
        // Excel columns mapped by index (0-based)
        // Column A (0): Items
        // Column B (1): HS CODE
        // Column C (2): Weight / (KGs)
        // Column D (3): No. of UNITS / NOS /PCS
        // Column E (4): COST PER UNIT (may be formula)
        // Column F (5): Assessed Value PKR
        // Column P (15): UOM
        // Column Q (16): RATE / UNIT OR KG (may be formula)
        // Column S (18): RATE/STAX

        $itemName = $row[0] ?? null;
        $hsCode = $row[1] ?? null;
        $weight = $row[2] ?? 0;
        $quantity = $row[3] ?? 0;
        $costPerUnit = $row[4] ?? 0;
        $uom = $row[15] ?? null;
        $ratePerUnit = $row[16] ?? 0;
        $rateSalesTax = $row[18] ?? 0;

        // CRITICAL: Skip rows that should not be imported
        // 1. Empty item name
        if (empty($itemName) || $itemName === null || trim($itemName) === '') {
            return null;
        }

        // 2. Total/Summary rows (G. Total, Subtotal, Grand Total, etc.)
        $itemNameLower = strtolower(trim($itemName));
        if (stripos($itemNameLower, 'total') !== false || 
            stripos($itemNameLower, 'subtotal') !== false ||
            stripos($itemNameLower, 'grand') !== false ||
            stripos($itemNameLower, 'g.') !== false ||
            stripos($itemNameLower, 'g total') !== false ||
            preg_match('/^g\.\s*total/i', $itemName)) {
            return null;
        }

        // 3. Missing HS Code (required field for products)
        if (empty($hsCode) || $hsCode === null || trim($hsCode) === '') {
            return null;
        }

        // 4. Skip rows with uncalculated Excel formulas
        if (is_string($costPerUnit) && strpos($costPerUnit, '=') === 0) {
            return null;
        }

        if (is_string($quantity) && strpos($quantity, '=') === 0) {
            return null;
        }

        // Preserve hsCode as string to maintain trailing zeros (e.g., 8544.4290)
        // Convert to string first, then trim
        $hsCode = trim(strval($hsCode));


        // Generate unique SKU by appending timestamp or counter if needed
        $sku = $itemName;
        $originalSku = $sku;
        $counter = 1;
        
        while (Product::where('sku', $sku)->exists()) {
            $sku = $originalSku . '-' . $counter;
            $counter++;
        }

        // Ensure numeric values are properly handled
        $costPerUnit = is_numeric($costPerUnit) ? floatval($costPerUnit) : 0;
        $quantity = is_numeric($quantity) ? intval($quantity) : 0;
        $ratePerUnit = is_numeric($ratePerUnit) ? floatval($ratePerUnit) : 0;
        $rateSalesTax = is_numeric($rateSalesTax) ? floatval($rateSalesTax) : 0;
        // Create the product
        $product = Product::create([
            'name' => $itemName,
            'business_id' => $this->businessId,
            'sku' => $itemName, // Using name as SKU
            'price' => $costPerUnit,
            'hsCode' => $hsCode, // Preserved as string with trailing zeros
            'rate_of_tax' => $ratePerUnit,
            'salesTaxWithheldAtSource' => $rateSalesTax,
            'sale_type' => 'Goods at standard rate',
            'uom' => $uom,
            'discount' => null,
            'discount_type' => null,
            'purchase_price' => $costPerUnit,
            'quantity' => $quantity,
        ]);

        // Calculate purchase totals
        $purchasePrice = $costPerUnit;
        $qty = $quantity;
        $subTotal = $purchasePrice * $qty;

        // Create purchase record
        $purchase = Purchase::create([
            'supplier_id' => 1, // Default supplier
            'user_id' => Auth::id(),
            'sub_total' => $subTotal,
            'tax' => 0,
            'discount' => 0,
            'discount_type' => 'fixed',
            'shipping' => 0,
            'grand_total' => $subTotal,
            'status' => 1,
            'date' => now()
        ]);

        // Create purchase item record
        PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'product_id' => $product->id,
            'purchase_price' => $purchasePrice,
            'price' => $purchasePrice,
            'quantity' => $qty,
        ]);

        return $product;
    }
}