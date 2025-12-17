<?php

namespace App\Http\Controllers\Author;
use App\Models\Order;
use App\Models\OrderItem;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OrderController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // $user = Auth::user();
        // foreach($user->books as $books)
        // {
        //     $userOrders = $books->items()->Order();
        // }

        // return $userOrders;


    $user = Auth::user();
    $userBookIds = $user->books()->pluck('id');
    $orders = Order::whereHas('items', function ($orderItems) use ($userBookIds)
    {
        $orderItems->whereIn('book_id', $userBookIds);
    })
    ->with('items')->get();

    return $orders;
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
