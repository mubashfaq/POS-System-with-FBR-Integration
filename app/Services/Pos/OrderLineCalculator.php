<?php
namespace App\Services\Pos;

use App\Models\Product;
use App\Models\ProductVariation;

class OrderLineCalculator
{
    public function calculate(Product $product, float $qty, ?float $priceOverride, ?string $customerTaxType,
                              ?ProductVariation $variation = null, float $factor = 1.0): array
    {
        $factor = $factor > 0 ? $factor : 1.0;
        $unitPrice = $priceOverride ?? (($variation?->price ?? $product->discounted_price) * $factor);
        $baseQty   = $qty * $factor;
        $lineTotal = round($unitPrice * $qty, 2);          // ex-tax, $qty is typed qty, $unitPrice is per typed unit
        $subTotal  = round($unitPrice * $qty, 2);

        $rateText = trim((string) $product->pos_sale_tax);
        if (str_ends_with($rateText, '%')) {
            $taxRate = (float) str_replace('%', '', $rateText);
            $saleTax = round($lineTotal * ($taxRate / 100), 2);
        } else {
            $taxRate = 0;
            // fixed per-unit tax uses baseQty, not typed qty
            preg_match('/\d+(?:\.\d+)?/', $rateText, $matches);
            $ratePerUnit = (float) ($matches[0] ?? 0);
            $saleTax = round($ratePerUnit * $baseQty, 2);
        }

        $finalTotal = round($lineTotal + $saleTax, 2);

        $customerTax = match ($customerTaxType) {
            'retailer' => round($finalTotal * 0.005, 2),
            'distributor' => round($finalTotal * 0.01, 2),
            default => 0,
        };

        return [
            'unit_price' => round($unitPrice, 4),
            'line_total' => $lineTotal,   // value ex tax
            'sub_total' => $subTotal,
            'rate_text' => $rateText,
            'tax_rate' => $taxRate,
            'sale_tax' => $saleTax,
            'customer_tax' => $customerTax,
            'total' => $finalTotal,
        ];
    }
}
