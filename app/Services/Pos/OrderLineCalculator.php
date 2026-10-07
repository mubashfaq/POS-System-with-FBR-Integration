<?php
namespace App\Services\Pos;

use App\Models\Product;
use App\Models\ProductVariation;

class OrderLineCalculator
{
    public function calculate(Product $product, float $qty, ?float $priceOverride, ?string $customerTaxType, ProductVariation $variation = null): array
    {
        $unitPrice = $priceOverride ?? $variation?->price ?? $product->discounted_price;
        $lineTotal = round($unitPrice * $qty, 2);          // ex-tax
        $subTotal = round($unitPrice * $qty, 2);
        // $discount = round(($product->price - $unitPrice) * $qty, 2);

        $rateText = trim((string) $product->pos_sale_tax);
        if (str_ends_with($rateText, '%')) {
            $taxRate = (float) str_replace('%', '', $rateText);
            $saleTax = round($lineTotal * ($taxRate / 100), 2);
        } else {
            $taxRate = 0;
            $saleTax = round(((float) preg_replace('/[^0-9.]/', '', $rateText)) * $qty, 2);
        }

        $finalTotal = round($lineTotal + $saleTax, 2);

        $customerTax = match ($customerTaxType) {
            'retailer' => round($finalTotal * 0.005, 2),
            'distributor' => round($finalTotal * 0.01, 2),
            default => 0,
        };

        return [
            'unit_price' => $unitPrice,
            'line_total' => $lineTotal,   // value ex tax
            'sub_total' => $subTotal,
            // 'discount' => $discount,
            'rate_text' => $rateText,
            'tax_rate' => $taxRate,
            'sale_tax' => $saleTax,
            'customer_tax' => $customerTax,
            'total' => $finalTotal,
        ];
    }
}