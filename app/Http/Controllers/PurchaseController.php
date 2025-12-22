namespace App\Http\Controllers;

use App\Models\PurchaseBill;
use App\Models\PurchaseItem;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PurchaseController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'items' => 'required|array',
            'invoice_number' => 'nullable|string'
        ]);

        try {
            DB::beginTransaction();

            $totalAmount = 0;

            // 1. Create Purchase Bill
            $bill = PurchaseBill::create([
                'date' => now(),
                'invoice_number' => $request->invoice_number,
                'supplier_id' => $request->supplier_id,
            ]);

            // 2. Process Items
            foreach ($request->items as $item) {
                $product = Product::lockForUpdate()->find($item['product_id']);

                // A. Add Stock
                $product->increment('quantity_in_stock', $item['quantity']);

                // B. Update Cost Price (Optional: Average Cost Logic could go here)
                $product->update(['cost_price' => $item['cost_price']]);

                $lineTotal = $item['quantity'] * $item['cost_price'];
                $totalAmount += $lineTotal;

                PurchaseItem::create([
                    'purchase_bill_id' => $bill->id,
                    'product_id' => $product->id,
                    'quantity' => $item['quantity'],
                    'cost_price' => $item['cost_price'],
                    'line_total' => $lineTotal
                ]);
            }

            // 3. Update Bill Total
            $bill->update(['total_amount' => $totalAmount]);

            // 4. Update Supplier Balance (We owe them money)
            Supplier::where('id', $request->supplier_id)->increment('balance', $totalAmount);

            DB::commit();
            return response()->json(['message' => 'Purchase recorded', 'data' => $bill], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }
}
