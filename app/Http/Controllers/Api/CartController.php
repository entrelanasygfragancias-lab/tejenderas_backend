<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CartController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $cart = Cart::with(['items.product'])->firstOrCreate(['user_id' => $user->id]);
        
        return response()->json($cart);
    }

    public function store(Request $request) 
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1',
            'unit_price' => 'nullable|numeric',
            'variants' => 'nullable|array'
        ]);

        $user = Auth::user();
        $cart = Cart::firstOrCreate(['user_id' => $user->id]);

        $product = Product::findOrFail($request->product_id);
        
        $unitPrice = $request->unit_price ?? $product->price;
        $variants = $request->variants;

        // Find existing item with same product and variants
        // We compare the JSON string of variants
        $variantsJson = $variants ? json_encode($variants) : null;
        
        $cartItem = $cart->items()
            ->where('product_id', $product->id)
            ->get()
            ->filter(function($item) use ($variantsJson) {
                return json_encode($item->variants) === $variantsJson;
            })
            ->first();

        if ($cartItem) {
            $cartItem->quantity += $request->quantity;
            $cartItem->unit_price = $unitPrice;
            $cartItem->save();
        } else {
            $cart->items()->create([
                'product_id' => $product->id,
                'quantity' => $request->quantity,
                'unit_price' => $unitPrice,
                'variants' => $variants
            ]);
        }

        return response()->json($cart->load('items.product'));
    }

    public function update(Request $request, $itemId)
    {
        $request->validate([
            'quantity' => 'required|integer|min:1'
        ]);

        $user = Auth::user();
        $cart = Cart::where('user_id', $user->id)->firstOrFail();
        
        $item = $cart->items()->where('id', $itemId)->firstOrFail();
        $item->quantity = $request->quantity;
        $item->save();

        return response()->json($item);
    }

    public function destroy($itemId)
    {
        $user = Auth::user();
        $cart = Cart::where('user_id', $user->id)->firstOrFail();
        
        $cart->items()->where('id', $itemId)->delete();

        return response()->json(['message' => 'Item removed']);
    }

    public function clear()
    {
        $user = Auth::user();
        $cart = Cart::where('user_id', $user->id)->first();

        if ($cart) {
            $cart->items()->delete();
        }

        return response()->json(['message' => 'Cart cleared']);
    }
}
