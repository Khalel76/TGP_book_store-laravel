<?php

namespace App\Http\Controllers;

use App\Models\PurchaseBill;
use App\Models\PurchaseItem;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PurchaseController extends Controller
{
    // List all purchase bills
    public function index(Request $request)
    {
        $query = PurchaseBill::with(['supplier', 'items.product'])->latest('date');

        // Filter by supplier
        if ($request->has('supplier_id')) {
            $query->where('supplier_id', $request->supplier_id);
        }

        // Filter by date range
        if ($request->has('from_date')) {
            $query->whereDate('date', '>=', $request->from_date);
        }
        if ($request->has('to_date')) {
            $query->whereDate('date', '<=', $request->to_date);
        }

        return response()->json($query->paginate(15));
    }

    public function store(Request $request)
    {
        $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.cost_price' => 'required|numeric|min:0',
            'invoice_number' => 'nullable|string|max:100'
        ]);

        try {
            DB::beginTransaction();

            $totalAmount = 0;

            // 1. Create Purchase Bill
            $bill = PurchaseBill::create([
                'date' => now(),
                'invoice_number' => $request->invoice_number,
                'supplier_id' => $request->supplier_id,
                'status' => 'confirmed'
            ]);

            // 2. Process Items
            foreach ($request->items as $item) {
                $product = Product::lockForUpdate()->find($item['product_id']);

                $oldQty = $product->quantity_in_stock;
                $oldCost = $product->cost_price;
                $newQty = $item['quantity'];
                $newCost = $item['cost_price'];

                // A. Calculate Weighted Average Cost
                if ($oldQty > 0) {
                    $weightedAvgCost = (($oldQty * $oldCost) + ($newQty * $newCost)) / ($oldQty + $newQty);
                } else {
                    $weightedAvgCost = $newCost;
                }

                // B. Update Stock and Cost
                $product->increment('quantity_in_stock', $newQty);
                $product->update(['cost_price' => $weightedAvgCost]);

                // Log Inventory Transaction
                \App\Models\InventoryTransaction::create([
                    'product_id' => $product->id,
                    'transaction_type' => 'purchase',
                    'quantity' => $newQty,
                    'reference_type' => PurchaseBill::class,
                    'reference_id' => $bill->id,
                    'notes' => 'Purchase Bill #' . $bill->id
                ]);

                $lineTotal = $newQty * $newCost;
                $totalAmount += $lineTotal;

                PurchaseItem::create([
                    'purchase_bill_id' => $bill->id,
                    'product_id' => $product->id,
                    'quantity' => $newQty,
                    'cost_price' => $newCost
                    // 'line_total' removed
                ]);
            }

            // Note: Supplier balance is calculated dynamically.
            // 3. Update Bill Total logic removed (total_amount dropped)

            DB::commit();
            return response()->json([
                'message' => 'Purchase recorded successfully',
                'data' => $bill->load(['supplier', 'items.product'])
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }
}
