namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    // List products (Can filter by type: raw_material, porcelain, manufactured)
    public function index(Request $request)
    {
        $query = Product::query();

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        return response()->json($query->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'type' => 'required|in:raw_material,porcelain,manufactured',
            'selling_price' => 'numeric|min:0',
            'cost_price' => 'numeric|min:0',
            // Add other validations...
        ]);

        $product = Product::create($validated);

        return response()->json(['message' => 'Product created', 'data' => $product], 201);
    }

    // API to check stock for a specific product
    public function checkStock($id)
    {
        $product = Product::findOrFail($id);
        return response()->json([
            'id' => $product->id,
            'name' => $product->name,
            'current_stock' => $product->quantity_in_stock
        ]);
    }
}
