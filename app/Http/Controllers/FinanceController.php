<?php

namespace App\Http\Controllers;

use App\Models\CustomerPayment;
use App\Models\SupplierPayment;
use App\Models\ExpenseTransaction;
use App\Models\Customer;
use App\Models\Supplier;
use App\Models\Treasury;
use App\Models\ExpenseCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FinanceController extends Controller
{
    // List all treasuries with balances
    public function index()
    {
        $treasuries = Treasury::all();

        return response()->json([
            'treasuries' => $treasuries,
            'total_cash' => $treasuries->sum('current_balance')
        ]);
    }

    // Transaction history (unified view)
    public function history(Request $request)
    {
        $transactions = [];

        // Get customer payments
        $customerPayments = CustomerPayment::with('customer')
            ->when($request->from_date, fn($q) => $q->whereDate('date', '>=', $request->from_date))
            ->when($request->to_date, fn($q) => $q->whereDate('date', '<=', $request->to_date))
            ->get()
            ->map(function ($payment) {
                return [
                    'id' => $payment->id,
                    'type' => 'customer_payment',
                    'date' => $payment->date,
                    'amount' => $payment->amount,
                    'party' => $payment->customer->name ?? 'Unknown',
                    'description' => 'Payment received from customer',
                    'treasury_id' => $payment->treasury_id
                ];
            });

        // Get supplier payments
        $supplierPayments = SupplierPayment::with('supplier')
            ->when($request->from_date, fn($q) => $q->whereDate('date', '>=', $request->from_date))
            ->when($request->to_date, fn($q) => $q->whereDate('date', '<=', $request->to_date))
            ->get()
            ->map(function ($payment) {
                return [
                    'id' => $payment->id,
                    'type' => 'supplier_payment',
                    'date' => $payment->date,
                    'amount' => -$payment->amount, // Negative because it's outgoing
                    'party' => $payment->supplier->name ?? 'Unknown',
                    'description' => 'Payment to supplier',
                    'treasury_id' => $payment->treasury_id
                ];
            });

        // Get expenses
        $expenses = ExpenseTransaction::with('category')
            ->when($request->from_date, fn($q) => $q->whereDate('date', '>=', $request->from_date))
            ->when($request->to_date, fn($q) => $q->whereDate('date', '<=', $request->to_date))
            ->get()
            ->map(function ($expense) {
                return [
                    'id' => $expense->id,
                    'type' => 'expense',
                    'date' => $expense->date,
                    'amount' => -$expense->amount, // Negative because it's outgoing
                    'party' => $expense->category->name ?? 'Expense',
                    'description' => $expense->description ?? 'Business expense',
                    'treasury_id' => $expense->treasury_id
                ];
            });

        // Merge and sort by date
        $transactions = $customerPayments
            ->concat($supplierPayments)
            ->concat($expenses)
            ->sortByDesc('date')
            ->values();

        return response()->json([
            'transactions' => $transactions,
            'summary' => [
                'total_inflow' => $customerPayments->sum('amount'),
                'total_outflow' => abs($supplierPayments->sum('amount')) + abs($expenses->sum('amount')),
                'net_cash_flow' => $customerPayments->sum('amount') + $supplierPayments->sum('amount') + $expenses->sum('amount')
            ]
        ]);
    }

    // 1. Receive Payment from Customer
    public function receiveCustomerPayment(Request $request)
    {
        $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'amount' => 'required|numeric|min:0.01',
            'treasury_id' => 'required|exists:treasuries,id'
        ]);

        return DB::transaction(function () use ($request) {
            // A. Create Record
            $payment = CustomerPayment::create($request->all() + ['date' => now()]);

            return response()->json(['message' => 'Payment received successfully', 'data' => $payment]);
        });
    }

    // 2. Pay Supplier
    public function paySupplier(Request $request)
    {
        $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'amount' => 'required|numeric|min:0.01',
            'treasury_id' => 'required|exists:treasuries,id'
        ]);

        return DB::transaction(function () use ($request) {
            // A. Check if we have enough money
            $treasury = Treasury::find($request->treasury_id);
            if ($treasury->current_balance < $request->amount) {
                return response()->json(['error' => 'Insufficient funds in treasury'], 400);
            }

            // B. Create Record
            $payment = SupplierPayment::create($request->all() + ['date' => now()]);

            return response()->json(['message' => 'Supplier paid successfully', 'data' => $payment]);
        });
    }

    // 3. Record Expense (Rent, Salaries)
    public function storeExpense(Request $request)
    {
        $request->validate([
            'expense_category_id' => 'required',
            'amount' => 'required|numeric|min:0.01',
            'treasury_id' => 'required|exists:treasuries,id',
            'description' => 'nullable|string|max:500'
        ]);

        return DB::transaction(function () use ($request) {
            $treasury = Treasury::find($request->treasury_id);

            if ($treasury->current_balance < $request->amount) {
                return response()->json(['error' => 'Insufficient funds in treasury'], 400);
            }

            $expense = ExpenseTransaction::create($request->all() + ['date' => now()]);

            return response()->json(['message' => 'Expense recorded successfully', 'data' => $expense]);
        });
    }

    // 4. Create New Treasury
    public function storeTreasury(Request $request)
    {
        $request->validate([
            'name' => 'required|string|unique:treasuries,name|max:255',
        ]);

        $treasury = Treasury::create($request->all());

        return response()->json([
            'message' => 'Treasury created successfully',
            'data' => $treasury
        ], 201);
    }

    // 5. Create New Expense Category
    public function storeExpenseCategory(Request $request)
    {
        $request->validate([
            'name' => 'required|string|unique:expense_categories,name|max:255',
            'description' => 'nullable|string|max:500',
        ]);

        $category = ExpenseCategory::create($request->all());

        return response()->json([
            'message' => 'Expense Category created successfully',
            'data' => $category
        ], 201);
    }
}
