namespace App\Http\Controllers;

use App\Models\ProductionOrder;
use App\Models\ProductionOrderLine;
use App\Models\BillOfMaterial;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ManufacturingController extends Controller
{
    // Create a new BOM (Recipe)
    public function storeBOM(Request $request) { /* CRUD Logic */ }

    // Execute Production
    public function executeOrder(Request $request)
    {
        $request->validate([
            'bill_of_material_id' => 'required|exists:bill_of_materials,id',
            'quantity_to_produce' => 'required|numeric|min:1'
        ]);

        try {
            DB::beginTransaction();

            $bom = BillOfMaterial::with('details')->find($request->bill_of_material_id);

            // 1. Create Production Order Header
            $order = ProductionOrder::create([
                'date' => now(),
                'is_completed' => true, // Assuming instant completion for simplicity
                'product_id' => $bom->product_id, // The finished product
                'quantity_to_produce' => $request->quantity_to_produce
            ]);

            // 2. Consume Raw Materials
            foreach ($bom->details as $component) {
                // Calculate total needed (Recipe Qty * Production Qty)
                $neededQty = $component->quantity * $request->quantity_to_produce;

                $rawMaterial = Product::lockForUpdate()->find($component->product_id);

                // Check Stock
                if ($rawMaterial->quantity_in_stock < $neededQty) {
                    throw new \Exception("Not enough material: " . $rawMaterial->name);
                }

                // Deduct Stock
                $rawMaterial->decrement('quantity_in_stock', $neededQty);

                // Record Line
                ProductionOrderLine::create([
                    'production_order_id' => $order->id,
                    'product_id' => $rawMaterial->id,
                    'quantity_used' => $neededQty
                ]);
            }

            // 3. Add Finished Product to Stock
            Product::where('id', $bom->product_id)->increment('quantity_in_stock', $request->quantity_to_produce);

            DB::commit();
            return response()->json(['message' => 'Production completed', 'data' => $order], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }
}
