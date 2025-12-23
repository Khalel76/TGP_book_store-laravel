<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\SalesInvoice;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    // List all customers
    public function index(Request $request)
    {
        $query = Customer::query();

        // Search by name
        if ($request->has('search')) {
            $query->where('name', 'like', "%{$request->search}%");
        }

        // Order by name
        $query->orderBy('name');

        return response()->json($query->paginate(15));
    }

    // Create new customer
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:500'
        ]);

        $customer = Customer::create($validated);

        return response()->json([
            'message' => 'Customer created successfully',
            'data' => $customer
        ], 201);
    }

    // Show customer details with invoices and balance
    public function show($id)
    {
        $customer = Customer::with([
            'salesInvoices' => function ($q) {
                $q->latest('date')->take(10);
            }
        ])->findOrFail($id);

        return response()->json([
            'customer' => $customer,
            'summary' => [
                'current_balance' => $customer->balance,
                'total_invoices' => $customer->salesInvoices()->count(),
                'total_sales' => \App\Models\InvoiceItem::whereHas('salesInvoice', function ($q) use ($id) {
                    $q->where('customer_id', $id);
                })->selectRaw('sum(quantity * unit_price) as total')->value('total') ?? 0
            ]
        ]);
    }

    // Update customer
    public function update(Request $request, $id)
    {
        $customer = Customer::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:500'
        ]);

        $customer->update($validated);

        return response()->json([
            'message' => 'Customer updated successfully',
            'data' => $customer
        ]);
    }

    // Delete customer
    public function destroy($id)
    {
        $customer = Customer::findOrFail($id);

        // Check if customer has outstanding balance
        if ($customer->balance > 0) {
            return response()->json([
                'error' => "Cannot delete customer with outstanding balance of {$customer->balance}. Please collect payment first."
            ], 400);
        }

        // Check if customer has invoices
        if ($customer->salesInvoices()->count() > 0) {
            return response()->json([
                'error' => 'Cannot delete customer with existing invoices. Archive instead.'
            ], 400);
        }

        $customer->delete();

        return response()->json([
            'message' => 'Customer deleted successfully'
        ]);
    }
}
