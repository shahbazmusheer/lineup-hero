<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait; // <-- USE TRAIT
use App\Models\PromoCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Http\Response;

class AdminPromoCodeController extends Controller
{
    use ApiResponseTrait; // <-- INCLUDE TRAIT

    public function index(Request $request)
    {
        $promoCodes = PromoCode::orderBy('created_at', 'desc')->paginate($request->input('per_page', 25));
        return $this->successResponse($promoCodes, 'Promo codes retrieved successfully.');
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'code' => 'nullable|string|max:50|unique:promo_codes,code',
            'description' => 'nullable|string|max:1000', 'expires_at' => 'nullable|date|after:now',
            'max_uses' => 'nullable|integer|min:1', 'max_uses_per_user' => 'sometimes|required|integer|min:1',
            'is_active' => 'sometimes|boolean',
            // 'duration_in_days' => 'sometimes|required|integer',
        ]);
        if ($validator->fails()) return $this->validationErrorResponse($validator);

        $validatedData = $validator->validated();
        if (empty($validatedData['code'])) {
            $validatedData['code'] = strtoupper(Str::random(10));
            while (PromoCode::where('code', $validatedData['code'])->exists()) { $validatedData['code'] = strtoupper(Str::random(10)); }
        } else {
            $validatedData['code'] = strtoupper($validatedData['code']);
        }
        $validatedData['max_uses_per_user'] = $validatedData['max_uses_per_user'] ?? 1;
        $validatedData['is_active'] = $validatedData['is_active'] ?? true;

        $promoCode = PromoCode::create($validatedData);
        return $this->successResponse($promoCode, 'Promo code created successfully.', Response::HTTP_CREATED);
    }

    public function show(PromoCode $promoCode)
    {
        $promoCode->loadCount('redemptions as use_count_from_redemptions'); // Get live count from redemptions
        return $this->successResponse($promoCode);
    }

    public function update(Request $request, PromoCode $promoCode)
    {
        $validator = Validator::make($request->all(), [
            'description' => 'nullable|string|max:1000', 'expires_at' => 'nullable|date',
            'max_uses' => 'nullable|integer|min:' . $promoCode->use_count,
            'max_uses_per_user' => 'sometimes|required|integer|min:1',
            'is_active' => 'sometimes|boolean',
            // 'duration_in_days' => 'sometimes|required|integer',
        ]);
        if ($validator->fails()) return $this->validationErrorResponse($validator);

        $promoCode->update($validator->validated());
        $promoCode->loadCount('redemptions as use_count_from_redemptions');
        return $this->successResponse($promoCode, 'Promo code updated successfully.');
    }

    public function destroy(PromoCode $promoCode)
    {
        if ($promoCode->use_count > 0) {
            return $this->errorResponse('Cannot delete a promo code that has been used. Deactivate it instead.', Response::HTTP_CONFLICT);
        }
        $promoCode->delete();
        return $this->successResponse(null, 'Promo code deleted successfully.', Response::HTTP_OK, false);
    }
}
