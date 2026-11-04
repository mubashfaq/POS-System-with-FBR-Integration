<?php

namespace App\Services;

use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class ProductVariationService
{
    /**
     * Create a variation and its attribute values.
     * Stock stays 0 here; postOpeningBatch() adds it.
     */
    public function create(Product $product, array $row, int $businessId): ProductVariation
    {
        $variation = ProductVariation::create([
            'product_id' => $product->id,
            'business_id' => $businessId,
            'sku' => $row['sku'] ?? null,
            'purchase_price' => $product->purchase_price,
            'price' => $row['price'],
            'status' => 1,
        ]);

        $this->syncValues($variation, $row['options'], $businessId);

        return $variation;
    }

    public function syncValues(ProductVariation $variation, array $options, int $businessId): void
    {
        $valueIds = [];

        foreach ($options as $name => $value) {
            $attribute = Attribute::firstOrCreate([
                'business_id' => $businessId,
                'name' => trim($name),
            ]);

            $valueIds[] = AttributeValue::firstOrCreate([
                'attribute_id' => $attribute->id,
                'value' => trim($value),
            ])->id;
        }

        $variation->values()->sync($valueIds);
    }

    /**
     * Sync submitted rows against the product's variations.
     *
     *  - row with a known id  -> update price / sku / options (stock untouched)
     *  - row without id       -> create (or restore if a trashed match exists)
     *  - existing, not posted -> soft-delete (history kept for invoices/purchases)
     *
     * @return Collection new units that still need an opening batch
     */
    public function sync(Product $product, array $rows, int $businessId): Collection
    {
        // Include only non-deleted variations for matching by id
        $existing = $product->variations()->get()->keyBy('id');

        // Also grab trashed variations so we can restore if same combo re-appears
        $trashed = $product->variations()->onlyTrashed()->with('values')->get();

        $keep = [];
        $created = collect();

        foreach ($rows as $row) {
            $variation = isset($row['id']) ? $existing->get((int) $row['id']) : null;

            if ($variation) {
                // Existing active variation → update price/sku, keep stock
                $variation->update([
                    'sku'            => $row['sku'] ?? null,
                    'purchase_price' => $product->purchase_price,
                    'price'          => $row['price'],
                    'status'         => 1,
                ]);
                $this->syncValues($variation, $row['options'], $businessId);
            } else {
                // Check if a trashed variation with the same attribute combo exists
                $restored = $this->findTrashedMatch($trashed, $row['options'] ?? []);

                if ($restored) {
                    // Restore the soft-deleted variation
                    $restored->restore();
                    $restored->update([
                        'sku'            => $row['sku'] ?? null,
                        'purchase_price' => $product->purchase_price,
                        'price'          => $row['price'],
                        'status'         => 1,
                    ]);
                    $this->syncValues($restored, $row['options'], $businessId);
                    $variation = $restored;
                } else {
                    // Brand-new variation
                    $variation = $this->create($product, $row, $businessId);
                    $created->push([
                        'variation'      => $variation,
                        'purchase_price' => (float) $product->purchase_price,
                        'price'          => (float) $row['price'],
                        'qty'            => (float) ($row['quantity'] ?? 0),
                    ]);
                }
            }

            $keep[] = $variation->id;
        }

        // Soft-delete variations not in the submitted list
        $product->variations()->whereNotIn('id', $keep)->delete();

        return $created;
    }

    /**
     * Find a trashed variation whose attribute values match the given options.
     */
    private function findTrashedMatch(Collection $trashed, array $options): ?ProductVariation
    {
        if (empty($options)) {
            return null;
        }

        $needle = collect($options)->map(fn($v) => trim($v))->sort()->values()->all();

        return $trashed->first(function (ProductVariation $v) use ($needle) {
            $existing = $v->values->pluck('value')->map(fn($val) => trim($val))->sort()->values()->all();
            return $existing === $needle;
        });
    }

    /**
     * One purchase, one batch per unit. Works for simple products too
     * (pass a unit with 'variation' => null). Use it from store() and update().
     *
     * @param Collection $units items shaped [variation, purchase_price, price, qty]
     */
    public function postOpeningBatch(Product $product, Collection $units, ?int $supplierId, int $businessId): void
    {
        $total = $units->sum(
            fn($u) => $u['purchase_price'] * $u['qty']
        );

        $purchase = Purchase::create([
            'supplier_id' => $supplierId ? $supplierId : 0,
            'user_id' => auth()->id(),
            'business_id' => $businessId,
            'sub_total' => $total,
            'tax' => 0,
            'discount_value' => 0,
            'shipping' => 0,
            'grand_total' => $total,
            'date' => Carbon::now()->toDateString(),
            'status' => 1,
        ]);

        foreach ($units as $u) {
            $item = PurchaseItem::create([
                'purchase_id' => $purchase->id,
                'product_id' => $product->id,
                'product_variation_id' => $u['variation']?->id,
                'purchase_price' => $u['purchase_price'],
                'price' => $u['price'],
                'quantity' => $u['qty'],
                'remaining_qty' => $u['qty'],
            ]);

            StockReconciliationService::reconcileNewBatch($item);
        }
    }
}