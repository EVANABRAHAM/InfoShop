<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Transaction;
use App\Models\PurchaseTransaction;
use App\Models\Purchase;
use App\Models\Contact;
use App\Models\Sale;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TransactionController extends Controller
{
    public function storeCustomerTransaction(Request $request){
        $amount = $request->input('amount');
        $paymentMethod = $request->input('payment_method');
        $transactionDate = $request->input('transaction_date');
        $note = $request->input('note');
        $contactId = $request->input('contact_id');
        $saleID = $request->input('transaction_id');
        $storeId = $request->input('store_id');

        $transactionData = [
            'sales_id' => $saleID,
            'store_id' => $storeId,
            'contact_id' => $contactId,
            'transaction_date' => $transactionDate,
            'amount' => $amount,
            'payment_method' => $paymentMethod,
            'note'=>$note,
        ];

        DB::beginTransaction();
        try{
            $transactionData['transaction_type'] = 'account';
            if($paymentMethod=='Account' && $request->has('transaction_id')) {
                
            }
            elseif($paymentMethod !='Account' && $request->has('transaction_id')){
                $transactionData['transaction_type'] = 'sale';
                Contact::where('id', $contactId)->increment('balance', $amount);
            }
            else{
                Contact::where('id', $contactId)->increment('balance', $amount);
            }
            
            Transaction::create($transactionData);

            if($request->has('transaction_id')){
                $sale = Sale::where('id', $saleID)->first();
                if ($sale) {
                    // Increment the amount_received field by the given amount
                    $sale->increment('amount_received', $amount);
                
                    // Check if the total amount received is greater than or equal to the total amount
                    if ($sale->amount_received >= $sale->total_amount) $sale->status = 'completed';
                
                    // Save the changes to the Sale record
                    $sale->save();
                }
            }


            DB::commit();
            return response()->json([
                'message'=>"Payment added successfully",
            ], 200);

        } catch (\Exception $e) {
            // Rollback transaction in case of error
            DB::rollBack();

            Log::error('Transaction failed', [
                'error_message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Return error response
            return response()->json(['error' => $e], 500);
        }
    }

    public function storeVendorTransaction(Request $request){
        $amount = $request->input('amount');
        $paymentMethod = $request->input('payment_method');
        $transactionDate = $request->input('transaction_date');
        $note = $request->input('note');
        $contactId = $request->input('contact_id');
        $purchaseID = $request->input('transaction_id');
        $storeId = $request->input('store_id');

        $transactionData = [
            'purchase_id' => $purchaseID,
            'store_id' => $storeId,
            'contact_id' => $contactId,
            'transaction_date' => $transactionDate,
            'amount' => $amount,
            'payment_method' => $paymentMethod,
            'note'=>$note,
        ];

        DB::beginTransaction();
        try{
            $transactionData['transaction_type'] = 'account';
            if($paymentMethod=='Account' && $request->has('transaction_id')) {
                
            }
            elseif($paymentMethod !='Account' && $request->has('transaction_id')){
                $transactionData['transaction_type'] = 'purchase';
                Contact::where('id', $contactId)->increment('balance', $amount);
            }
            else{
                Contact::where('id', $contactId)->increment('balance', $amount);
            }
            
            PurchaseTransaction::create($transactionData);

            if($request->has('transaction_id')){
                $purchase = Purchase::where('id', $purchaseID)->first();
                if ($purchase) {
                    // Increment the amount_received field by the given amount
                    $purchase->increment('amount_paid', $amount);
                
                    // Check if the total amount received is greater than or equal to the total amount
                    if ($purchase->amount_paid >= $purchase->total_amount) $purchase->status = 'completed';
                
                    // Save the changes to the Sale record
                    $purchase->save();
                }
            }


            DB::commit();
            return response()->json([
                'message'=>"Payment added successfully",
            ], 200);

        } catch (\Exception $e) {
            // Rollback transaction in case of error
            DB::rollBack();

            Log::error('Transaction failed', [
                'error_message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Return error response
            return response()->json(['error' => 'Trnsaction failed'], 500);
        }
    }

    public function getPayments($type, $filters){
        $query = ($type === 'sales') ? Transaction::query() : PurchaseTransaction::query();

        $query->select(
                ($type === 'sales' ? 'transactions.id' : 'purchase_transactions.id'),
                ($type === 'sales' ? 'sales_id as reference_id' : 'purchase_id as reference_id'),
                'store_id',
                'contact_id',
                'contacts.name as contact_name',
                'transaction_date',
                'amount',
                'payment_method',
                'transaction_type'
            )
            ->join('contacts', 'contact_id', '=', 'contacts.id')
            ->orderBy('transaction_date', 'desc');

        if(isset($filters['contact_id'])){
            $query->where('contact_id', $filters['contact_id']);
        }

        if(isset($filters['payment_method']) && $filters['payment_method'] !== 'All'){
            $query->where('payment_method', $filters['payment_method']);
        }

        if(isset($filters['start_date']) && isset($filters['end_date'])){
            $query->whereBetween('transaction_date', [$filters['start_date'], $filters['end_date']]);
        }
        $results = $query->paginate(25);
        $results->appends($filters);
        return $results;
    }

    public function viewPayments(Request $request, $type='sales'){
        $filters = $request->only(['contact_id', 'payment_method', 'start_date', 'end_date']);
        $transactions = $this->getPayments($type, $filters);

        if($type==='sales') $contacts = Contact::select('id', 'name','balance')->customers()->get();
        else $contacts = Contact::select('id', 'name','balance')->vendors()->get();

        return Inertia::render('Payment/Payment', [
            'payments' => $transactions,
            'transactionType'=>$type,
            'pageLabel'=>'Payments',
            'contacts'=>$contacts,
        ]);
    }

    public function findPayments(Request $request, $type){
        $transaction_id = $request->transaction_id;
        $query = ($type === 'sale') ? Transaction::query() : PurchaseTransaction::query();
        $query = $query->select('amount', 'payment_method', 'transaction_date');
        $query = ($type === 'sale') ? $query->where('sales_id', $transaction_id) : $query->where('purchase_id', $transaction_id);

        $results = $query->get ();
        return response()->json(['payments' => $results,]);
    }
}
