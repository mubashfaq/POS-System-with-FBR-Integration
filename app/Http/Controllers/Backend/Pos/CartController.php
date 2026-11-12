<?php

namespace App\Http\Controllers\Backend\Pos;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Business;
use App\Models\CartSetting;
use App\Models\InvoiceSetting;
use App\Models\PosCart;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\PurchaseItem;
use App\Models\quantity_validation;
use App\Services\CartService;
use App\Services\FbrTokenService;
use Illuminate\Http\Request;

class CartController extends Controller
{

    public function index(Request $request)
    {
        $businessId = session('business_id');
        $businesstype = Business::where('id', $businessId)->value('business_type');
        $cartSetting = CartSetting::where('business_id', $businessId)->value('draft_btn');
        $fbrEnabled = FbrTokenService::isIntegrationEnabled();


        // clear cart
        PosCart::where('user_id', auth()->id())->delete();
        return view('backend.cart.index', [
            'businessType' => $businesstype,
            'draftButtonEnabled' => $cartSetting,
            'fbrEnabled' => $fbrEnabled,
        ]);
    }

    public function getCart()
    {
        // Get customer registration status from session 
        $customerIsRegistered = session('customer_is_registered', 'Registered'); // ✅ Default "Registered" capital

        $cartItems = PosCart::where('user_id', auth()->id())
            ->with([
                'product.unit',
                'product.defaultPacking.levels.unit',
                'product.unitPrices',
                'packing.levels.unit',
                'qtyUnit',
                'purchaseItem.purchase',
                'variation.values'
            ])
            ->latest('created_at')
            ->get()
            ->map(function ($item) use ($customerIsRegistered) {
                $factor = 1.0;
                $packing = $item->packing ?? $item->product?->defaultPacking;
                $qtyUnit = $item->qtyUnit;

                if ($item->packing_id || $item->product?->default_packing_id) {
                    $packingId = $item->packing_id ?: $item->product?->default_packing_id;
                    $qtyUnitId = $item->qty_unit_id ?: $item->product?->unit_id;
                    if ($packing && $packing->levels) {
                        $lvl = $packing->levels->firstWhere('unit_id', $qtyUnitId);
                        $factor = $lvl ? (float) $lvl->base_qty : 1.0;
                        if (!$qtyUnit && $lvl) {
                            $qtyUnit = $lvl->unit;
                        }
                    }
                    $unitPrice = (float) ($item->entered_unit_price ?? (($item->price_override ?? $item->variation?->price ?? $item->product->discounted_price) * $factor));
                    $enteredQty = (float) ($item->entered_qty ?? ($factor > 0 ? $item->quantity / $factor : $item->quantity));
                    $baseTotal = round($unitPrice * $enteredQty, 2);
                } else {
                    $unitPrice = (float) ($item->price_override ?? $item->variation?->price ?? $item->product->discounted_price);
                    $enteredQty = (float) $item->quantity;
                    $baseTotal = round($unitPrice * $enteredQty, 2);
                }

                $furtherTax = 0;
                $valueExTax = $baseTotal;
                $salesTax = 0;

                if ($item->product->sale_type != "3rd Schedule Goods" && strtolower($customerIsRegistered) === "unregistered") {
                    $rateText = trim($item->product->rate_of_tax);

                    if (str_ends_with($rateText, '%')) {
                        $rate = floatval(str_replace('%', '', $rateText));
                        $valueExTax = round($baseTotal / (1 + ($rate / 100)), 2);
                        $furtherTax = round($valueExTax * 0.04, 2);
                        $salesTax = round($baseTotal - $valueExTax, 2);
                    } else {
                        preg_match('/\d+(?:\.\d+)?/', $rateText, $m);
                        $ratePerUnit = (float) ($m[0] ?? 0);
                        $salesTax = round($ratePerUnit * (float) $item->quantity, 2);
                        $valueExTax = round($baseTotal - $salesTax, 2);
                        $furtherTax = round($valueExTax * 0.04, 2);
                    }
                }

                $totalWithTax = $baseTotal + $furtherTax;

                $packingLevels = [];
                if ($packing && $packing->levels) {
                    foreach ($packing->levels as $lvl) {
                        $uPrice = $item->product?->unitPrices?->firstWhere('unit_id', $lvl->unit_id)?->sale_price;
                        $suggested = $uPrice ?? round((float) $item->product?->discounted_price * (float) $lvl->base_qty, 2);
                        $packingLevels[] = [
                            'unit_id' => $lvl->unit_id,
                            'title' => $lvl->unit?->title ?? 'Unit',
                            'base_qty' => (float) $lvl->base_qty,
                            'contains_qty' => $lvl->contains_qty ? (float) $lvl->contains_qty : null,
                            'price' => (float) $suggested,
                        ];
                    }
                }

                $item->unit_price = $unitPrice;
                $item->entered_qty = $enteredQty;
                $item->base_total = $baseTotal;
                $item->further_tax = $furtherTax;
                $item->row_total = $totalWithTax;
                $item->customer_is_registered = $customerIsRegistered;
                $item->is_accessory = !is_null($item->parent_cart_id);
                $item->base_qty_factor = $factor;
                $item->base_unit_name = $item->product?->unit?->title ?? 'Piece';
                $item->qty_unit_name = $qtyUnit?->title ?? $item->base_unit_name;
                $item->packing_levels = $packingLevels;

                return $item;
            });

        // Totals from parent items only (accessories don't contribute to price)
        $parentItems = $cartItems->filter(fn($i) => is_null($i->parent_cart_id));

        $subTotal = $parentItems->sum('base_total');
        $furtherTaxTotal = $parentItems->sum('further_tax');
        $grandTotal = $parentItems->sum('row_total');

        return response()->json([
            'carts' => $cartItems->values(),
            'sub_total' => round($subTotal, 2),
            'further_tax_total' => round($furtherTaxTotal, 2),
            'total' => round($grandTotal, 2)
        ]);
    }

    public function getProducts(Request $request)
    {
        $businessId = session('business_id');

        $quantityVal = quantity_validation::where('business_id', $businessId)->first();

        $products = Product::query()->where('business_id', $businessId)->with([
            'unit',
            'defaultPacking.levels.unit',
            'unitPrices',
            'variations' => function ($query) {
                $query->where('status', 1)->withSum('purchaseItems', 'remaining_qty');
            }
        ]);

        if ($quantityVal?->product_quantity_validation == "disabled") {
            $products->active()->stocked();
        }

        $products->when($request->search, function ($query, $search) {
            $query->where('name', 'LIKE', "%{$search}%");
        });

        $products->when($request->barcode, function ($query, $barcode) {
            $query->where('sku', $barcode);
        });

        $products = $products->latest()->paginate(96);

        if ($request->wantsJson()) {
            return ProductResource::collection($products);
        }
    }

    public function getQuantityValidation()
    {
        $businessId = session('business_id');
        $quantityVal = quantity_validation::where('business_id', $businessId)->firstOrFail();

        return response()->json([
            'quantity_validation' => $quantityVal->product_quantity_validation
        ]);
    }

    public function store(Request $request, CartService $cartService)
    {
        $request->validate([
            'id' => 'required|exists:products,id',
            'product_variation_id' => 'nullable|integer|exists:product_variations,id',
            'purchase_item_id' => 'nullable|integer|exists:purchase_items,id',
            'qty_unit_id' => 'nullable|integer|exists:units,id',
        ]);

        try {

            $cartItem = $cartService->addToCart(
                $request->id,
                session('business_id'),
                session('registration_status') ?? 'registered',
                $request->purchase_item_id,
                null,   // parentCartId
                null,   // overrideQty
                false,  // skipStockCheck
                $request->product_variation_id,
                $request->qty_unit_id
            );

            return response()->json([
                'message' => 'Product added to cart',
                'quantity' => $cartItem->quantity,
                'further_tax' => $cartItem->further_tax,
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 400);
        }
    }

    public function storeNewDigital(Request $request, CartService $cartService)
    {
        $request->validate([
            'id' => 'required|exists:products,id',
            'product_variation_id' => 'nullable|integer|exists:product_variations,id',
            'purchase_item_id' => 'nullable|integer|exists:purchase_items,id',
            'qty_unit_id' => 'nullable|integer|exists:units,id',
        ]);

        try {

            $cartItem = $cartService->addToCartNew(
                $request->id,
                session('business_id'),
                session('registration_status') ?? 'registered',
                $request->purchase_item_id,
                null,   // parentCartId
                null,   // overrideQty
                false,  // skipStockCheck
                $request->product_variation_id,
                $request->qty_unit_id
            );

            return response()->json([
                'message' => 'Product added to cart',
                'quantity' => $cartItem->quantity,
                'further_tax' => $cartItem->further_tax,
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 400);
        }
    }

    public function increment(Request $request, CartService $cartService)
    {
        $request->validate([
            'id' => 'required|integer|exists:pos_carts,id'
        ]);

        try {
            $cart = $cartService->increment($request->id, session('business_id'));

            return response()->json(['message' => 'Cart Updated successfully'], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 400);
        }
    }

    public function decrement(Request $request, CartService $cartService)
    {
        $request->validate([
            'id' => 'required|integer|exists:pos_carts,id'
        ]);
        try {
            $cart = $cartService->decrement($request->id);
            return response()->json(['message' => 'Cart Updated successfully'], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 400);
        }
    }

    public function delete(Request $request, CartService $cartService)
    {
        $request->validate([
            'id' => 'required|integer|exists:pos_carts,id'
        ]);

        try {
            $cart = $cartService->delete($request->id);
            return response()->json(['message' => 'Item successfully deleted'], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 400);
        }


    }

    public function empty(CartService $cartService)
    {
        $deletedCount = $cartService->clearCart();

        if ($deletedCount > 0) {
            return response()->json(['message' => 'Cart successfully cleared'], 200);
        }

        return response()->json(['message' => 'Cart is already empty'], 204);
    }

    public function updateQuantity(Request $request, CartService $cartService)
    {
        $request->validate([
            'id' => 'required|exists:pos_carts,id',
            'quantity' => 'required',
        ]);

        try {

            $registrationStatus = session('registration_status') ?? 'registered';

            $cart = $cartService->updateQuantity(
                $request->id,
                $request->quantity,
                $registrationStatus,
                session('business_id')
            );

            return response()->json([
                'message' => 'Quantity updated successfully',
                'quantity' => $cart->quantity,
                'row_total' => $cart->row_total,
                'further_tax' => $cart->further_tax,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 400);
        }
    }

    public function updatePrice(Request $request, CartService $cartService)
    {
        $request->validate([
            'id' => 'required|exists:pos_carts,id',
            'price' => 'required|numeric|min:0'
        ]);

        try {

            $cart = $cartService->updatePrice($request->id, $request->price);

            return response()->json([
                'message' => 'Price updated successfully',
                'price' => $cart->price_override,
                'row_total' => $cart->row_total
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 400);
        }
    }

    public function setUnit(Request $request, CartService $cartService)
    {
        $request->validate([
            'id' => 'required|exists:pos_carts,id',
            'qty_unit_id' => 'required|exists:units,id',
        ]);

        try {
            $registrationStatus = session('registration_status') ?? 'registered';
            $cart = $cartService->setUnit(
                $request->id,
                (int) $request->qty_unit_id,
                $registrationStatus,
                session('business_id')
            );

            return response()->json([
                'message' => 'Unit updated successfully',
                'cart' => $cart,
                'quantity' => $cart->quantity,
                'entered_qty' => $cart->entered_qty,
                'entered_unit_price' => $cart->entered_unit_price,
                'row_total' => $cart->row_total,
                'further_tax' => $cart->further_tax,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 400);
        }
    }

    public function digitalInvoice(Request $request)
    {
        $businessId = session('business_id');
        $cartSetting = CartSetting::where('business_id', $businessId)->first(['draft_btn', 'custom_field_btn']);
        $invoiceSetting = InvoiceSetting::where('business_id', $businessId)->first(['invoice_number', 'po_number']);
        $fbrEnabled = FbrTokenService::isIntegrationEnabled();

        // clear cart
        PosCart::where('user_id', auth()->id())->delete();

        if ($request->route()->getName() === 'backend.admin.cart.digitalInvoiceNew') {
            return view('backend.cart.digital-invoice-new', [
                'draftButtonEnabled' => $cartSetting?->draft_btn,
                'customFieldButton' => $cartSetting?->custom_field_btn,
                'invoiceNumber' => $invoiceSetting?->invoice_number,
                'poNumber' => $invoiceSetting?->po_number,
                'fbrEnabled' => $fbrEnabled,
            ]);
        }
        return view('backend.cart.digital-invoice', [
            'draftButtonEnabled' => $cartSetting?->draft_btn,
            'invoiceNumber' => $invoiceSetting?->invoice_number,
            'poNumber' => $invoiceSetting?->po_number,
            'fbrEnabled' => $fbrEnabled,
        ]);

    }

    public function getdigitalInvoiceCart()
    {
        // Get customer registration status from session 
        $customerIsRegistered = session('customer_is_registered', 'Registered'); // ✅ Default "Registered" capital

        $cartItems = PosCart::where('user_id', auth()->id())
            ->with([
                'product.unit',
                'product.defaultPacking.levels.unit',
                'product.unitPrices',
                'packing.levels.unit',
                'qtyUnit',
                'purchaseItem.purchase',
                'variation.values'
            ])
            ->oldest('created_at')
            ->get()
            ->map(function ($item) use ($customerIsRegistered) {
                $factor = 1.0;
                $packing = $item->packing ?? $item->product?->defaultPacking;
                $qtyUnit = $item->qtyUnit;

                if ($item->packing_id || $item->product?->default_packing_id) {
                    $packingId = $item->packing_id ?: $item->product?->default_packing_id;
                    $qtyUnitId = $item->qty_unit_id ?: $item->product?->unit_id;
                    if ($packing && $packing->levels) {
                        $lvl = $packing->levels->firstWhere('unit_id', $qtyUnitId);
                        $factor = $lvl ? (float) $lvl->base_qty : 1.0;
                        if (!$qtyUnit && $lvl) {
                            $qtyUnit = $lvl->unit;
                        }
                    }
                    $unitPrice = (float) ($item->entered_unit_price ?? (($item->price_override ?? $item->variation?->price ?? $item->product->discounted_price) * $factor));
                    $enteredQty = (float) ($item->entered_qty ?? ($factor > 0 ? $item->quantity / $factor : $item->quantity));
                    $baseTotal = round($unitPrice * $enteredQty, 2);
                } else {
                    $unitPrice = (float) ($item->price_override ?? $item->variation?->price ?? $item->product->discounted_price);
                    $enteredQty = (float) $item->quantity;
                    $baseTotal = round($unitPrice * $enteredQty, 2);
                }

                $furtherTax = 0;
                $valueExTax = $baseTotal;
                $salesTax = 0;

                if (
                    $item->product->sale_type != "3rd Schedule Goods" &&
                    strtolower($customerIsRegistered) === "unregistered"
                ) {
                    $rateText = trim($item->product->rate_of_tax);

                    if (str_ends_with($rateText, '%')) {
                        $rate = floatval(str_replace('%', '', $rateText));
                        $valueExTax = round($baseTotal / (1 + ($rate / 100)), 2);
                        $furtherTax = round($valueExTax * 0.04, 2);
                        $salesTax = round($baseTotal - $valueExTax, 2);
                    } else {
                        preg_match('/\d+(?:\.\d+)?/', $rateText, $m);
                        $ratePerUnit = (float) ($m[0] ?? 0);
                        $salesTax = round($ratePerUnit * (float) $item->quantity, 2);
                        $valueExTax = round($baseTotal - $salesTax, 2);
                        $furtherTax = round($valueExTax * 0.04, 2);
                    }
                }

                $totalWithTax = $baseTotal + $furtherTax;

                $packingLevels = [];
                if ($packing && $packing->levels) {
                    foreach ($packing->levels as $lvl) {
                        $uPrice = $item->product?->unitPrices?->firstWhere('unit_id', $lvl->unit_id)?->sale_price;
                        $suggested = $uPrice ?? round((float) $item->product?->discounted_price * (float) $lvl->base_qty, 2);
                        $packingLevels[] = [
                            'unit_id' => $lvl->unit_id,
                            'title' => $lvl->unit?->title ?? 'Unit',
                            'base_qty' => (float) $lvl->base_qty,
                            'contains_qty' => $lvl->contains_qty ? (float) $lvl->contains_qty : null,
                            'price' => (float) $suggested,
                        ];
                    }
                }

                $item->unit_price = $unitPrice;
                $item->entered_qty = $enteredQty;
                $item->base_total = $baseTotal;
                $item->further_tax = $furtherTax;
                $item->row_total = $totalWithTax;
                $item->customer_is_registered = $customerIsRegistered;
                // Accessory flag for frontend grouping
                $item->is_accessory = !is_null($item->parent_cart_id);
                $item->base_qty_factor = $factor;
                $item->base_unit_name = $item->product?->unit?->title ?? 'Piece';
                $item->qty_unit_name = $qtyUnit?->title ?? $item->base_unit_name;
                $item->packing_levels = $packingLevels;

                return $item;
            });

        // Totals from parent items only
        $parentItems = $cartItems->filter(fn($i) => is_null($i->parent_cart_id));

        $subTotal = $parentItems->sum('base_total');
        $furtherTaxTotal = $parentItems->sum('further_tax');
        $grandTotal = $parentItems->sum('row_total');

        return response()->json([
            'carts' => $cartItems->values(),
            'sub_total' => round($subTotal, 2),
            'further_tax_total' => round($furtherTaxTotal, 2),
            'total' => round($grandTotal, 2)
        ]);
    }

    /**
     * Get available purchase batches for a product.
     */
    public function getBatches($productId, $variationId = null)
    {
        $businessId = session('business_id');

        $product = Product::where('id', $productId)
            ->where('business_id', $businessId)
            ->firstOrFail();

        if ($product->has_variations) {
            abort_if(!$variationId, 422, 'A variation is required for this product.');
            $variation = ProductVariation::where('id', $variationId)
                ->where('product_id', $product->id)
                ->firstOrFail();
        } else {
            $variation = null;
        }

        $quantityVal = quantity_validation::where('business_id', $businessId)->first();
        $enforce = $quantityVal->product_quantity_validation == "disabled";

        $query = PurchaseItem::where('product_id', $productId)
            ->where('product_variation_id', $variationId) // null matches null for simple products
            ->with('purchase:id,date,supplier_id')
            ->orderBy('created_at', 'desc');

        if ($enforce) {
            $query->where('remaining_qty', '>', 0);
        }

        $batches = $query->get()->map(fn($batch) => [
            'id' => $batch->id,
            'purchase_id' => $batch->purchase_id,
            'purchase_price' => $batch->purchase_price,
            'price' => $batch->price,
            'remaining_qty' => $batch->remaining_qty,
            'quantity' => $enforce ? $batch->quantity : max($batch->quantity, 1),
            'purchase_date' => $batch->purchase
                ? \Carbon\Carbon::parse($batch->purchase->date)->format('Y-m-d')
                : $batch->created_at->format('Y-m-d'),
            'created_at' => $batch->created_at,
        ]);

        return response()->json([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'variation_id' => $variation?->id,
            'variation_label' => $variation?->label,
            'total_stock' => $variation ? $variation->quantity : $product->quantity,
            'batches' => $batches,
            'is_batch_enabled' => \App\Models\Business::find($businessId)->enable_batch ?? true,
        ]);
    }

    public function updateCustomFields(Request $request)
    {
        $request->validate(['id' => 'required', 'custom_fields' => 'nullable|array']);
        $cart = PosCart::findOrFail($request->id);
        $cart->custom_fields = array_merge($cart->custom_fields ?? [], $request->custom_fields ?? []);
        $cart->save();
        return response()->json(['message' => 'Saved', 'cart' => $cart]);
    }

}