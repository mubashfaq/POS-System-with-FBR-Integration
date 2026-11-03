<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class ProductLogService
{
    /**
     * Fields captured in old_values / new_values.
     * If you add a business-meaningful column to `products`,
     * add it here once and every store/update path picks it up.
     */
    public const TRACKED_FIELDS = [
        // Identity
        'name',
        'sku',
        'slug',
        'description',

        // Pricing
        'price',
        'purchase_price',
        'discount',
        'discount_type',
        'fixed_notified_value_or_retail_price',

        // Stock
        'quantity',
        'has_variations',
        'expire_date',

        // Relations / classification
        'category_id',
        'brand_id',
        'unit_id',

        // FBR / tax fields
        'hsCode',
        'hs_description',
        'rate_of_tax',
        'salesTaxWithheldAtSource',
        'sale_type',
        'sro_item',
        'sro_schedule',
        'uom',
        'PCTCode',
        'pos_sale_tax',

        // Status
        'status',
    ];

    /**
     * Snapshot of a product's tracked fields, safe to store as JSON.
     */
    public static function snapshot(Product $product): array
    {
        return collect(self::TRACKED_FIELDS)->mapWithKeys(function ($field) use ($product) {
            $value = $product->getAttribute($field);
            // Normalize empty strings to null for stable comparison
            if (is_string($value) && trim($value) === '') {
                $value = null;
            }
            return [$field => $value];
        })->all();
    }

    public static function log(string $action, Product $product, ?array $oldValues = null, ?array $newValues = null): ProductLog
    {
        $changedFields = null;

        if ($action === ProductLog::ACTION_UPDATED && $oldValues && $newValues) {
            $changedFields = self::diffFields($oldValues, $newValues);
        }

        return ProductLog::create([
            'business_id' => $product->business_id ?? session('business_id'),
            'user_id' => Auth::id(),
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_sku' => $product->sku,
            'action' => $action,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'changed_fields' => $changedFields,
            'ip_address' => Request::ip(),
            'user_agent' => substr((string) Request::userAgent(), 0, 500),
        ]);
    }

    private static function diffFields(array $old, array $new): array
    {
        $changed = [];

        $allKeys = array_unique(array_merge(array_keys($old), array_keys($new)));

        foreach ($allKeys as $key) {
            // Skip noisy / internal fields
            if (in_array($key, ['updated_at', 'created_at', 'image'], true)) {
                continue;
            }

            $oldVal = $old[$key] ?? null;
            $newVal = $new[$key] ?? null;

            // Normalize numeric-string comparisons
            if (is_numeric($oldVal) && is_numeric($newVal)) {
                if ((float) $oldVal !== (float) $newVal) {
                    $changed[$key] = ['old' => $oldVal, 'new' => $newVal];
                }
                continue;
            }

            if ($oldVal !== $newVal) {
                $changed[$key] = ['old' => $oldVal, 'new' => $newVal];
            }
        }

        return $changed;
    }
}