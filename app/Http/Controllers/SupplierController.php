<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use App\Models\PurchaseBill;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    // List all suppliers
    public function index(Request $request)
    {
        $query = Supplier::query();

        // Search by name or company
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('company_name', 'like', "%{$search}%");
            });
        }

        // Order by name
        $query->orderBy('name');

        return response()->json($query->paginate(15));
    }

    // Create new supplier
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'company_name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50'
        ]);

        $supplier = Supplier::create($validated);

        return response()->json([
            'message' => 'Supplier created successfully',
            'data' => $supplier
        ], 201);
    }

    // Show supplier details with bills and balance
    public function show($id)
    {
        $supplier = Supplier::with([
            'purchaseBills' => function ($q) {
                $q->latest('date')->take(10);
            }
        ])->findOrFail($id);

        return response()->json([
            'supplier' => $supplier,
            'summary' => [
                'current_balance' => $supplier->balance,
                'total_bills' => $supplier->purchaseBills()->count(),
                'total_purchases' => \App\Models\PurchaseItem::whereHas('purchaseBill', function ($q) use ($id) {
                    $q->where('supplier_id', $id);
                })->selectRaw('sum(quantity * cost_price) as total')->value('total') ?? 0
            ]
        ]);
    }

    // Update supplier
    public function update(Request $request, $id)
    {
        $supplier = Supplier::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'company_name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50'
        ]);

        $supplier->update($validated);

        return response()->json([
            'message' => 'Supplier updated successfully',
            'data' => $supplier
        ]);
    }

    // Delete supplier
    public function destroy($id)
    {
        $supplier = Supplier::findOrFail($id);

        // Check if supplier has outstanding balance
        if ($supplier->balance > 0) {
            return response()->json([
                'error' => "Cannot delete supplier with outstanding balance of {$supplier->balance}. Please settle payment first."
            ], 400);
        }

        // Check if supplier has purchase history
        if ($supplier->purchaseBills()->count() > 0) {
            return response()->json([
                'error' => 'Cannot delete supplier with existing purchase history. Archive instead.'
            ], 400);
        }

        $supplier->delete();

        return response()->json([
            'message' => 'Supplier deleted successfully'
        ]);
    }
}
