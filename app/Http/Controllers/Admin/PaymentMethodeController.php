<?php

namespace App\Http\Controllers\Admin;
use App\Models\PaymentMethod;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class PaymentMethodeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $payments = PaymentMethod::all();
        return $payments;
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $inputs = $request->validate([
            'name'=>['required']
        ]);

        $payments = PaymentMethod::create($inputs);

        return response()->json([
            'message'=>'payment created',
            'payment'=> $payments
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $payment = PaymentMethod::findOrFail($id);
        return $payment;
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
         $inputs = $request->validate([
            'name'=>['required']
        ]);

        $payment = PaymentMethod::findOrFail($id);
        $payment->update($inputs);

        return $payment;
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $payment = PaymentMethod::findOrFail($id);
        $payment->delete();
        return response()->json([
            'message'=>'payment deleted'
        ]);
    }
}
