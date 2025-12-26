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


                    $boxes = (float) $requestedQuantity;
                    // Validate integer boxes
                    if (abs($boxes - round($boxes)) > 0.0001) {
                        throw new \Exception("For product '{$product->name}', box count must be an integer.");
                    }
                    $boxes = (int) $boxes;
                }

                // Calculate Total Pieces
                if (!$product->category || !$product->category->count_in_box) {
                    if ($boxes > 0) {

                        if ($product->category && $product->category->count_in_box > 0) {
                            $totalPieces = $boxes * $product->category->count_in_box;
                        } else {

                            if (isset($meters)) {
                                throw new \Exception("Product '{$product->name}' category missing 'count_in_box'.");
                            }
                            $totalPieces = $item['quantity'];
                        }
                    }
                } else {
                    $totalPieces = $boxes * $product->category->count_in_box;
                }


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

                $unitPrice = $item['unit_price'] ;


                // D. Save Item
                InvoiceItem::create([
                    'sales_invoice_id' => $invoice->id,
                    'product_id' => $product->id,
                    'quantity' => $totalPieces,
                    'unit_price' => $unitPrice
                ]);
            }

            // 4. Handle Payment
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
