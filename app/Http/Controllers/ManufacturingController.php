<?php

namespace App\Http\Controllers;

use App\Models\ProductionOrder;
use App\Models\ProductionOrderLine;
use App\Models\BillOfMaterial;
use App\Models\BOMDetail;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ManufacturingController extends Controller
{
    // List all BOMs (Recipes)
    public function indexBOM(Request $request)
    {
        $query = BillOfMaterial::with(['product', 'details.product'])->latest();

        return response()->json($query->paginate(15));
    }

    // Create a new BOM (Recipe)
    public function storeBOM(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'product_id' => 'required|exists:products,id',
            'details' => 'required|array|min:1',
            'details.*.product_id' => 'required|exists:products,id',
            'details.*.quantity' => 'required|integer|min:1'
        ]);

        try {
            DB::beginTransaction();

            // Ensure the finished product is marked as manufactured
            $finishedProduct = Product::find($request->product_id);
            if ($finishedProduct->type !== 'manufactured') {
                throw new \Exception("Product '{$finishedProduct->name}' must be of type 'manufactured' to create a BOM.");
            }

            // Create BOM Header
            $bom = BillOfMaterial::create([
                'name' => $request->name,
                'product_id' => $request->product_id
            ]);

            // Create BOM Details (Ingredients/Components)
            foreach ($request->details as $detail) {
                BOMDetail::create([
                    'bill_of_material_id' => $bom->id,
                    'product_id' => $detail['product_id'],
                    'quantity' => $detail['quantity']
                ]);
            }

            DB::commit();
            return response()->json([
                'message' => 'Bill of Materials created successfully',
                'data' => $bom->load(['product', 'details.product'])
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    // List production history
    public function indexOrders(Request $request)
    {
        $query = ProductionOrder::with(['product', 'lines.product'])->latest('date');

        // Filter by date range
        if ($request->has('from_date')) {
            $query->whereDate('date', '>=', $request->from_date);
        }
        if ($request->has('to_date')) {
            $query->whereDate('date', '<=', $request->to_date);
        }

        return response()->json($query->paginate(15));
    }

    // Execute Production
    public function executeOrder(Request $request)
    {
        $request->validate([
            'bill_of_material_id' => 'required|exists:bill_of_materials,id',
            'quantity_to_produce' => 'required|integer|min:1'
        ]);

        try {
            DB::beginTransaction();

            $bom = BillOfMaterial::with('details.product')->find($request->bill_of_material_id);

            // 1. Create Production Order Header
            $order = ProductionOrder::create([
                'date' => now(),
                'is_completed' => true,
                'product_id' => $bom->product_id, // The finished product
                'quantity_to_produce' => $request->quantity_to_produce
            ]);

            $totalCost = 0; // Track total cost for finished product

            // 2. Consume Raw Materials
            foreach ($bom->details as $component) {
                // Calculate total needed (Recipe Qty * Production Qty)
                $neededQty = $component->quantity * $request->quantity_to_produce;

                $rawMaterial = Product::lockForUpdate()->find($component->product_id);

                // Check Stock
                if ($rawMaterial->quantity_in_stock < $neededQty) {
                    throw new \Exception("Not enough stock for material: {$rawMaterial->name}. Available: {$rawMaterial->quantity_in_stock}, Needed: {$neededQty}");
                }

                // Deduct Stock
                $rawMaterial->decrement('quantity_in_stock', $neededQty);

                // Calculate cost contribution
                $componentCost = $rawMaterial->cost_price * $neededQty;
                $totalCost += $componentCost;

                // Record Line
                ProductionOrderLine::create([
                    'production_order_id' => $order->id,
                    'product_id' => $rawMaterial->id,
                    'quantity_used' => $neededQty
                ]);

                \App\Models\InventoryTransaction::create([
                    'product_id' => $rawMaterial->id,
                    'transaction_type' => 'Production',
                    'quantity' => -$neededQty, // Negative for sales
                    'reference_type' => ProductionOrder::class,
                    'reference_id' => $order->id,
                    'notes' => 'Production Material from Order #' . $order->id
                ]);
            }

            // 3. Calculate Cost Per Unit for Finished Product
            $costPerUnit = $totalCost / $request->quantity_to_produce;

            // 4. Add Finished Product to Stock with Calculated Cost
            $finishedProduct = Product::lockForUpdate()->find($bom->product_id);

            // Capture old state for WAC calculation
            $oldQty = $finishedProduct->quantity_in_stock;
            $oldCost = $finishedProduct->cost_price;

            // Increment Stock
            $finishedProduct->increment('quantity_in_stock', $request->quantity_to_produce);

            \App\Models\InventoryTransaction::create([
                'product_id' => $bom->product_id,
                'transaction_type' => 'Production',
                'quantity' => $request->quantity_to_produce,
                'reference_type' => ProductionOrder::class,
                'reference_id' => $order->id,
                'notes' => 'Finished Product from Order #' . $order->id
            ]);

            // Calculate Weighted Average Cost
            $newQty = $request->quantity_to_produce;
            $newCost = $costPerUnit;

            if (($oldQty + $newQty) > 0) {
                $weightedAvgCost = (($oldQty * $oldCost) + ($newQty * $newCost)) / ($oldQty + $newQty);
            } else {
                $weightedAvgCost = $newCost;
            }

            $finishedProduct->update(['cost_price' => $weightedAvgCost]);

            DB::commit();
            return response()->json([
                'message' => 'Production completed successfully',
                'data' => [
                    'order' => $order->load(['product', 'lines.product']),
                    'cost_summary' => [
                        'total_raw_material_cost' => round($totalCost, 2),
                        'cost_per_unit' => round($costPerUnit, 2),
                        'units_produced' => $request->quantity_to_produce,
                        'new_weighted_avg_cost' => round($weightedAvgCost, 2)
                    ]
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }
}
