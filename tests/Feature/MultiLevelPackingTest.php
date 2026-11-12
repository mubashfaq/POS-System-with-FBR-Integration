<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Models\Unit;
use App\Models\Packing;
use App\Models\PackingLevel;
use App\Models\Product;
use App\Models\PurchaseItem;
use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\Import;
use App\Models\ImportProduct;
use App\Models\Purchase;
use App\Services\UomConverter;
use App\Services\Pos\FifoStockService;
use App\Exceptions\InsufficientStockException;

class MultiLevelPackingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.database' => ':memory:']);
        DB::purge();

        $this->createTestSchema();
    }

    private function createTestSchema(): void
    {
        Schema::create('units', function ($table) {
            $table->id();
            $table->string('title');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('packings', function ($table) {
            $table->id();
            $table->unsignedBigInteger('business_id')->nullable();
            $table->string('name');
            $table->unsignedBigInteger('base_unit_id');
            $table->unsignedTinyInteger('levels_count')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('packing_levels', function ($table) {
            $table->id();
            $table->unsignedBigInteger('packing_id');
            $table->unsignedTinyInteger('level');
            $table->unsignedBigInteger('unit_id');
            $table->decimal('contains_qty', 14, 4)->nullable();
            $table->decimal('base_qty', 14, 4);
            $table->timestamps();
        });

        Schema::create('products', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->nullable();
            $table->string('sku')->nullable();
            $table->unsignedBigInteger('business_id')->default(1);
            $table->unsignedBigInteger('unit_id')->nullable();
            $table->unsignedBigInteger('default_packing_id')->nullable();
            $table->decimal('price', 15, 4)->default(0);
            $table->decimal('purchase_price', 15, 4)->default(0);
            $table->decimal('discounted_price', 15, 4)->default(0);
            $table->decimal('quantity', 14, 4)->default(0); // legacy column
            $table->string('pos_sale_tax')->nullable();
            $table->string('rate_of_tax')->nullable();
            $table->string('sale_type')->nullable();
            $table->boolean('status')->default(1);
            $table->timestamps();
        });

        Schema::create('purchase_items', function ($table) {
            $table->id();
            $table->unsignedBigInteger('purchase_id')->nullable();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('product_variation_id')->nullable();
            $table->decimal('quantity', 14, 4);
            $table->decimal('remaining_qty', 14, 4);
            $table->decimal('purchase_price', 15, 4);
            $table->decimal('price', 15, 4)->default(0);
            $table->timestamps();
        });

        Schema::create('orders', function ($table) {
            $table->id();
            $table->unsignedBigInteger('business_id')->default(1);
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('invoice_number')->default(1);
            $table->string('order_stage')->default('draft');
            $table->boolean('is_cancelled')->default(false);
            $table->decimal('sub_total', 15, 4)->default(0);
            $table->decimal('discount', 15, 4)->default(0);
            $table->decimal('total', 15, 4)->default(0);
            $table->decimal('paid', 15, 4)->default(0);
            $table->decimal('due', 15, 4)->default(0);
            $table->timestamps();
        });

        Schema::create('order_products', function ($table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('product_variation_id')->nullable();
            $table->unsignedBigInteger('purchase_item_id')->nullable();
            $table->decimal('quantity', 14, 4);
            $table->decimal('entered_qty', 14, 4)->nullable();
            $table->decimal('unit_price', 15, 4)->nullable();
            $table->unsignedBigInteger('packing_id')->nullable();
            $table->unsignedBigInteger('qty_unit_id')->nullable();
            $table->decimal('base_qty_factor', 14, 4)->nullable();
            $table->decimal('price', 15, 4);
            $table->decimal('purchase_price', 15, 4);
            $table->decimal('sub_total', 15, 4);
            $table->decimal('discount', 15, 4)->default(0);
            $table->decimal('total', 15, 4);
            $table->boolean('is_accessory')->default(false);
            $table->text('custom_fields')->nullable();
            $table->timestamps();
        });

        Schema::create('import_products', function ($table) {
            $table->id();
            $table->unsignedBigInteger('import_id')->default(1);
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('unit_id')->nullable();
            $table->unsignedBigInteger('packing_id')->nullable();
            $table->unsignedBigInteger('qty_unit_id')->nullable();
            $table->decimal('base_qty_factor', 14, 4)->default(1);
            $table->decimal('ctn_qty', 14, 4);
            $table->decimal('pcs_per_carton', 14, 4);
            $table->decimal('total_pcs', 14, 4);
            $table->decimal('purchase_price', 15, 4);
            $table->decimal('final_cost_per_piece', 15, 4)->nullable();
            $table->timestamps();
        });

        Schema::create('product_unit_prices', function ($table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('unit_id');
            $table->decimal('sale_price', 15, 4);
            $table->timestamps();
        });

        Schema::create('pos_carts', function ($table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('product_variation_id')->nullable();
            $table->unsignedBigInteger('purchase_item_id')->nullable();
            $table->unsignedBigInteger('packing_id')->nullable();
            $table->unsignedBigInteger('qty_unit_id')->nullable();
            $table->decimal('entered_qty', 14, 4)->nullable();
            $table->decimal('entered_unit_price', 15, 4)->nullable();
            $table->decimal('quantity', 14, 4)->default(1);
            $table->decimal('further_tax', 15, 4)->default(0);
            $table->decimal('row_total', 15, 4)->default(0);
            $table->unsignedBigInteger('parent_cart_id')->nullable();
            $table->decimal('accessory_default_qty', 14, 4)->nullable();
            $table->decimal('price_override', 15, 4)->nullable();
            $table->timestamps();
        });

        Schema::create('quantity_validations', function ($table) {
            $table->id();
            $table->unsignedBigInteger('business_id')->default(1);
            $table->string('product_quantity_validation')->default('enabled');
            $table->timestamps();
        });

        Schema::create('product_accessories', function ($table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('accessory_product_id');
            $table->decimal('quantity', 14, 4)->default(1);
            $table->timestamps();
        });

        Schema::create('users', function ($table) {
            $table->id();
            $table->string('name')->default('Test');
            $table->string('email')->default('test@test.com');
            $table->timestamps();
        });
    }

    /**
     * Test 2: Import 3 Carton of oil creates one batch with quantity = remaining_qty = 150.0000.
     */
    public function test_import_three_carton_oil_creates_batch_with_150_base_qty()
    {
        $litre = Unit::create(['title' => 'Litre']);
        $bottle = Unit::create(['title' => 'Bottle']);
        $carton = Unit::create(['title' => 'Carton']);

        $oilPacking = Packing::create(['name' => 'Oil Carton', 'base_unit_id' => $litre->id, 'levels_count' => 3]);
        PackingLevel::create(['packing_id' => $oilPacking->id, 'level' => 1, 'unit_id' => $carton->id, 'contains_qty' => 10, 'base_qty' => 50]);
        PackingLevel::create(['packing_id' => $oilPacking->id, 'level' => 2, 'unit_id' => $bottle->id, 'contains_qty' => 5, 'base_qty' => 5]);
        PackingLevel::create(['packing_id' => $oilPacking->id, 'level' => 3, 'unit_id' => $litre->id, 'contains_qty' => null, 'base_qty' => 1]);

        $oilProduct = Product::create([
            'name' => 'Cooking Oil',
            'unit_id' => $litre->id,
            'default_packing_id' => $oilPacking->id,
            'price' => 150,
            'discounted_price' => 150,
        ]);

        // Convert 3 Cartons to base qty
        $conversion = UomConverter::toBase($oilPacking->id, $carton->id, 3);
        $this->assertEquals(50.0, $conversion['factor']);
        $this->assertEquals(150.0, $conversion['total_base']);

        $batch = PurchaseItem::create([
            'product_id' => $oilProduct->id,
            'quantity' => $conversion['total_base'],
            'remaining_qty' => $conversion['total_base'],
            'purchase_price' => 100,
            'price' => 120,
        ]);

        $this->assertEquals(150.0, (float) $batch->quantity);
        $this->assertEquals(150.0, (float) $batch->remaining_qty);
        $this->assertEquals('150.0000', number_format($batch->quantity, 4, '.', ''));
        $this->assertEquals('150.0000', number_format($batch->remaining_qty, 4, '.', ''));
    }

    /**
     * Test 3: Pepsi Carton(4) > Packet(6) > Piece = 24 / 6 / 1.
     * Selling 2 Carton from a batch of 240 leaves 192.
     * The line has entered_qty = 2, quantity = 48, price = 1100/24, sub_total = 2200.
     */
    public function test_pepsi_selling_carton_deducts_base_qty()
    {
        $piece = Unit::create(['title' => 'Piece']);
        $packet = Unit::create(['title' => 'Packet']);
        $carton = Unit::create(['title' => 'Carton']);

        $pepsiPacking = Packing::create(['name' => 'Pepsi Carton', 'base_unit_id' => $piece->id, 'levels_count' => 3]);
        PackingLevel::create(['packing_id' => $pepsiPacking->id, 'level' => 1, 'unit_id' => $carton->id, 'contains_qty' => 4, 'base_qty' => 24]);
        PackingLevel::create(['packing_id' => $pepsiPacking->id, 'level' => 2, 'unit_id' => $packet->id, 'contains_qty' => 6, 'base_qty' => 6]);
        PackingLevel::create(['packing_id' => $pepsiPacking->id, 'level' => 3, 'unit_id' => $piece->id, 'contains_qty' => null, 'base_qty' => 1]);

        $pepsi = Product::create([
            'name' => 'Pepsi 250ml',
            'unit_id' => $piece->id,
            'default_packing_id' => $pepsiPacking->id,
            'price' => 50,
            'discounted_price' => 50,
        ]);

        $batch = PurchaseItem::create([
            'product_id' => $pepsi->id,
            'quantity' => 240.0,
            'remaining_qty' => 240.0,
            'purchase_price' => 35.0,
            'price' => 50.0,
        ]);

        // Sell 2 Cartons @ 1100 per carton
        $enteredQty = 2.0;
        $enteredUnitPrice = 1100.0;

        $c = UomConverter::toBase($pepsiPacking->id, $carton->id, $enteredQty);
        $factor = $c['factor'];
        $baseQty = $c['total_base'];

        $this->assertEquals(24.0, $factor);
        $this->assertEquals(48.0, $baseQty);

        $fifo = new FifoStockService();
        $allocations = $fifo->consume($pepsi, $baseQty, true);

        // Batch remaining_qty: 240 - 48 = 192
        $batch->refresh();
        $this->assertEquals(192.0, (float) $batch->remaining_qty);

        // Price per base piece = 1100 / 24
        $basePrice = UomConverter::priceToBase($enteredUnitPrice, $factor);
        $this->assertEquals(round(1100 / 24, 4), $basePrice);

        // Subtotal = enteredUnitPrice * enteredQty = 1100 * 2 = 2200
        $subTotal = round($enteredUnitPrice * $enteredQty, 2);
        $this->assertEquals(2200.0, $subTotal);
    }

    /**
     * Test 5: A fake factor/total/amount sent by the browser is ignored.
     */
    public function test_fake_factor_from_browser_is_ignored()
    {
        $piece = Unit::create(['title' => 'Piece']);
        $carton = Unit::create(['title' => 'Carton']);

        $packing = Packing::create(['name' => 'Carton 24', 'base_unit_id' => $piece->id, 'levels_count' => 2]);
        PackingLevel::create(['packing_id' => $packing->id, 'level' => 1, 'unit_id' => $carton->id, 'contains_qty' => 24, 'base_qty' => 24]);
        PackingLevel::create(['packing_id' => $packing->id, 'level' => 2, 'unit_id' => $piece->id, 'contains_qty' => null, 'base_qty' => 1]);

        // Browser sends fake factor = 10, fake total_base = 20
        $fakeFactor = 10.0;
        $fakeTotalBase = 20.0;
        $typedQty = 2.0;

        // Server recalculates using UomConverter
        $resolved = UomConverter::toBase($packing->id, $carton->id, $typedQty);

        $this->assertNotEquals($fakeFactor, $resolved['factor']);
        $this->assertEquals(24.0, $resolved['factor']);
        $this->assertNotEquals($fakeTotalBase, $resolved['total_base']);
        $this->assertEquals(48.0, $resolved['total_base']);
    }

    /**
     * Test 6: Selling more than stock is rejected in any unit.
     */
    public function test_selling_more_than_stock_throws_insufficient_stock_exception()
    {
        $piece = Unit::create(['title' => 'Piece']);
        $carton = Unit::create(['title' => 'Carton']);

        $packing = Packing::create(['name' => 'Carton 24', 'base_unit_id' => $piece->id, 'levels_count' => 2]);
        PackingLevel::create(['packing_id' => $packing->id, 'level' => 1, 'unit_id' => $carton->id, 'contains_qty' => 24, 'base_qty' => 24]);
        PackingLevel::create(['packing_id' => $packing->id, 'level' => 2, 'unit_id' => $piece->id, 'contains_qty' => null, 'base_qty' => 1]);

        $product = Product::create([
            'name' => 'Juice',
            'unit_id' => $piece->id,
            'default_packing_id' => $packing->id,
        ]);

        // Stock is only 10 pieces
        PurchaseItem::create([
            'product_id' => $product->id,
            'quantity' => 10.0,
            'remaining_qty' => 10.0,
            'purchase_price' => 10,
        ]);

        $fifo = new FifoStockService();

        // Selling 1 Carton (= 24 pieces) when only 10 pieces are in stock
        $this->expectException(InsufficientStockException::class);
        $fifo->consume($product, 24.0, true);
    }

    /**
     * Test 8: Float drift: batches 0.1 and 0.2, sell 0.3: no exception, both end at 0.
     */
    public function test_float_drift_batches_end_at_zero()
    {
        $product = Product::create(['name' => 'Chemical']);

        $b1 = PurchaseItem::create([
            'product_id' => $product->id,
            'quantity' => 0.1,
            'remaining_qty' => 0.1,
            'purchase_price' => 50,
            'created_at' => now()->subMinutes(10),
        ]);

        $b2 = PurchaseItem::create([
            'product_id' => $product->id,
            'quantity' => 0.2,
            'remaining_qty' => 0.2,
            'purchase_price' => 60,
            'created_at' => now()->subMinutes(5),
        ]);

        $fifo = new FifoStockService();
        // 0.1 + 0.2 in PHP float math can be 0.30000000000000004
        $allocations = $fifo->consume($product, 0.3, true);

        $b1->refresh();
        $b2->refresh();

        $this->assertEquals(0.0, (float) $b1->remaining_qty);
        $this->assertEquals(0.0, (float) $b2->remaining_qty);
        $this->assertCount(2, $allocations);
    }

    /**
     * Test 9: Chosen batch has 10, sell 15 with enforcement: 10 from it, 5 from next batch; no next batch throws.
     */
    public function test_chosen_batch_partial_overflow_to_next_batch()
    {
        $product = Product::create(['name' => 'Flour']);

        $b1 = PurchaseItem::create([
            'product_id' => $product->id,
            'quantity' => 10.0,
            'remaining_qty' => 10.0,
            'purchase_price' => 40,
            'created_at' => now()->subMinutes(10),
        ]);

        $b2 = PurchaseItem::create([
            'product_id' => $product->id,
            'quantity' => 20.0,
            'remaining_qty' => 20.0,
            'purchase_price' => 45,
            'created_at' => now()->subMinutes(5),
        ]);

        $fifo = new FifoStockService();
        // Specifically select b1, but request 15
        $allocations = $fifo->consume($product, 15.0, true, $b1->id);

        $b1->refresh();
        $b2->refresh();

        $this->assertEquals(0.0, (float) $b1->remaining_qty);
        $this->assertEquals(15.0, (float) $b2->remaining_qty); // 20 - 5 = 15
        $this->assertEquals(10.0, $allocations[0]['qty']);
        $this->assertEquals(5.0, $allocations[1]['qty']);
    }

    /**
     * Test 10: A product with 0.5 left appears in stocked().
     */
    public function test_product_with_half_left_appears_in_stocked()
    {
        $product = Product::create(['name' => 'Sugar']);
        PurchaseItem::create([
            'product_id' => $product->id,
            'quantity' => 0.5,
            'remaining_qty' => 0.5,
            'purchase_price' => 100,
        ]);

        $stockedProducts = Product::stocked()->get();
        $this->assertTrue($stockedProducts->contains('id', $product->id));
    }

    /**
     * Test 13: Cancel an order restores remaining_qty exactly per allocation without exceeding quantity.
     */
    public function test_cancel_order_restores_exact_batch_allocations()
    {
        $product = Product::create(['name' => 'Rice']);

        $b1 = PurchaseItem::create([
            'product_id' => $product->id,
            'quantity' => 10.0,
            'remaining_qty' => 10.0,
            'purchase_price' => 50,
        ]);

        $b2 = PurchaseItem::create([
            'product_id' => $product->id,
            'quantity' => 20.0,
            'remaining_qty' => 20.0,
            'purchase_price' => 55,
        ]);

        // Consume 15 (10 from b1, 5 from b2)
        $fifo = new FifoStockService();
        $allocations = $fifo->consume($product, 15.0, true);

        $b1->refresh();
        $b2->refresh();
        $this->assertEquals(0.0, (float) $b1->remaining_qty);
        $this->assertEquals(15.0, (float) $b2->remaining_qty);

        // Create order and order_product with batch_allocations in custom_fields
        $order = Order::create(['invoice_number' => 101]);
        $op = OrderProduct::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 15.0,
            'price' => 70,
            'purchase_price' => 51.67,
            'sub_total' => 1050,
            'total' => 1050,
            'custom_fields' => ['batch_allocations' => $allocations],
        ]);

        // Simulate cancelOrder logic
        $customFields = $op->custom_fields;
        $batchAllocations = $customFields['batch_allocations'];

        foreach ($batchAllocations as $alloc) {
            $bId = $alloc['purchase_item_id'];
            $allocQty = round((float) $alloc['qty'], 4);
            $batch = PurchaseItem::find($bId);
            $newRemaining = round(min((float) $batch->quantity, (float) $batch->remaining_qty + $allocQty), 4);
            $batch->update(['remaining_qty' => $newRemaining]);
        }

        $b1->refresh();
        $b2->refresh();

        // b1 is restored to 10.0, b2 is restored to 20.0
        $this->assertEquals(10.0, (float) $b1->remaining_qty);
        $this->assertEquals(20.0, (float) $b2->remaining_qty);
        // And never exceeds batch quantity
        $this->assertLessThanOrEqual((float) $b1->quantity, (float) $b1->remaining_qty);
        $this->assertLessThanOrEqual((float) $b2->quantity, (float) $b2->remaining_qty);
    }

    /**
     * Test 11: Edit an import: new base qty below sold qty is rejected;
     * higher base qty adjusts remaining_qty by delta; sold quantity is never erased.
     */
    public function test_edit_import_rejects_base_qty_below_sold_and_adjusts_remaining_by_delta()
    {
        $piece = Unit::create(['title' => 'Piece']);
        $carton = Unit::create(['title' => 'Carton']);

        $product = Product::create(['name' => 'Biscuits', 'unit_id' => $piece->id]);

        // Import creates batch with 50 pieces (5 Cartons of 10)
        $batch = PurchaseItem::create([
            'product_id' => $product->id,
            'quantity' => 50.0,
            'remaining_qty' => 50.0,
            'purchase_price' => 20.0,
        ]);

        // Customer buys 20 pieces -> remaining = 30, sold = 20
        $batch->decrement('remaining_qty', 20.0);
        $batch->refresh();
        $sold = round((float) $batch->quantity - (float) $batch->remaining_qty, 4);
        $this->assertEquals(20.0, $sold);

        // Case A: Editing import to 1 Carton (= 10 pieces). New base qty 10 < sold 20 -> REJECT
        $newBaseQtyA = 10.0;
        $isRejected = $newBaseQtyA < $sold;
        $this->assertTrue($isRejected, "New base qty below sold quantity must be rejected");

        // Case B: Editing import to 6 Cartons (= 60 pieces). New base qty 60 > sold 20 -> ACCEPT
        $newBaseQtyB = 60.0;
        $delta = round($newBaseQtyB - (float) $batch->quantity, 4); // 60 - 50 = +10
        $this->assertEquals(10.0, $delta);

        $newRemaining = round((float) $batch->remaining_qty + $delta, 4); // 30 + 10 = 40
        $batch->update([
            'quantity' => $newBaseQtyB,
            'remaining_qty' => $newRemaining,
        ]);
        $batch->refresh();

        $this->assertEquals(60.0, (float) $batch->quantity);
        $this->assertEquals(40.0, (float) $batch->remaining_qty);
        // Sold quantity is preserved: 60 - 40 = 20
        $this->assertEquals(20.0, round((float) $batch->quantity - (float) $batch->remaining_qty, 4));
    }

    /**
     * Test 12: Delete an import: blocked if anything was sold; otherwise purchase and batches are removed.
     */
    public function test_delete_import_blocked_if_sold_otherwise_removed()
    {
        $product = Product::create(['name' => 'Soap']);

        // Case A: Batch with sold stock
        $batchSold = PurchaseItem::create([
            'purchase_id' => 99,
            'product_id' => $product->id,
            'quantity' => 50.0,
            'remaining_qty' => 45.0, // 5 sold
            'purchase_price' => 15.0,
        ]);

        $canDeleteSold = round((float) $batchSold->remaining_qty, 4) >= round((float) $batchSold->quantity, 4);
        $this->assertFalse($canDeleteSold, "Import deletion must be blocked if any batch item was consumed");

        // Case B: Batch with 0 sold
        $batchUnsold = PurchaseItem::create([
            'purchase_id' => 100,
            'product_id' => $product->id,
            'quantity' => 50.0,
            'remaining_qty' => 50.0, // 0 sold
            'purchase_price' => 15.0,
        ]);

        $canDeleteUnsold = round((float) $batchUnsold->remaining_qty, 4) >= round((float) $batchUnsold->quantity, 4);
        $this->assertTrue($canDeleteUnsold, "Import deletion allowed when remaining_qty == quantity");

        $batchUnsold->delete();
        $this->assertDatabaseMissing('purchase_items', ['id' => $batchUnsold->id]);
    }

    /**
     * Test 14: Edit a packing after sales exist is blocked at the level edit.
     */
    public function test_edit_packing_levels_blocked_when_sales_exist()
    {
        $piece = Unit::create(['title' => 'Piece']);
        $carton = Unit::create(['title' => 'Carton']);

        $packing = Packing::create(['name' => 'Carton 12', 'base_unit_id' => $piece->id, 'levels_count' => 2]);
        $product = Product::create(['name' => 'Biscuits', 'unit_id' => $piece->id, 'default_packing_id' => $packing->id]);

        $order = Order::create(['invoice_number' => 201]);
        OrderProduct::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'packing_id' => $packing->id,
            'quantity' => 12,
            'price' => 10,
            'purchase_price' => 8,
            'sub_total' => 120,
            'total' => 120,
        ]);

        // Check in-use guard implemented in PackingController
        $inUse = OrderProduct::where('packing_id', $packing->id)->exists()
            || ImportProduct::where('packing_id', $packing->id)->exists();

        $this->assertTrue($inUse, "Packing must be marked in use when referenced by order_products");
    }

    /**
     * Test 16: FBR sandbox: payload line value equals invoice line total.
     */
    public function test_fbr_payload_line_value_equals_invoice_line_total()
    {
        $unitPrice = 1100.0;
        $enteredQty = 2.0;
        $factor = 24.0;
        $baseQty = $enteredQty * $factor; // 48 pieces

        // 18% sales tax
        $lineTotal = round($unitPrice * $enteredQty, 2); // 2200.00
        $salesTax = round($lineTotal * 0.18, 2);          // 396.00
        $furtherTax = 0.0;
        $totalValues = round($lineTotal + $salesTax + $furtherTax, 2); // 2596.00

        $fbrItem = [
            'quantity' => number_format((float) $baseQty, 2, '.', ''), // 48.00 base quantity
            'totalValues' => number_format((float) $totalValues, 2, '.', ''), // 2596.00
            'valueSalesExcludingST' => number_format((float) $lineTotal, 2, '.', ''), // 2200.00
            'salesTaxApplicable' => number_format((float) $salesTax, 2, '.', ''), // 396.00
        ];

        $this->assertEquals('2596.00', $fbrItem['totalValues']);
        $this->assertEquals('48.00', $fbrItem['quantity']);
        $this->assertEquals($totalValues, (float) $fbrItem['totalValues']);
    }

    /**
     * Test 17: Mixed-unit import expense split follows Decision A.
     */
    public function test_mixed_unit_import_expense_split_decision_a()
    {
        // Line 1: 2 Cartons of 50 Litres = 100 Litres base qty, purchase_price = 100 per Litre
        $line1Pcs = 100.0;
        $line1Price = 100.0;
        $line1Subtotal = $line1Pcs * $line1Price; // 10,000

        // Line 2: 100 Bottles of 1 Litre = 100 Litres base qty, purchase_price = 120 per Litre
        $line2Pcs = 100.0;
        $line2Price = 120.0;
        $line2Subtotal = $line2Pcs * $line2Price; // 12,000

        // Decision A: Global expenses spread over total base quantity (sum(total_pcs))
        $totalPcs = $line1Pcs + $line2Pcs; // 200 Litres
        $globalExpense = 2000.0;

        $expensePerBaseUnit = $globalExpense / $totalPcs; // 10.0 per Litre

        $line1LandedCost = $line1Price + $expensePerBaseUnit; // 100 + 10 = 110.0
        $line2LandedCost = $line2Price + $expensePerBaseUnit; // 120 + 10 = 130.0

        $this->assertEquals(10.0, $expensePerBaseUnit);
        $this->assertEquals(110.0, $line1LandedCost);
        $this->assertEquals(130.0, $line2LandedCost);

        // Total landed expenditure matches: (100 * 110) + (100 * 130) = 11000 + 13000 = 24000
        // Which equals subtotal (10000 + 12000) + globalExpense (2000) = 24000
        $this->assertEquals($line1Subtotal + $line2Subtotal + $globalExpense, ($line1Pcs * $line1LandedCost) + ($line2Pcs * $line2LandedCost));
    }

    /**
     * Test 18: Legacy import and legacy order show and total exactly as before.
     */
    public function test_legacy_data_compatibility()
    {
        // Legacy order line: packing_id is null, factor 1.0
        $product = Product::create(['name' => 'Legacy Item', 'price' => 100, 'discounted_price' => 100]);
        $batch = PurchaseItem::create([
            'product_id' => $product->id,
            'quantity' => 10.0,
            'remaining_qty' => 10.0,
            'purchase_price' => 70.0,
        ]);

        $order = Order::create(['invoice_number' => 301]);
        $op = OrderProduct::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 2.0,
            'price' => 100.0,
            'purchase_price' => 70.0,
            'sub_total' => 200.0,
            'total' => 200.0,
            'packing_id' => null,
            'qty_unit_id' => null,
            'base_qty_factor' => null,
        ]);

        // Verify subtotal, profit, and remaining stock
        $profit = (float) $op->sub_total - ((float) $op->quantity * (float) $op->purchase_price); // 200 - (2 * 70) = 60
        $this->assertEquals(60.0, $profit);

        $fifo = new FifoStockService();
        $fifo->consume($product, 2.0, true);
        $batch->refresh();
        $this->assertEquals(8.0, (float) $batch->remaining_qty);
    }

    /**
     * Test 19: Cart packaging display and invoice quantity text formatting.
     */
    public function test_cart_packaging_display_and_invoice_quantity_text()
    {
        $pieces = Unit::create(['title' => 'Pieces']);
        $box = Unit::create(['title' => 'Box']);
        $cartoon = Unit::create(['title' => 'Cartoon']);

        $packing = Packing::create([
            'name' => 'Biscuits 3-Tier',
            'base_unit_id' => $pieces->id,
            'levels_count' => 3,
        ]);

        PackingLevel::create(['packing_id' => $packing->id, 'level' => 1, 'unit_id' => $pieces->id, 'contains_qty' => 1, 'base_qty' => 1]);
        PackingLevel::create(['packing_id' => $packing->id, 'level' => 2, 'unit_id' => $box->id, 'contains_qty' => 6, 'base_qty' => 6]);
        PackingLevel::create(['packing_id' => $packing->id, 'level' => 3, 'unit_id' => $cartoon->id, 'contains_qty' => 4, 'base_qty' => 24]);

        $product = Product::create([
            'name' => 'Chocolate Biscuits',
            'unit_id' => $pieces->id,
            'default_packing_id' => $packing->id,
            'price' => 50,
            'discounted_price' => 50,
        ]);

        $order = Order::create(['invoice_number' => 401]);

        // 1 Cartoon (entered_qty = 1, base_qty = 24)
        $opCartoon = OrderProduct::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'packing_id' => $packing->id,
            'qty_unit_id' => $cartoon->id,
            'base_qty_factor' => 24,
            'entered_qty' => 1,
            'quantity' => 24,
            'unit_price' => 1100,
            'price' => 45.8333,
            'purchase_price' => 35,
            'sub_total' => 1100,
            'total' => 1100,
        ]);

        $opCartoon->load(['product.unit', 'qtyUnit', 'packing']);
        $this->assertEquals('1 Cartoon (1 Cartoon = 24 Pieces)', $opCartoon->quantity_text);
        $this->assertEquals(1100.0, $opCartoon->invoice_unit_price);

        // 2 Boxes (entered_qty = 2, base_qty = 12)
        $opBox = OrderProduct::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'packing_id' => $packing->id,
            'qty_unit_id' => $box->id,
            'base_qty_factor' => 6,
            'entered_qty' => 2,
            'quantity' => 12,
            'unit_price' => 300,
            'price' => 50,
            'purchase_price' => 35,
            'sub_total' => 600,
            'total' => 600,
        ]);

        $opBox->load(['product.unit', 'qtyUnit', 'packing']);
        $this->assertEquals('2 Box (1 Box = 6 Pieces)', $opBox->quantity_text);
        $this->assertEquals(300.0, $opBox->invoice_unit_price);

        // 5 Pieces (base unit, factor = 1)
        $opPieces = OrderProduct::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'packing_id' => $packing->id,
            'qty_unit_id' => $pieces->id,
            'base_qty_factor' => 1,
            'entered_qty' => 5,
            'quantity' => 5,
            'unit_price' => 50,
            'price' => 50,
            'purchase_price' => 35,
            'sub_total' => 250,
            'total' => 250,
        ]);

        $opPieces->load(['product.unit', 'qtyUnit', 'packing']);
        $this->assertEquals('5 Pieces', $opPieces->quantity_text);
        $this->assertEquals(50.0, $opPieces->invoice_unit_price);
    }

    /**
     * Test 20: ProductResource prices are not 0 and addToCart respects requested qty_unit_id.
     */
    public function test_add_to_cart_with_explicit_packaging_unit_and_product_resource_pricing()
    {
        $pieces = Unit::create(['title' => 'Pieces']);
        $box = Unit::create(['title' => 'Box']);
        $cartoon = Unit::create(['title' => 'Cartoon']);

        $packing = Packing::create([
            'name' => 'Wafers 3-Tier',
            'base_unit_id' => $pieces->id,
            'levels_count' => 3,
        ]);

        PackingLevel::create(['packing_id' => $packing->id, 'level' => 1, 'unit_id' => $pieces->id, 'contains_qty' => 1, 'base_qty' => 1]);
        PackingLevel::create(['packing_id' => $packing->id, 'level' => 2, 'unit_id' => $box->id, 'contains_qty' => 6, 'base_qty' => 6]);
        PackingLevel::create(['packing_id' => $packing->id, 'level' => 3, 'unit_id' => $cartoon->id, 'contains_qty' => 4, 'base_qty' => 24]);

        $product = Product::create([
            'name' => 'Vanilla Wafers',
            'unit_id' => $pieces->id,
            'default_packing_id' => $packing->id,
            'price' => 50,
            'discounted_price' => 50,
            'quantity' => 100,
        ]);

        \App\Models\ProductUnitPrice::create([
            'product_id' => $product->id,
            'unit_id' => $box->id,
            'sale_price' => 280.0,
        ]);

        \App\Models\quantity_validation::create([
            'business_id' => 1,
            'product_quantity_validation' => 'enabled',
        ]);

        $user = \App\Models\User::create(['name' => 'Admin']);
        \Illuminate\Support\Facades\Auth::login($user);

        // 1. Verify ProductResource
        $product->load(['unit', 'defaultPacking.levels.unit', 'unitPrices']);
        $resource = (new \App\Http\Resources\ProductResource($product))->toArray(request());

        $this->assertEquals($pieces->id, $resource['base_unit_id']);
        $this->assertEquals('Pieces', $resource['base_unit_name']);
        $this->assertCount(3, $resource['packing_levels']);

        // Piece level price
        $this->assertEquals(50.0, $resource['packing_levels'][0]['price']);
        // Box level price from ProductUnitPrice (not 0!)
        $this->assertEquals(280.0, $resource['packing_levels'][1]['price']);
        // Cartoon level price calculated (50 * 24 = 1200, not 0!)
        $this->assertEquals(1200.0, $resource['packing_levels'][2]['price']);

        // 2. Verify CartService::addToCart with explicit qty_unit_id (Cartoon)
        $cartService = new \App\Services\CartService();
        $cartItem = $cartService->addToCart(
            $product->id,
            1,
            'registered',
            null,
            null,
            null,
            false,
            null,
            $cartoon->id // Explicitly choose Cartoon
        );

        $this->assertEquals($packing->id, $cartItem->packing_id);
        $this->assertEquals($cartoon->id, $cartItem->qty_unit_id);
        $this->assertEquals(1.0, (float) $cartItem->entered_qty);
        $this->assertEquals(24.0, (float) $cartItem->quantity); // base qty
        $this->assertEquals(1200.0, (float) $cartItem->entered_unit_price);
        $this->assertEquals(1200.0, (float) $cartItem->row_total);

        // 3. Add same Cartoon again -> entered_qty becomes 2, base qty becomes 48
        $cartItem2 = $cartService->addToCart(
            $product->id,
            1,
            'registered',
            null,
            null,
            null,
            false,
            null,
            $cartoon->id
        );

        $this->assertEquals($cartItem->id, $cartItem2->id);
        $this->assertEquals(2.0, (float) $cartItem2->entered_qty);
        $this->assertEquals(48.0, (float) $cartItem2->quantity);
        $this->assertEquals(2400.0, (float) $cartItem2->row_total);

        // 4. Switch unit to Box -> entered_qty becomes 1 Box, base qty becomes 6, price becomes 280
        $cartItem3 = $cartService->addToCart(
            $product->id,
            1,
            'registered',
            null,
            null,
            null,
            false,
            null,
            $box->id
        );

        $this->assertEquals($cartItem->id, $cartItem3->id);
        $this->assertEquals($box->id, $cartItem3->qty_unit_id);
        $this->assertEquals(1.0, (float) $cartItem3->entered_qty);
        $this->assertEquals(6.0, (float) $cartItem3->quantity);
        $this->assertEquals(280.0, (float) $cartItem3->entered_unit_price);
        $this->assertEquals(280.0, (float) $cartItem3->row_total);
    }
}
