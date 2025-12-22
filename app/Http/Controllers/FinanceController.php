namespace App\Http\Controllers;

use App\Models\CustomerPayment;
use App\Models\SupplierPayment;
use App\Models\ExpenseTransaction;
use App\Models\Customer;
use App\Models\Supplier;
use App\Models\Treasury;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FinanceController extends Controller
{
    // 1. Receive Payment from Customer
    public function receiveCustomerPayment(Request $request)
    {
        $request->validate([
            'customer_id' => 'required',
            'amount' => 'required|numeric|min:0.1',
            'treasury_id' => 'required'
        ]);

        return DB::transaction(function () use ($request) {
            // A. Create Record
            $payment = CustomerPayment::create($request->all() + ['date' => now()]);

            // B. Decrease Customer Debt
            Customer::where('id', $request->customer_id)->decrement('balance', $request->amount);

            // C. Increase Treasury Money
            Treasury::where('id', $request->treasury_id)->increment('current_balance', $request->amount);

            return response()->json(['message' => 'Payment Received', 'data' => $payment]);
        });
    }

    // 2. Pay Supplier
    public function paySupplier(Request $request)
    {
        $request->validate([
            'supplier_id' => 'required',
            'amount' => 'required|numeric|min:0.1',
            'treasury_id' => 'required'
        ]);

        return DB::transaction(function () use ($request) {
            // A. Check if we have enough money
            $treasury = Treasury::lockForUpdate()->find($request->treasury_id);
            if ($treasury->current_balance < $request->amount) {
                return response()->json(['error' => 'Insufficient funds in treasury'], 400);
            }

            // B. Create Record
            $payment = SupplierPayment::create($request->all() + ['date' => now()]);

            // C. Decrease Supplier Debt
            Supplier::where('id', $request->supplier_id)->decrement('balance', $request->amount);

            // D. Decrease Treasury
            $treasury->decrement('current_balance', $request->amount);

            return response()->json(['message' => 'Supplier Paid', 'data' => $payment]);
        });
    }

    // 3. Record Expense (Rent, Salaries)
    public function storeExpense(Request $request)
    {
        $request->validate([
            'expense_category_id' => 'required',
            'amount' => 'required|numeric',
            'treasury_id' => 'required'
        ]);

        return DB::transaction(function () use ($request) {
             $treasury = Treasury::lockForUpdate()->find($request->treasury_id);

             if ($treasury->current_balance < $request->amount) {
                return response()->json(['error' => 'Insufficient funds'], 400);
             }

             $expense = ExpenseTransaction::create($request->all() + ['date' => now()]);
             $treasury->decrement('current_balance', $request->amount);

             return response()->json(['message' => 'Expense recorded', 'data' => $expense]);
        });
    }
}
