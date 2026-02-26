<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Sale;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SaleController extends Controller
{
    /**
     * Display a listing of sales.
     */
    public function index(Request $request)
    {
        $data = $this->getTransactionsData($request);
        $stats = $this->calculateStats($data['sales'], $data['orders'], $data['payments']);

        // Merge and Sort
        $allTransactions = $data['sales']
            ->concat($data['orders'])
            ->concat($data['payments'])
            ->sortByDesc('created_at')
            ->values();

        // Pagination
        $page = $request->input('page', 1);
        $perPage = 20;
        $paginatedItems = new \Illuminate\Pagination\LengthAwarePaginator(
            $allTransactions->forPage($page, $perPage)->values(),
            $allTransactions->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return response()->json([
            'stats' => $stats,
            'sales' => $paginatedItems
        ]);
    }

    private function getTransactionsData(Request $request)
    {
        $salesQuery = Sale::with(['user', 'items.product']);
        $ordersQuery = \App\Models\Order::with('user', 'items')->where('status', 'completed');
        $paymentsQuery = \App\Models\ContractPayment::with('contract');

        if ($request->period === 'daily') {
            $today = now()->today();
            $salesQuery->whereDate('created_at', $today);
            $ordersQuery->whereDate('created_at', $today);
            $paymentsQuery->whereDate('payment_date', $today);
        } elseif ($request->period === 'weekly') {
            $range = [now()->startOfWeek(), now()->endOfWeek()];
            $salesQuery->whereBetween('created_at', $range);
            $ordersQuery->whereBetween('created_at', $range);
            $paymentsQuery->whereBetween('payment_date', $range);
        } elseif ($request->period === 'monthly') {
            $range = [now()->startOfMonth(), now()->endOfMonth()];
            $salesQuery->whereBetween('created_at', $range);
            $ordersQuery->whereBetween('created_at', $range);
            $paymentsQuery->whereBetween('payment_date', $range);
        }

        return [
            'sales' => $salesQuery->orderBy('created_at', 'desc')->get(),
            'orders' => $ordersQuery->orderBy('created_at', 'desc')->get(),
            'payments' => $paymentsQuery->orderBy('payment_date', 'desc')->get(),
        ];
    }

    private function calculateStats($sales, $orders, $payments)
    {
        $posTotal = (float) $sales->sum('total');
        $ordersTotal = (float) $orders->sum('total');
        $contractsTotal = (float) $payments->sum('amount');
        
        $telasTotal = 0;
        $perfumeriaTotal = 0;
        $perfumeriaCatalogoTotal = 0;
        $perfumeriaDisenadorTotal = 0;

        $sales->transform(function ($sale) use (&$telasTotal, &$perfumeriaTotal, &$perfumeriaCatalogoTotal, &$perfumeriaDisenadorTotal) {
            $sale->type = 'pos';
            $saleTelas = 0;
            $salePerfumeria = 0;
            $salePerfumeriaCatalogo = 0;
            $salePerfumeriaDisenador = 0;
            
            foreach ($sale->items as $item) {
                if ($item->product && $item->product->category === 'perfumeria') {
                    $salePerfumeria += $item->subtotal;
                    if ($item->product->subcategory === 'catalogo') {
                        $salePerfumeriaCatalogo += $item->subtotal;
                    } elseif ($item->product->subcategory === 'disenador') {
                        $salePerfumeriaDisenador += $item->subtotal;
                    }
                } else {
                    $saleTelas += $item->subtotal;
                }
            }
            $telasTotal += $saleTelas;
            $perfumeriaTotal += $salePerfumeria;
            $perfumeriaCatalogoTotal += $salePerfumeriaCatalogo;
            $perfumeriaDisenadorTotal += $salePerfumeriaDisenador;

            $sale->telas_total = $saleTelas;
            $sale->perfumeria_total = $salePerfumeria;
            $sale->perfumeria_catalogo_total = $salePerfumeriaCatalogo;
            $sale->perfumeria_disenador_total = $salePerfumeriaDisenador;
            return $sale;
        });

        $orders->transform(function($order) {
            $order->type = 'order';
            $order->payment_method = 'web'; 
            return $order;
        });

        $payments->transform(function($payment) {
            $payment->type = 'contract_payment';
            $payment->created_at = $payment->payment_date;
            $payment->total = $payment->amount;
            $payment->user = [
                'name' => $payment->contract ? ($payment->contract->company_name . ' (' . $payment->contract->contact_person . ')') : 'Contrato Eliminado'
            ];
            $payment->details = $payment->notes;
            return $payment;
        });

        return [
            'total' => $posTotal + $ordersTotal + $contractsTotal,
            'pos_total' => $posTotal,
            'orders_total' => $ordersTotal,
            'contracts_total' => $contractsTotal,
            'telas' => $telasTotal,
            'perfumeria' => $perfumeriaTotal,
            'perfumeria_catalogo' => $perfumeriaCatalogoTotal,
            'perfumeria_disenador' => $perfumeriaDisenadorTotal
        ];
    }

    /**
     * Lookup product by barcode for POS.
     */
    public function lookupBarcode(Request $request)
    {
        $request->validate(['barcode' => 'required|string']);

        $products = Product::with(['attributes', 'attributeValues' => function($q) {
            $q->withPivot(['price_delta', 'base_price', 'markup', 'markup_type', 'stock', 'image']);
        }])->where('barcode', $request->barcode)->get();

        if ($products->isEmpty()) {
            return response()->json([
                'message' => 'Producto no encontrado'
            ], 404);
        }

        // We return all matches, the frontend will decide if it shows a list or picks one
        return response()->json($products);
    }

    /**
     * Store a new sale with items.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.variants' => 'nullable',
            'payment_method' => 'required|in:cash,card,transfer',
            'notes' => 'nullable|string',
        ]);

        try {
            DB::beginTransaction();

            $total = 0;
            $itemsData = [];

            // Validate stock and calculate totals
            foreach ($validated['items'] as $item) {
                $product = Product::findOrFail($item['product_id']);

                if ($product->stock < $item['quantity']) {
                    return response()->json([
                        'message' => "Stock insuficiente para {$product->name}. Disponible: {$product->stock}"
                    ], 400);
                }

                $unitPrice = $product->price;
                
                $variantsInput = $item['variants'] ?? null;
                if (is_string($variantsInput)) {
                    try { $variantsInput = json_decode($variantsInput, true); } catch (\Throwable $e) { $variantsInput = null; }
                }

                // If variants are selected, we MUST check and deduct stock from the pivot table (variants)
                // And also calculate the correct unit price including deltas
                if ($variantsInput && is_array($variantsInput)) {
                    foreach ($variantsInput as $vInfo) {
                        $unitPrice += ($vInfo['priceDelta'] ?? 0);
                        
                        // We need to find the specific value_id. 
                        $attrValue = \App\Models\AttributeValue::where('name', $vInfo['value'])->first();
                        if ($attrValue) {
                            $pivot = DB::table('product_attribute_values')
                                ->where('product_id', $product->id)
                                ->where('attribute_value_id', $attrValue->id)
                                ->first();

                            if ($pivot && $pivot->stock < $item['quantity']) {
                                throw new \Exception("Stock insuficiente para variante {$vInfo['value']}. Disponible: {$pivot->stock}");
                            }

                            if ($pivot) {
                                DB::table('product_attribute_values')
                                    ->where('id', $pivot->id)
                                    ->decrement('stock', $item['quantity']);
                            }
                        }
                    }
                }

                $subtotal = $unitPrice * $item['quantity'];
                $total += $subtotal;

                $itemsData[] = [
                    'product_id' => $product->id,
                    'quantity' => $item['quantity'],
                    'unit_price' => $unitPrice,
                    'subtotal' => $subtotal,
                    'variants' => $variantsInput,
                ];

                // Reduce general stock and track outflows
                $product->decrement('stock', $item['quantity']);
                $product->increment('stock_out_total', $item['quantity']);
                StockMovement::create([
                    'product_id' => $product->id,
                    'type' => 'out',
                    'quantity' => $item['quantity'],
                    'variants' => $variantsInput,
                ]);
            }

            // Create sale
            $sale = Sale::create([
                'user_id' => $request->user()->id,
                'total' => $total,
                'payment_method' => $validated['payment_method'],
                'notes' => $validated['notes'] ?? null,
            ]);

            // Create sale items
            foreach ($itemsData as $itemData) {
                $sale->items()->create($itemData);
            }

            DB::commit();

            return response()->json([
                'message' => 'Venta registrada exitosamente',
                'sale' => $sale->load('items.product'),
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Sale creation failed: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
            return response()->json([
                'message' => 'Error al procesar la venta',
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ], 500);
        }
    }

    /**
     * Display a specific sale.
     */
    public function show(Sale $sale)
    {
        return response()->json($sale->load(['user', 'items.product']));
    }
}
