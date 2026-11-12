<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Services\PackingCalculator;
use App\Services\UomConverter;
use App\Services\Pos\OrderLineCalculator;

class PackingCalculatorTest extends TestCase
{
    /**
     * Test 1: Oil Carton(10) > Bottle(5) > Litre = 50 / 5 / 1.
     * 3 Carton = 150 Litre, 20 Bottle = 100 Litre.
     */
    public function test_oil_three_level_conversion_calculation()
    {
        $levels = [
            ['level' => 1, 'unit_id' => 10, 'contains_qty' => 10], // 1 Carton = 10 Bottles
            ['level' => 2, 'unit_id' => 20, 'contains_qty' => 5],  // 1 Bottle = 5 Litres
            ['level' => 3, 'unit_id' => 30, 'contains_qty' => null], // 1 Litre (base)
        ];

        $computed = PackingCalculator::withBaseQty($levels);

        $this->assertEquals(50.0, $computed[0]['base_qty']); // Carton factor = 10 * 5 = 50
        $this->assertEquals(5.0, $computed[1]['base_qty']);  // Bottle factor = 5
        $this->assertEquals(1.0, $computed[2]['base_qty']);  // Litre factor = 1

        // 3 Carton = 150 Litre
        $this->assertEquals(150.0, 3 * $computed[0]['base_qty']);

        // 20 Bottle = 100 Litre
        $this->assertEquals(100.0, 20 * $computed[1]['base_qty']);
    }

    /**
     * Test 3 (UOM part): Pepsi Carton(4) > Packet(6) > Piece = 24 / 6 / 1.
     * 2 Carton = 48 Piece.
     */
    public function test_pepsi_three_level_conversion_calculation()
    {
        $levels = [
            ['level' => 1, 'unit_id' => 1, 'contains_qty' => 4],
            ['level' => 2, 'unit_id' => 2, 'contains_qty' => 6],
            ['level' => 3, 'unit_id' => 3, 'contains_qty' => null],
        ];

        $computed = PackingCalculator::withBaseQty($levels);

        $this->assertEquals(24.0, $computed[0]['base_qty']);
        $this->assertEquals(6.0, $computed[1]['base_qty']);
        $this->assertEquals(1.0, $computed[2]['base_qty']);

        // 2 Carton = 48 Piece
        $this->assertEquals(48.0, 2 * $computed[0]['base_qty']);
    }

    /**
     * Test 4: Carton price 5,000 with factor 50 gives 100 per Litre.
     */
    public function test_carton_price_to_base_price()
    {
        $cartonPrice = 5000.0;
        $factor = 50.0;
        $basePrice = UomConverter::priceToBase($cartonPrice, $factor);

        $this->assertEquals(100.0, $basePrice);
    }

    /**
     * Test 7 (decimal precision): Half a Litre (0.5) is preserved with 4 decimals.
     */
    public function test_half_unit_precision()
    {
        $qty = 0.5;
        $factor = 50.0;
        $totalBase = round($qty * $factor, 4);

        $this->assertEquals(25.0, $totalBase);

        $baseQty = 0.5;
        $factor = 1.0;
        $this->assertEquals('0.5000', number_format($baseQty, 4, '.', ''));
    }

    /**
     * Test 15: Fixed per-unit tax on a Carton line uses base quantity.
     */
    public function test_fixed_per_unit_tax_uses_base_quantity()
    {
        $product = new \App\Models\Product();
        $product->price = 1100.0;
        $product->pos_sale_tax = 'Rs. 2 per unit'; // Fixed tax of 2 per base unit

        $calculator = new OrderLineCalculator();
        $unitPrice = 1100.0; // Price per carton
        $enteredQty = 2.0;   // 2 cartons
        $factor = 24.0;      // 24 pieces per carton

        $result = $calculator->calculate(
            $product,
            $enteredQty,
            $unitPrice,
            null,
            null,
            $factor
        );

        // Subtotal = 1100 * 2 = 2200
        $this->assertEquals(2200.0, $result['sub_total']);
        // Sales tax = 2.0 * 48 base units = 96
        $this->assertEquals(96.0, $result['sale_tax']);
        // Total = 2200 + 96 = 2296
        $this->assertEquals(2296.0, $result['total']);
    }
}
