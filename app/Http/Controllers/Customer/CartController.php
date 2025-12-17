<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Resources\Customer\CartResource;
use App\Models\Cart;
use App\Models\Order;

use App\Models\CartItem;
use App\Models\PaymentMethod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CartController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $cart = Cart::where('user_id', Auth::id())->first();
        if ($cart) {
            return new CartResource($cart);
        }
        return response()->json([
            'message' => 'there are no cart for you'
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store($book_id)
    {
        $user = Auth::user();

        // if user not have a cart
        $cart = Cart::where('user_id', $user->id)->firstOrCreate([
            'user_id' => $user->id,
            'payment_method_id' => PaymentMethod::first()->id,
            'address' => $user->customer->address
        ]);


        $cartItem = CartItem::where('cart_id', $cart->id)->where('book_id', $book_id)->first();

        if ($cartItem) {
            $cartItem->update([
                'qty' => $cartItem->qty + 1
            ]);
        } else {
            // add item to the cart
            $cart->items()->create([
                'book_id' => $book_id,
                'qty' => 1
            ]);
        }



        return response()->json([
            'message' => 'item added'
        ]);
    }

    public function checkout(Request $request)
    {
        $user_id = Auth::user()->id;
        $cart = Cart::where('user_id', $user_id)->first();

        if (!$cart || $cart->items->count() == 0) {
            return response()->json([
                'message' => 'Cart is empty'
            ], 400);
        }

        $request->validate([
            'payment_method_id' => 'required|exists:payment_methods,id'
        ]);

        $total = $cart->totalCart();

        $order = Order::create([
            'user_id' => $user_id,
            'payment_method_id' => $request->payment_method_id,
            'address' => $cart->address,
            'total' => $total,
            'status' => 'pending',
        ]);

        foreach ($cart->items as $cartItem) {
            $order->items()->create([
                'book_id' => $cartItem->book_id,
                'qty' => $cartItem->qty,
                'price' => $cartItem->book->price,
            ]);
        }

        $cart->items()->delete();

        $cart->delete();

        return response()->json([
            'message' => 'Order placed successfully',
            'order_id' => $order->id,
            'total' => $total
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Decrease quantity of a cart item
     */
    public function decreaseQty($book_id)
    {
        $user = Auth::user();
        $cart = Cart::where('user_id', $user->id)->first();

        if (!$cart) {
            return response()->json([
                'message' => 'Cart not found'
            ], 404);
        }

        $cartItem = CartItem::where('cart_id', $cart->id)
            ->where('book_id', $book_id)
            ->first();

        if (!$cartItem) {
            return response()->json([
                'message' => 'Item not found in cart'
            ], 404);
        }

        if ($cartItem->qty <= 1) {
            $cartItem->delete();
            return response()->json([
                'message' => 'Item removed from cart'
            ]);
        }

        $cartItem->update([
            'qty' => $cartItem->qty - 1
        ]);

        return response()->json([
            'message' => 'Quantity decreased',
            'new_qty' => $cartItem->qty
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove item from cart completely
     */
    public function removeItem($book_id)
    {
        $user = Auth::user();
        $cart = Cart::where('user_id', $user->id)->first();

        if (!$cart) {
            return response()->json([
                'message' => 'Cart not found'
            ], 404);
        }

        $cartItem = CartItem::where('cart_id', $cart->id)
            ->where('book_id', $book_id)
            ->first();

        if (!$cartItem) {
            return response()->json([
                'message' => 'Item not found in cart'
            ], 404);
        }

        $cartItem->delete();

        return response()->json([
            'message' => 'Item removed from cart successfully'
        ]);
    }
}
