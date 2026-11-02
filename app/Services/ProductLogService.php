<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class ProductLogService
{
    /**
     * Log a product action.
     *
     * @param  string       $action   created|updated|deleted
     * @param  Product      $product
     * @param  array|null   $oldValues  Snapshot before change (for update/delete)
     * @param  array|null   $newValues  Snapshot after change (for create/update)
     */
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

    /**
     * Extract only the fields that actually changed.
     */
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