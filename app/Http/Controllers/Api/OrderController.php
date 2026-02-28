<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\Order;
use App\Models\AdminNotification;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

use Illuminate\Support\Facades\Storage;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;

class OrderController extends Controller
{
    public function index()
    {
        $orders = Auth::user()->orders()->with('items.product')->latest()->get();
        return response()->json($orders);
    }

    public function indexAdmin()
    {
        $orders = Order::with('user', 'items.product')->latest()->get();
        return response()->json($orders);
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        $cart = Cart::with('items.product')->where('user_id', $user->id)->first();

        if (!$cart || $cart->items->isEmpty()) {
            return response()->json(['message' => 'Cart is empty'], 400);
        }

        // Validate shipping info if not already in user profile, strictly we should validate request inputs 
        // but for now we will rely on user profile + request overrrides if needed.
        // For simplicity, let's assume we use user's profile data if not provided
        
        $shippingAddress = $request->address ?? $user->address;
        $city = $request->city ?? $user->city;
        $department = $request->department ?? $user->department;
        $phone = $request->phone ?? $user->phone;

        if (!$shippingAddress || !$city || !$phone) {
             return response()->json(['message' => 'Shipping information is incomplete'], 400);
        }

        // Calculate total
        $subtotal = $cart->items->collect()->sum(function($item) {
            return $item->quantity * $item->product->price;
        });

        $shippingCost = 15000; // Fixed shipping cost as per plan
        $total = $subtotal + $shippingCost;

        try {
            DB::beginTransaction();

            $order = Order::create([
                'user_id' => $user->id,
                'total' => $total,
                'shipping_cost' => $shippingCost,
                'status' => 'pending',
                'shipping_address' => $shippingAddress,
                'city' => $city,
                'department' => $department,
                'phone' => $phone,
            ]);

            if ($request->hasFile('payment_proof')) {
                $path = Cloudinary::upload($request->file('payment_proof')->getRealPath())->getSecurePath();
                $order->payment_proof = $path;
                $order->save();

                $adminUsers = User::where('role', 'admin')->get();
                foreach ($adminUsers as $adminUser) {
                    AdminNotification::create([
                        'admin_user_id' => $adminUser->id,
                        'order_id' => $order->id,
                        'message' => 'Nuevo comprobante de pago recibido para el pedido #' . $order->id . '.',
                        'is_read' => false,
                    ]);
                }
            }

            foreach ($cart->items as $item) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item->product_id,
                    'quantity' => $item->quantity,
                    'price' => $item->product->price,
                ]);
            }

            // Clear cart
            $cart->items()->delete();

            DB::commit();

            return response()->json($order->load('items.product'), 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error creating order: ' . $e->getMessage()], 500);
        }
    }

    public function show($id)
    {
        $order = Auth::user()->orders()->with('items.product')->findOrFail($id);
        return response()->json($order);
    }

    public function updateStatus(Request $request, Order $order)
    {
        $validated = $request->validate([
            'status' => 'required|string|in:pending,confirmed,completed,rejected',
            'shipping_date' => 'nullable|date',
        ]);

        $order->update([
            'status' => $validated['status'],
            'shipping_date' => $validated['shipping_date'] ?? $order->shipping_date,
        ]);

        return response()->json($order->load('user', 'items.product'));
    }
}
