<?php

namespace App\Http\Controllers;

use App\Models\SalesInvoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\Customer;
use App\Models\Treasury;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SalesController extends Controller
{
    // List all invoices with pagination
    public function index(Request $request)
    {
        $query = SalesInvoice::with(['customer', 'items.product'])->latest('date');

        // Filter by customer
        if ($request->has('customer_id')) {
            $query->where('customer_id', $request->customer_id);
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

    // Show single invoice with all details
    public function show($id)
    {
        $invoice = SalesInvoice::with(['customer', 'items.product.category'])
            ->findOrFail($id);

        return response()->json([
            'invoice' => $invoice,
            'summary' => [
                'total_items' => $invoice->items->count(),
                'total_amount' => $invoice->total_amount,
                'paid_amount' => $invoice->paid_amount,
                'remaining_amount' => $invoice->remaining_amount
            ]
        ]);
    }

    public function store(Request $request)
    {
        // 1. Validation
        $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'paid_amount' => 'nullable|numeric|min:0',
            'treasury_id' => 'nullable|exists:treasuries,id'
        ]);

        try {
            DB::beginTransaction();

            $totalAmount = 0;

            // Validate all products before processing
            foreach ($request->items as $item) {
                $product = Product::find($item['product_id']);

                // BLOCK RAW MATERIALS FROM BEING SOLD
                if ($product->type === 'raw_material') {
                    throw new \Exception("Cannot sell raw material: {$product->name}. Only porcelain and manufactured products can be sold.");
                }

                // Ensure product is marked as sellable
                if (!$product->is_sellable) {
                    throw new \Exception("Product '{$product->name}' is not marked as sellable.");
                }
            }

            // 2. Create Invoice Header
            $invoice = SalesInvoice::create([
                'date' => now(),
                'customer_id' => $request->customer_id,
                'status' => 'confirmed' // Default to confirmed for now
            ]);

            // 3. Process Items
            foreach ($request->items as $item) {
                $product = Product::with('category')->lockForUpdate()->find($item['product_id']);

                // Determine Quantity in Boxes and Pieces
                $requestedQuantity = $item['quantity'];
                $boxes = 0;
                $totalPieces = 0;

                // Check if input is in meters (string ending with 'm' or 'M')
                if (is_string($requestedQuantity) && preg_match('/^(\d+(\.\d+)?)m$/i', $requestedQuantity, $matches)) {
                    $meters = (float) $matches[1];

                    if (!$product->category || !$product->category->metres_in_box) {
                        throw new \Exception("Product '{$product->name}' category does not have 'meters in box' defined.");
                    }

                    // Calculate boxes
                    $calculatedBoxes = $meters / $product->category->metres_in_box;

                    // Validate integer boxes
                    if (abs($calculatedBoxes - round($calculatedBoxes)) > 0.0001) {
                        throw new \Exception("For product '{$product->name}', {$meters}m results in {$calculatedBoxes} boxes. Box count must be an integer.");
                    }
                    $boxes = (int) round($calculatedBoxes);

                } else {
                    // Assume input is boxes
                    if (!is_numeric($requestedQuantity)) {
                        throw new \Exception("Invalid quantity format for product '{$product->name}'.");
                    }

                    $boxes = (float) $requestedQuantity;
                    // Validate integer boxes
                    if (abs($boxes - round($boxes)) > 0.0001) {
                        throw new \Exception("For product '{$product->name}', box count must be an integer.");
                    }
                    $boxes = (int) $boxes;
                }

                // Calculate Total Pieces
                if (!$product->category || !$product->category->count_in_box) {
                    // Fallback if no category info (though user implied all porcelain has this)
                    // If it's a simple product without these params, maybe treat input as pieces? 
                    // OR throw error strictly? Given user request "each category had same piece of porsalin", let's be strict or assume 1 if not defined?
                    // Let's throw error to be safe as this logic seems specific to these products.
                    if ($boxes > 0) {
                        // If we have boxes but no count_in_box, we can't calculate pieces.
                        // But if it's a legacy product, maybe just use quantity as pieces?
                        // The prompt implies this logic is specifically for the new feature.
                        // Let's assume if count_in_box is missing, we treat original quantity as pieces for now, BUT user said "added them... idea... each category had same piece".
                        // Safest is to error if category data missing for this calculation.
                        // However, to avoid breaking other product sales (non-porcelain?), let's check product type or category fields.

                        // If category has no count_in_box, we fall back to treating input as direct quantity units (pieces/items), 
                        // but we need to remove our "boxes" assumption if it wasn't 'm'.
                        // Re-evaluating: The requirement is specific to the new columns.

                        if ($product->category && $product->category->count_in_box > 0) {
                            $totalPieces = $boxes * $product->category->count_in_box;
                        } else {
                            // Fallback for products NOT using this system (e.g. non-tile products)
                            // If input was 'm', we definitely fail because we needed metres_in_box which likely implies count_in_box exists too.
                            // If input was just number, treat as pieces directly.
                            if (isset($meters)) {
                                throw new \Exception("Product '{$product->name}' category missing 'count_in_box'.");
                            }
                            $totalPieces = $item['quantity']; // Treat input as simple quantity
                        }
                    }
                } else {
                    $totalPieces = $boxes * $product->category->count_in_box;
                }

                // A. Check Stock (Quantity in Stock is assumed to be in Pieces based on "count that decreased from proudact... will be the sum of box multible count_in_box")
                if ($product->quantity_in_stock < $totalPieces) {
                    throw new \Exception("Insufficient stock for product: {$product->name}. Available Pieces: {$product->quantity_in_stock}, Requested Pieces: {$totalPieces} ({$boxes} boxes)");
                }

                // B. Deduct Stock
                $product->decrement('quantity_in_stock', $totalPieces);

                // Create Inventory Transaction Log
                \App\Models\InventoryTransaction::create([
                    'product_id' => $product->id,
                    'transaction_type' => 'sale',
                    'quantity' => -$totalPieces, // Negative for sales
                    'reference_type' => SalesInvoice::class,
                    'reference_id' => $invoice->id,
                    'notes' => 'Invoice #' . $invoice->id
                ]);

                // C. Calculate Line Total
                // Assuming selling_price is PER PIECE as stock is per piece. 
                // "count that decreased ... will be ... box multiple count_in_box".
                $unitPrice = $product->selling_price;
                $lineTotal = $unitPrice * $totalPieces;
                $totalAmount += $lineTotal;

                // D. Save Item
                InvoiceItem::create([
                    'sales_invoice_id' => $invoice->id,
                    'product_id' => $product->id,
                    'quantity' => $totalPieces, // Store pieces count? Or boxes? "count must be 4" (from user ex 10m -> 4).
                    // User said: "when user want to sell product from that category he can write 10m then the count must be 4"
                    // BUT LATER said: "count that decreased from proudact when user selled will be the sum of box multible count_in_box"
                    // Usually InvoiceItem should reflect what was actually sold/deducted. 
                    // If stock is pieces, InvoiceItem quantity usually matches stock deduction for consistency.
                    // BUT user might want to see "4 boxes" on the invoice?
                    // User's first prompt: "the count must be 4". This likely referred to the UI/Calculation interim step.
                    // User's second prompt: "count ... decreased ... will be sum of box multible count_in_box".
                    // Standard ERP: Invoice Line Qty = Stock Deduction Qty (Pieces).
                    // I will store pieces (`totalPieces`) to align with stock deduction.
                    'unit_price' => $unitPrice
                ]);
            }

            // 4. Handle Payment (If Cash Paid)
            $paidAmount = $request->paid_amount ?? 0;
            if ($paidAmount > 0) {
                $treasuryId = $request->treasury_id ?? 1; // Default to treasury ID 1

                // Create Customer Payment
                \App\Models\CustomerPayment::create([
                    'customer_id' => $request->customer_id,
                    'treasury_id' => $treasuryId,
                    'amount' => $paidAmount,
                    'date' => now()
                ]);
            }

            // Note: Customer balance and Treasury balance are now calculated dynamically, so no need to increment columns.

            DB::commit();
            return response()->json([
                'message' => 'Invoice created successfully',
                'data' => $invoice->load(['customer', 'items.product'])
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }
}
