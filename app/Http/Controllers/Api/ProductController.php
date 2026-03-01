<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;


class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Product::with(['category', 'subcategory', 'attributes', 'attributeValues.attribute']);

        if ($request->has('category')) {
            $catSearch = strtolower($request->category);
            $query->where(function($q) use ($catSearch) {
                $q->whereRaw('LOWER(category) = ?', [$catSearch]) // Legacy column
                  ->orWhereHas('category', function($sq) use ($catSearch) {
                      $sq->where('slug', $catSearch)
                         ->orWhereRaw('LOWER(name) = ?', [$catSearch]);
                  });
            });
        }

        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        $products = $query
            ->select('products.*')
            ->selectSub(function ($sub) {
                $sub->from('sale_items')
                    ->selectRaw('COALESCE(SUM(quantity), 0)')
                    ->whereColumn('sale_items.product_id', 'products.id');
            }, 'sold_quantity')
            ->selectSub(function ($sub) {
                $sub->from('stock_movements')
                    ->selectRaw("COALESCE(SUM(CASE WHEN type = 'out' THEN quantity ELSE 0 END), 0)")
                    ->whereColumn('stock_movements.product_id', 'products.id');
            }, 'calculated_stock_out')
            ->selectSub(function ($sub) {
                $sub->from('stock_movements')
                    ->selectRaw("COALESCE(SUM(CASE WHEN type = 'in' THEN quantity ELSE 0 END), 0)")
                    ->whereColumn('stock_movements.product_id', 'products.id');
            }, 'calculated_stock_in')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($products);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $this->validateProduct($request);

        try {
            if ($request->hasFile('image')) {
                $validated['image'] = Cloudinary::upload($request->file('image')->getRealPath())->getSecurePath();
            }

            if ($request->hasFile('images')) {
                $validated['images'] = $this->handleMultipleImages($request->file('images'));
            }
        } catch (\Throwable $e) {
            \Log::error('Cloudinary upload error: ' . $e->getMessage());
            return response()->json(['message' => 'Error al subir la imagen a Cloudinary: ' . $e->getMessage()], 500);
        }

        try {
            DB::beginTransaction();
            
            $product = Product::create(collect($validated)->except(['attributes', 'attribute_values'])->toArray());
            
            $this->syncAttributesAndValues($request, $product);
            $product->updateVariantsJson();

            DB::commit();
            return response()->json($product->load(['category', 'subcategory', 'attributes', 'attributeValues.attribute']), 201);

        } catch (\Throwable $e) {
            DB::rollBack();
            \Log::error('Error storing product: ' . $e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
            return response()->json(['message' => 'Error al guardar el producto: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Product $product)
    {
        return response()->json($product->load(['category', 'subcategory', 'attributes', 'attributeValues.attribute']));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Product $product)
    {
        $validated = $this->validateProduct($request, $product->id);

        try {
            if ($request->hasFile('image')) {
                $validated['image'] = Cloudinary::upload($request->file('image')->getRealPath())->getSecurePath();
            }

            if ($request->hasFile('images')) {
                if ($product->images) {
                    foreach ($product->images as $oldImg) {
                        // Optionally handle cloudinary delete here if needed
                    }
                }
                $validated['images'] = $this->handleMultipleImages($request->file('images'));
            }
        } catch (\Throwable $e) {
            \Log::error('Cloudinary update upload error: ' . $e->getMessage());
            return response()->json(['message' => 'Error al subir la imagen a Cloudinary en actualización: ' . $e->getMessage()], 500);
        }

        try {
            DB::beginTransaction();

            $product->update(collect($validated)->except(['attributes', 'attribute_values'])->toArray());

            $this->syncAttributesAndValues($request, $product);
            $product->updateVariantsJson();

            DB::commit();
            return response()->json($product->load(['category', 'subcategory', 'attributes', 'attributeValues.attribute']));
        } catch (\Throwable $e) {
            DB::rollBack();
            \Log::error('Error updating product: ' . $e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
            return response()->json(['message' => 'Error al actualizar el producto: ' . $e->getMessage()], 500);
        }
    }

    private function validateProduct(Request $request, $id = null)
    {
        return $request->validate([
            'barcode' => $id ? 'sometimes|required|max:255|unique:products,barcode,' . $id : 'required|unique:products|max:255',
            'name' => 'required|max:255',
            'category' => 'nullable|string',
            'category_id' => 'nullable|exists:categories,id',
            'subcategory_id' => 'nullable|exists:subcategories,id',
            'brand' => 'nullable|string|max:255',
            'subcategory' => 'nullable|string|max:255',
            'is_promo' => 'nullable|boolean',
            'is_combo' => 'nullable|boolean',
            'description' => 'nullable',
            'base_price' => $id ? 'nullable|numeric|min:0' : 'required|numeric|min:0',
            'markup' => 'nullable|numeric|min:0',
            'markup_type' => 'nullable|string|in:percentage,manual',
            'price' => $id ? 'nullable|numeric|min:0' : 'required|numeric|min:0',
            'stock' => $id ? 'sometimes|required|integer|min:0' : 'required|integer|min:0',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'images' => 'nullable|array',
            'images.*' => 'image|mimes:jpeg,png,jpg,gif|max:2048',
            'attributes' => 'nullable|array',
            'attribute_values' => 'nullable|array',
        ]);
    }

    private function handleMultipleImages($imageFiles)
    {
        $galleryPaths = [];
        foreach ($imageFiles as $imgFile) {
            $galleryPaths[] = Cloudinary::upload($imgFile->getRealPath())->getSecurePath();
        }
        return $galleryPaths;
    }

    private function syncAttributesAndValues(Request $request, Product $product)
    {
        if ($request->has('attributes')) {
            $product->attributes()->sync($request->input('attributes'));
        }

        if ($request->has('attribute_values')) {
            $values = $request->input('attribute_values');
            $syncData = [];
            
            $priceDeltas = $request->input('attribute_value_price_deltas') ?? [];
            $basePrices = $request->input('attribute_value_base_prices') ?? [];
            $markups = $request->input('attribute_value_markups') ?? [];
            $markupTypes = $request->input('attribute_value_markup_types') ?? [];
            $valueStocks = $request->input('attribute_value_stocks') ?? [];
            $images = $request->file('value_images') ?? [];

            foreach ($values as $valueId) {
                $pivotData = [
                    'price_delta' => (isset($priceDeltas[$valueId]) ? $priceDeltas[$valueId] : 0),
                    'base_price' => (isset($basePrices[$valueId]) ? $basePrices[$valueId] : 0),
                    'markup' => (isset($markups[$valueId]) ? $markups[$valueId] : 0),
                    'markup_type' => (isset($markupTypes[$valueId]) ? $markupTypes[$valueId] : 'percentage'),
                    'stock' => (isset($valueStocks[$valueId]) ? $valueStocks[$valueId] : 0),
                ];

                if (isset($images[$valueId])) {
                    $path = Cloudinary::upload($images[$valueId]->getRealPath())->getSecurePath();
                    $pivotData['image'] = $path;
                } elseif ($request->input("keep_value_image_{$valueId}")) {
                    $existing = $product->attributeValues()->where('attribute_values.id', $valueId)->first();
                    if ($existing && $existing->pivot->image) {
                        $pivotData['image'] = $existing->pivot->image;
                    }
                }

                $syncData[$valueId] = $pivotData;
            }

            $product->attributeValues()->sync($syncData);
        }
    }

    /**
     * Display the specified resource.
     */
    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Product $product)
    {
        $product->delete();

        return response()->json(['message' => 'Producto eliminado correctamente.']);
    }

    /**
     * Register a stock entry for a product.
     */
    public function addStock(Request $request, Product $product)
    {
        $validated = $request->validate([
            'quantity' => 'required|integer|min:1',
            'variants' => 'nullable',
        ]);

        $quantity = (int) $validated['quantity'];
        $variantsInput = $request->input('variants');
        if (is_string($variantsInput)) {
            try { $variantsInput = json_decode($variantsInput, true); } catch (\Throwable $e) { $variantsInput = null; }
        }
        $product->increment('stock', $quantity);
        $product->increment('stock_in_total', $quantity);

        // Update variant-specific stock if applicable
        $variantValueIds = $request->input('variant_value_ids', []);
        if (!empty($variantValueIds)) {
            foreach ($variantValueIds as $valueId) {
                DB::table('product_attribute_values')
                    ->where('product_id', $product->id)
                    ->where('attribute_value_id', $valueId)
                    ->update([
                        'stock' => DB::raw("stock + $quantity"),
                        'stock_in_total' => DB::raw("stock_in_total + $quantity"),
                    ]);
            }
            $product->updateVariantsJson();
        }

        StockMovement::create([
            'product_id' => $product->id,
            'type' => 'in',
            'quantity' => $quantity,
            'variants' => $variantsInput,
        ]);

        return response()->json([
            'message' => 'Ingreso de stock registrado',
            'product' => $product,
        ]);
    }

    /**
     * Check if a barcode exists.
     */
    public function check($barcode)
    {
        $product = Product::with(['category', 'subcategory', 'attributes', 'attributeValues.attribute'])
            ->where('barcode', $barcode)
            ->first();

        if ($product) {
            return response()->json(['exists' => true, 'product' => $product]);
        }

        return response()->json(['exists' => false]);
    }

    /**
     * Generate a unique barcode.
     */
    public function generate()
    {
        do {
            $barcode = str_pad(mt_rand(0, 999999999999), 12, '0', STR_PAD_LEFT);
        } while (Product::where('barcode', $barcode)->exists());

        return response()->json(['barcode' => $barcode]);
    }
}

