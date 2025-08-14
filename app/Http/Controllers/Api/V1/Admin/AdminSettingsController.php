<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait; // <-- USE TRAIT
use App\Models\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Http\Response;

class AdminSettingsController extends Controller
{
    use ApiResponseTrait; // <-- INCLUDE TRAIT

    public function show()
    {
        $settings = Settings::instance();
        return $this->successResponse($settings);
    }

    public function update(Request $request)
    {
        $settings = Settings::instance();
        $validator = Validator::make($request->all(), [
            'optimizer_service_url' => 'sometimes|required|url',
            'optimizer_timeout' => 'sometimes|required|integer',
            'unlock_price_amount' => 'sometimes|required|min:0',
            'unlock_currency' => ['sometimes','required','string','size:3'],
            'unlock_currency_symbol' => 'sometimes|required|string|max:5',
            'unlock_currency_symbol_position' => ['sometimes','required', Rule::in(['before', 'after'])],
            'notify_admin_on_payment' => 'sometimes|required|boolean', // <-- ADDED
            'admin_notification_email' => ['nullable', 'email', 'max:255', Rule::requiredIf( (bool) $request->input('notify_admin_on_payment', $settings->notify_admin_on_payment) )], // <-- ADDED
        ]);
        if ($validator->fails()) return $this->validationErrorResponse($validator);

        $settings->fill($validator->validated());
        $settings->save();
        Settings::clearCache(); // Important
        return $this->successResponse($settings, 'Settings updated successfully.');
    }
}
