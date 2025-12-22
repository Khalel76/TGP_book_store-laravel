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
    public function store(Request $request)
    {
        // 1. Validation
        $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'items' => 'required|array',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:1',
            'paid_amount' => 'numeric|min:0'
        ]);

        try {
            DB::beginTransaction();

            $totalAmount = 0;

            // 2. Create Invoice Header
            $invoice = SalesInvoice::create([
                'date' => now(),
                'customer_id' => $request->customer_id,
                'paid_amount' => $request->paid_amount,
                'total_amount' => 0, // Will calculate below
                'remaining_amount' => 0
            ]);

            // 3. Process Items
            foreach ($request->items as $item) {
                $product = Product::lockForUpdate()->find($item['product_id']);

                // A. Check Stock
                if ($product->quantity_in_stock < $item['quantity']) {
                    throw new \Exception("Insufficient stock for product: " . $product->name);
                }

                // B. Deduct Stock
                $product->decrement('quantity_in_stock', $item['quantity']);

                // C. Calculate Line Total
                $unitPrice = $product->selling_price; // Or from request if dynamic
                $lineTotal = $unitPrice * $item['quantity'];
                $totalAmount += $lineTotal;

                // D. Save Item
                InvoiceItem::create([
                    'sales_invoice_id' => $invoice->id,
                    'product_id' => $product->id,
                    'quantity' => $item['quantity'],
                    'unit_price' => $unitPrice,
                    'line_total' => $lineTotal
                ]);
            }

            // 4. Finalize Invoice Calculations
            $remaining = $totalAmount - $request->paid_amount;
            $invoice->update([
                'total_amount' => $totalAmount,
                'remaining_amount' => $remaining
            ]);

            // 5. Update Customer Balance (Add Debt)
            if ($remaining > 0) {
                Customer::where('id', $request->customer_id)->increment('balance', $remaining);
            }

            // 6. Update Treasury (If Cash Paid)
            if ($request->paid_amount > 0) {
                // Assuming Treasury ID 1 is Main Safe, or pass it in request
                Treasury::where('id', 1)->increment('current_balance', $request->paid_amount);
            }

            DB::commit();
            return response()->json(['message' => 'Invoice created successfully', 'data' => $invoice], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }
}
