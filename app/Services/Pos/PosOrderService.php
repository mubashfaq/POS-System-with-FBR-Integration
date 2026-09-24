<?php
// app/Services/Pos/PosOrderService.php
namespace App\Services\Pos;

use App\Models\{Business, BusinessCounter, Customer, Order, Product, quantity_validation};
use App\Services\Fbr\FbrInvoiceService;
use App\Services\FbrTokenService;
use Illuminate\Support\Facades\DB;
use Log;

class PosOrderService
{
    public function __construct(
        private OrderLineCalculator $calculator,
        private FifoStockService $fifo,
        private FbrInvoiceService $fbr,
    ) {
    }

    /**
     * $data = [
     *   business_id, customer_id, order_date, order_stage (draft|submitted),
     *   order_discount?, paid?,
     *   items => [['product_id'=>, 'qty'=>, 'price'=>?], ...]
     * ]
     */
    public function create(array $data, int $userId): array
    {
        $business = Business::findOrFail($data['business_id']);
        $customer = Customer::where('business_id', $business->id)->findOrFail($data['customer_id']);

        $qtyVal = quantity_validation::where('business_id', $business->id)->first();
        $enforce = $qtyVal && $qtyVal->product_quantity_validation === 'disabled'; // same rule as web

        $stage = $data['order_stage'] ?? 'draft';
        $discount = (float) ($data['order_discount'] ?? 0);
        $paid = (float) ($data['paid'] ?? 0);

        return DB::transaction(function () use ($data, $business, $customer, $enforce, $stage, $discount, $paid, $userId) {

            // Lock products up-front (also prevents duplicate-lock ordering issues)
            $productIds = collect($data['items'])->pluck('product_id')->unique()->sort()->values();
            $products = Product::where('business_id', $business->id)
                ->whereIn('id', $productIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($products->count() !== $productIds->count()) {
                abort(422, 'One or more products do not belong to this business');
            }

            $counter = BusinessCounter::lockForUpdate()
                ->firstOrCreate(['business_id' => $business->id], ['last_invoice_number' => 0]);
            $counter->increment('last_invoice_number');

            $env = FbrTokenService::getURLEnvironment($business->id);

            $order = Order::create([
                'customer_id' => $customer->id,
                'user_id' => $userId,
                'business_id' => $business->id,
                'invoice_number' => $counter->last_invoice_number,
                'order_date' => $data['order_date'],
                'invoice_environment' => $env,
                'POSID' => FbrTokenService::getPOSID($business->id),
                'fbr_integration_enabled' => 1,
            ]);

            $lines = [];
            $totals = ['amount' => 0, 'sale_tax' => 0, 'customer_tax' => 0];

            foreach ($data['items'] as $item) {
                $product = $products[$item['product_id']];
                $qty = (float) $item['qty'];
                $calc = $this->calculator->calculate(
                    $product,
                    $qty,
                    isset($item['price']) ? (float) $item['price'] : null,
                    $customer->customer_tax_type
                );

                $allocations = $this->fifo->consume($product, $qty, $enforce);

                // weighted average cost of the batches used
                $cost = collect($allocations)->sum(fn($a) => $a['qty'] * $a['cost']) / max($qty, 0.0001);

                $order->products()->create([
                    'quantity' => $qty,
                    'hsCode' => $product->hsCode ?? null,
                    'uom' => $product->uom ?? null,
                    'price' => $calc['unit_price'],
                    'purchase_price' => round($cost, 2),
                    'sub_total' => $calc['sub_total'],
                    'discount' => $calc['discount'],
                    'tax_rate' => $calc['rate_text'],
                    'sale_tax' => $calc['sale_tax'],
                    'customer_tax' => $calc['customer_tax'],
                    'further_tax' => 0,
                    'total' => $calc['total'],
                    'product_id' => $product->id,
                    'purchase_item_id' => $allocations[0]['purchase_item_id'] ?? null, // oldest batch used
                    'is_accessory' => false,
                ]);

                $totals['amount'] += $calc['total'];
                $totals['sale_tax'] += $calc['sale_tax'];
                $totals['customer_tax'] += $calc['customer_tax'];

                $lines[] = ['product' => $product, 'qty' => $qty, 'calc' => $calc];
            }

            $total = $totals['amount'] - $discount;
            $due = round($total - $paid, 2);

            $order->update([
                'sub_total' => $totals['amount'],
                'discount' => $discount,
                'total_sale_tax' => round($totals['sale_tax'], 2),
                'total_customer_tax' => round($totals['customer_tax'], 2),
                'total_further_tax' => 0,
                'paid' => $paid,
                'total' => round($total, 2),
                'due' => $due,
                'status' => $due <= 0,
                'order_stage' => $stage,
                'type' => 'pos_invoice',
            ]);

            $fbrResult = null;

            $tokens = FbrTokenService::getToken($business->id);
            $token = is_array($tokens) ? $tokens['pos_token'] : $tokens;
            $payload = $this->fbr->buildPayload($order, $customer, $lines);

            Log::info('payload', $payload);
            // throws FbrRejectedException -> transaction rolls back automatically
            $fbrResult = $this->fbr->send($business, $payload, $env, $token);

            $order->update([
                'fbr_invoice_no' => $fbrResult['invoice_number'],
                'api_status' => $fbrResult['http_status'],
                'api_message' => 1,
                'order_stage' => 'submitted',
            ]);

            return ['order' => $order->fresh('products'), 'fbr' => $fbrResult];
        });
    }
}