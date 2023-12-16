<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Notification;
use App\Models\StudentClass;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;

class EnrollmentController extends Controller
{

    public function __construct()
    {
        $this->middleware('auth');
    }
    public function index()
    {
        $notifications = Notification::orderBy("updated_at", 'DESC')->limit(10)->get();
        $studentClasses = StudentClass::orderBy("updated_at", "DESC")->get();

        return view('enrollment.index', compact('notifications', 'studentClasses'));
    }

    public function detail($uuid)
    {
        $data = StudentClass::where('uuid', $uuid)->first();

        if (!$data) {
            // Handle jika data tidak ditemukan
            abort(404);
        }

        $id = $data->id;

        $notifications = Notification::orderBy("updated_at", 'DESC')->limit(10)->get();
        $students = User::where('class_id', $id)
            ->whereNotNull('category_id')
            ->with(['paymentAttribute' => function ($query) {
                $query->select('attributes.id', 'attribute_name','attribute_price as total_price', 'status', 'payments.price');
            }])
            ->get();
            

        $class = StudentClass::find($id);


        return view('enrollment.detail', compact('notifications', 'students', 'class'));
    }

    public function billingStudent($uuid)
    {
        $data = User::where('uuid', $uuid)->first();

        if (!$data) {
            // Handle jika data tidak ditemukan
           abort(404);
       }

        $id = $data->id;

        $student = DB::table('payments')
                    ->join('users', 'payments.user_id', '=', 'users.id')
                    ->join('attributes', 'payments.attribute_id', '=', 'attributes.id')
                    ->select('attributes.attribute_name','attributes.attribute_price','payments.status','payments.id')
                    ->where('user_id',$id)
                    ->get();

        $user = User::find($id);
        

        $notifications = Notification::orderBy("updated_at", 'DESC')->limit(10)->get();

        return view('enrollment.billing', compact('notifications', 'student','user'));
    }

    public function processMultiplePayments(Request $request)
    {
        $transactionIds = $request->input('attribute_id');
        $transactions = [];

        // Validate if the user is authorized to perform these transactions (optional)

        // Now process the selected transactions
        foreach ($transactionIds as $transactionId) {
            $transaction = Payment::find($transactionId);
            
            // Add the transaction to the array
            $transactions[] = $transaction;
        }

            // Calculate total amount for the group payment
        $totalAmount = collect($transactions)->sum(function ($transaction) {
            return $transaction->attribute->attribute_price;
        });

        // Set up Midtrans configuration outside the loop
        \Midtrans\Config::$serverKey = config('midtrans.server_key');
        \Midtrans\Config::$isProduction = false;
        \Midtrans\Config::$isSanitized = true;
        \Midtrans\Config::$is3ds = true;

        // Create a unique order ID for the group payment
        $orderId = 'GROUP_' . now()->format('YmdHis') .$transactions[0]->id;

        // Prepare Midtrans parameters for the group payment
        $params = [
            'transaction_details' => [
                'order_id' => $orderId,
                'gross_amount' => $totalAmount,
            ],
            'customer_details' => [
                'name' => $transactions[0]->user->name, // Assuming all transactions belong to the same user
                'email' => $transactions[0]->user->email,
                'phone' => $transactions[0]->user->nis,
            ],
        ];

        foreach ($transactions as $transaction) {
            $params['item_details'][] = [
                'id' => $transaction->id,
                'name' => $transaction->attribute->attribute_name,
                'price' => $transaction->attribute->attribute_price,
                'quantity' => 1,
            ];
        }

        // Get Snap token for the group payment
        $snapToken = \Midtrans\Snap::getSnapToken($params);

        $notifications = Notification::orderBy("updated_at", 'DESC')->limit(10)->get();

        return view('enrollment.payment', compact('transactions', 'snapToken', 'notifications'));
    }

}

