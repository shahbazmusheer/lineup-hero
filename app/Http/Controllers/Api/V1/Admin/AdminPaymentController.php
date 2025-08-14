<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait; // <-- USE TRAIT
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AdminPaymentController extends Controller
{
    use ApiResponseTrait; // <-- INCLUDE TRAIT

    public function index(Request $request)
    {
        $query = Payment::query()->with(['user:id,first_name,last_name,email'])->orderBy('paid_at', 'desc');
        // Apply filters as before...
        if ($request->filled('user_id')) $query->where('user_id', $request->input('user_id'));
        if ($request->filled('status')) $query->where('status', $request->input('status'));
        // ... other filters ...

        $payments = $query->paginate($request->input('per_page', 25));
        // The 'amount' will be transformed by the Payment model's accessor
        return $this->successResponse($payments, 'Payments retrieved successfully.');
    }

    public function show(Payment $payment)
    {
        $payment->load(['user:id,first_name,last_name,email']);
        // The 'amount' will be transformed by the Payment model's accessor
        return $this->successResponse($payment);
    }
}
