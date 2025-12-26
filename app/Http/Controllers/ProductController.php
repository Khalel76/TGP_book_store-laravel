<?php

namespace App\Http\Controllers;
use App\Models\InventoryTransaction;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::query()->with('category');

        // Filter by type
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        // Filter by category
        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        // Search by name or code
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        // Pagination
        return response()->json($query->paginate(15));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:raw_material,porcelain,manufactured',
            'category_id' => 'required|exists:categories,id',
            'code' => 'nullable|string|max:100|unique:products,code',
            'selling_price' => 'nullable|numeric|min:0',
            'cost_price' => 'nullable|numeric|min:0',
            'quantity_in_stock' => 'nullable|numeric|min:0',
            'is_sellable' => 'nullable|boolean',
            'is_purchasable' => 'nullable|boolean',
            'is_manufactured' => 'nullable|boolean',
            'origin_country' => 'nullable|string|max:100',
            'pattern' => 'nullable|string|max:100',
            'thickness' => 'nullable|numeric',
            'unit' => 'nullable|string|max:50',
        ]);

        // Auto-set flags based on type
        if ($request->type === 'raw_material') {
            $validated['is_sellable'] = false;
            $validated['is_purchasable'] = true;
            $validated['is_manufactured'] = false;
        } elseif ($request->type === 'porcelain') {
            $validated['is_sellable'] = true;
            $validated['is_purchasable'] = true;
            $validated['is_manufactured'] = false;
        } elseif ($request->type === 'manufactured') {
            $validated['is_sellable'] = true;
            $validated['is_purchasable'] = false;
            $validated['is_manufactured'] = true;
        }

        $product = Product::create($validated);

        if ($product->quantity_in_stock > 0) {
            InventoryTransaction::create([
                'product_id' => $product->id,
                'transaction_type' => 'initial_stock',
                'quantity' => $product->quantity_in_stock,
                'reference_type' => null, // Manual/Initial
                'reference_id' => null,
                'notes' => 'Initial stock on product creation'
            ]);
        }

        return response()->json([
            'message' => 'Product created successfully',
            'data' => $product->load('category')
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $product = Product::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'type' => 'sometimes|in:raw_material,porcelain,manufactured',
            'category_id' => 'sometimes|exists:categories,id',
            'code' => 'sometimes|string|max:100|unique:products,code,' . $id,
            'selling_price' => 'sometimes|numeric|min:0',
            'cost_price' => 'sometimes|numeric|min:0',
            'quantity_in_stock' => 'sometimes|numeric|min:0',
            'is_sellable' => 'sometimes|boolean',
            'is_purchasable' => 'sometimes|boolean',
            'is_manufactured' => 'sometimes|boolean',
            'origin_country' => 'nullable|string|max:100',
            'pattern' => 'nullable|string|max:100',
            'thickness' => 'nullable|numeric',
            'unit' => 'nullable|string|max:50',
        ]);

        $oldStock = $product->quantity_in_stock;

        $product->update($validated);

        // Check for manual stock adjustment
        if (isset($validated['quantity_in_stock']) && $validated['quantity_in_stock'] != $oldStock) {
            $diff = $validated['quantity_in_stock'] - $oldStock;
            \App\Models\InventoryTransaction::create([
                'product_id' => $product->id,
                'transaction_type' => 'manual_adjustment',
                'quantity' => $diff,
                'reference_type' => null,
                'reference_id' => null,
                'notes' => 'Manual adjustment via product update'
            ]);
        }

        return response()->json([
            'message' => 'Product updated successfully',
            'data' => $product->load('category')
        ]);
    }

    public function destroy($id)
    {
        $product = Product::findOrFail($id);

        // Check if product has stock
        if ($product->quantity_in_stock > 0) {
            return response()->json([
                'error' => 'Cannot delete product with existing stock. Current stock: ' . $product->quantity_in_stock
            ], 400);
        }

        // Check if used in any BOM
        if ($product->bomDetails()->count() > 0) {
            return response()->json([
                'error' => 'Cannot delete product that is used in manufacturing recipes (Bill of Materials)'
            ], 400);
        }

        $product->delete();

        return response()->json([
            'message' => 'Product deleted successfully'
        ]);
    }

    // API to check stock for a specific product
    public function checkStock($id)
    {
        $product = Product::findOrFail($id);
        return response()->json([
            'id' => $product->id,
            'name' => $product->name,
            'type' => $product->type,
            'current_stock' => $product->quantity_in_stock,
            'cost_price' => $product->cost_price,
            'selling_price' => $product->selling_price
        ]);
    }
}
