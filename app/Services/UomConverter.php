<?php

namespace App\Services;

use App\Models\Packing;
use App\Models\PackingLevel;
use App\Models\Product;
use Illuminate\Validation\ValidationException;

class UomConverter
{
    public static function resolve(int $packingId, int $unitId): PackingLevel
    {
        return PackingLevel::where('packing_id', $packingId)->where('unit_id', $unitId)->firstOrFail();
    }

    public static function toBase(int $packingId, int $unitId, float $qty): array
    {
        $f = (float) self::resolve($packingId, $unitId)->base_qty;
        return [
            'factor' => $f,
            'total_base' => round($qty * $f, 4),
        ];
    }

    public static function priceToBase(float $unitPrice, float $factor): float
    {
        if ($factor <= 0) {
            return round($unitPrice, 4);
        }
        return round($unitPrice / $factor, 4);
    }

    public static function assertPackingMatchesProduct(int $packingId, Product $product): void
    {
        $p = Packing::findOrFail($packingId);
        if (!$product->unit_id || (int) $p->base_unit_id !== (int) $product->unit_id) {
            throw ValidationException::withMessages([
                'packing_id' => "Packing base unit must match the unit of {$product->name}."
            ]);
        }
    }
}
