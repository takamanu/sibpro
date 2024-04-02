<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Notification;
use Illuminate\Support\Facades\Auth;
use App\Models\Payment;
use App\Models\Year;
use App\Models\StudentClass;
use Illuminate\Support\Carbon;

class PaymentController extends Controller
{

    public function __construct()
    {
        $this->middleware('auth');
    }


    public function indexCart()
    {
        $credit = Payment::orderBy("user_id", "DESC")
                    ->where('user_id', Auth::user()->id)
                    ->where('status', 'Unpaid')
                    ->get();
        $years = Year::select('year_name','year_semester')->orderBy("updated_at", "DESC")->get();
        $notifications = Notification::orderBy("updated_at", 'DESC')->limit(10)->get();
        $studentClasses = StudentClass::orderBy("updated_at", "DESC")->get();
        $students = StudentClass::orderBy("class_name", 'ASC')->get();

        return view('payment.user.cart.index', compact('students', 'notifications', 'studentClasses','credit','years'));
    }

    public function indexPayment()
    {
        $credit = Payment::orderBy("invoice_number", "ASC")
                    ->where('user_id', Auth::user()->id)
                    ->where('status', 'Pending')
                    ->get();
        $years = Year::select('year_name','year_semester')->orderBy("updated_at", "DESC")->get();
        $notifications = Notification::orderBy("updated_at", 'DESC')->limit(10)->get();
        $studentClasses = StudentClass::orderBy("updated_at", "DESC")->get();
        $students = StudentClass::orderBy("class_name", 'ASC')->get();

        return view('payment.user.payment.index', compact('students', 'notifications', 'studentClasses','credit','years'));
    }

    public function addToCart(Request $request)
    {
        // Validasi request
        $request->validate([
            'transactions' => 'required|array',
            'transactions.*' => 'exists:payments,id' // Pastikan transaksi tersedia dalam database
        ]);

        $transactionIds = $request->input('transactions');

        // Perbarui status pembayaran untuk transaksi yang dipilih
        Payment::whereIn('id', $transactionIds)->update([
            'status' => 'Pending'
        ]);
    
        // Kembalikan respons sukses
        return response()->json(['message' => 'Pembayaran online berhasil dilakukan'], 200);
    }


    public function processOnlinePayment(Request $request)
    {
        // Validasi request
        $request->validate([
            'transactions' => 'required|array',
            'transactions.*' => 'exists:payments,id' // Pastikan transaksi tersedia dalam database
        ]);

        $transactionIds = $request->input('transactions');
    
        // Ambil invoice number terakhir
        $lastInvoiceNumber = Payment::whereYear('updated_at', Carbon::now()->year)
        ->whereMonth('updated_at', Carbon::now()->month)
        ->latest()
        ->where('status', '!=', 'Unpaid')
        ->value('increment');


        $increment = 1;
        if ($lastInvoiceNumber != NULL) {
        $increment = $lastInvoiceNumber + 1;
        }

        // Format tanggal hari ini dalam format "ddMMyy"
        $todayDate = Carbon::now()->format('dmy');

        // Buat invoice number baru
        $invoiceNumber = 'PAY'. '-' . $todayDate . '-' . $increment;


        // Perbarui status pembayaran untuk transaksi yang dipilih
        Payment::whereIn('id', $transactionIds)->update([
            'status' => 'Pending',
            'increment' => $increment, 
            'invoice_number' => $invoiceNumber
        ]);
    
        // Kembalikan respons sukses
        return response()->json(['message' => 'Pembayaran online berhasil dilakukan'], 200);
    }
    public function allData()
    {
        $credit = Payment::where('status','!=','Unpaid')
                    ->whereHas('year', function ($query) {$query->where('id', '=', Year::where('year_current', 'selected')->value('id'));})
                    ->orderBy("updated_at", "DESC")
                    ->get();
        $years = Year::select('year_name','year_semester')->orderBy("updated_at", "DESC")->get();
        $notifications = Notification::orderBy("updated_at", 'DESC')->limit(10)->get();
        $studentClasses = StudentClass::orderBy("updated_at", "DESC")->get();
        $students = StudentClass::orderBy("class_name", 'ASC')->get();

        return view('payment.allData', compact('students', 'notifications', 'studentClasses','credit','years'));
    }

    public function detail($id)
    {
        $order = Payment::find($id);
        \Midtrans\Config::$serverKey = config('midtrans.server_key');
        \Midtrans\Config::$isProduction = false;
        \Midtrans\Config::$isSanitized = true;
        // Set 3DS transaction for credit card to true
        \Midtrans\Config::$is3ds = true;

        $params = array(
            'transaction_details' => array(
                'order_id' => $id,
                'gross_amount' => $order->credit->credit_price,
            ),
            'customer_details' => array(
                'name' => $order->user->name,
                'email' => $order->user->email,
                'phone' =>  $order->user->nis,
            ),
        );

        $snapToken = \Midtrans\Snap::getSnapToken($params);
        $notifications = Notification::orderBy("updated_at", 'DESC')->limit(10)->get();
        return view('payment.credit.detail', compact('order', 'snapToken', 'notifications'));
    }
}
