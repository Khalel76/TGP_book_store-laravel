<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Category;
use App\Models\Product;
use App\Models\SalesInvoice;
use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use App\Http\Controllers\SalesController;

class SalesConversionTest extends TestCase
{
    // Use this if you want to reset DB, else be careful
    // use RefreshDatabase; 

    public function test_sales_conversion_logic()
    {
        // 1. Setup Data
        $category = Category::create([
            'name' => 'Porcelain Test Cat ' . uniqid(),
            'count_in_box' => 6,
            'metres_in_box' => 2.5
        ]);

        $product = Product::create([
            'name' => 'Porcelain Tile ' . uniqid(),
            'code' => 'TILE-' . uniqid(),
            'type' => 'finished',
            'category_id' => $category->id,
            'quantity_in_stock' => 1000, // Pieces
            'cost_price' => 10,
            'selling_price' => 20, // Per Piece
            'is_sellable' => true
        ]);

        $customer = Customer::first();
        if (!$customer) {
            $customer = Customer::create(['name' => 'Test Customer', 'email' => 'test@test.com']);
        }

        $controller = new SalesController();

        echo "\n--- Test Case 1: Sell 10m --- \n";
        // 10m / 2.5m/box = 4 boxes.
        // 4 boxes * 6 pieces/box = 24 pieces.
        $request1 = new Request([
            'customer_id' => $customer->id,
            'items' => [
                ['product_id' => $product->id, 'quantity' => '10m']
            ]
        ]);

        try {
            $response = $controller->store($request1);
            $content = $response->getData(true);

            if ($response->getStatusCode() === 201) {
                echo "Success: Created Invoice " . $content['data']['id'] . "\n";

                $product->refresh();
                echo "New Stock: " . $product->quantity_in_stock . " (Expected 976)\n";

                // Verify Invoice Item
                $invoice = SalesInvoice::with('items')->find($content['data']['id']);
                $item = $invoice->items->first();
                echo "Invoice Item Quantity: " . $item->quantity . " (Expected 24)\n";

                if ($product->quantity_in_stock == 976 && $item->quantity == 24) {
                    echo "PASSED\n";
                } else {
                    echo "FAILED - Calculation Mismatch\n";
                }

            } else {
                echo "Failed: " . json_encode($content) . "\n";
            }

        } catch (\Exception $e) {
            echo "Exception: " . $e->getMessage() . "\n";
        }

        echo "\n--- Test Case 2: Sell Invalid Meters (6m) --- \n";
        // 6m / 2.5 = 2.4 boxes -> Should Fail
        $request2 = new Request([
            'customer_id' => $customer->id,
            'items' => [
                ['product_id' => $product->id, 'quantity' => '6m']
            ]
        ]);

        try {
            $response = $controller->store($request2);
            $content = $response->getData(true);
            echo "Status: " . $response->getStatusCode() . "\n";
            echo "Response: " . json_encode($content) . "\n";
            if ($response->getStatusCode() == 400 && strpos($content['error'], 'must be an integer') !== false) {
                echo "PASSED (Correctly rejected)\n";
            } else {
                echo "FAILED (Should have rejected)\n";
            }
        } catch (\Exception $e) {
            echo "Exception: " . $e->getMessage() . "\n";
        }

    }
}
