<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait; // <-- USE TRAIT
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use App\Models\Admin;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Http\Response;

class AdminAuthController extends Controller
{
    use ApiResponseTrait; // <-- INCLUDE TRAIT
    protected $guard = 'api_admin';

    protected function formatTokenResponse($token, $admin) // Renamed param
    {
        return [
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth($this->guard)->factory()->getTTL() * 60,
            'user' => $admin // Use admin param
        ];
    }

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email',
            'password' => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator);
        }

        $credentials = $validator->validated();

        // Check if admin with the given email exists
        $admin = Admin::where('email', $credentials['email'])->first();

        if (!$admin) {
            // Admin with this email does not exist
            return $this->errorResponse('Email not found.', Response::HTTP_UNAUTHORIZED);
        }

        // Admin exists, now check the password
        if (!Hash::check($credentials['password'], $admin->password)) {
            // Password for the existing admin is incorrect
            return $this->errorResponse('Incorrect password.', Response::HTTP_UNAUTHORIZED);
        }

        // If email and password are correct, attempt to get a token
        if (! $token = auth($this->guard)->attempt($credentials)) {
            // Fallback for other auth issues
            return $this->errorResponse('Login failed. Please try again.', Response::HTTP_UNAUTHORIZED);
        }

        return $this->successResponse(
            $this->formatTokenResponse($token, $admin), // Pass the $admin object
            'Admin login successful.'
        );
    }


    public function logout()
    {
        try {
            auth($this->guard)->logout();
            return $this->successResponse(null, 'Admin successfully signed out.', Response::HTTP_OK, false);
        } catch (\Exception $e) {
            Log::error('Admin logout failed: ' . $e->getMessage());
            return $this->errorResponse('Could not sign out.', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function refresh()
    {
        try {
            $newToken = auth($this->guard)->refresh();
            return $this->successResponse($this->formatTokenResponse($newToken, auth($this->guard)->user()), 'Token refreshed successfully.');
        } catch (\Tymon\JWTAuth\Exceptions\TokenInvalidException $e) {
            return $this->errorResponse('Token is invalid.', Response::HTTP_UNAUTHORIZED);
        } catch (\Tymon\JWTAuth\Exceptions\JWTException $e) {
            return $this->errorResponse('Could not refresh token.', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function profile()
    {
        try {
            return $this->successResponse(auth($this->guard)->userOrFail());
        } catch (\Tymon\JWTAuth\Exceptions\UserNotDefinedException $e) {
            return $this->notFoundResponse('Admin not found or token invalid.');
        }
    }

    public function updateProfile(Request $request)
    {
        $admin = $request->user();
        if (!$admin) return $this->unauthorizedResponse('Admin not authenticated.');

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'email' => ['sometimes','required','string','email','max:255', Rule::unique('admins', 'email')->ignore($admin->id)],
        ]);
        if ($validator->fails()) return $this->validationErrorResponse($validator);

        $admin->fill($validator->validated());
        $admin->save();
        return $this->successResponse($admin, 'Admin profile updated successfully.');
    }

    public function changePassword(Request $request)
    {
        $admin = $request->user();
        if (!$admin) return $this->unauthorizedResponse('Admin not authenticated.');

        $validator = Validator::make($request->all(), [
            'current_password' => ['required','string', function ($attr, $val, $fail) use ($admin) { if (!Hash::check($val, $admin->password)) $fail('Current password incorrect.'); }],
            'password' => ['required','confirmed', Password::defaults(), 'different:current_password'],
        ]);
        if ($validator->fails()) return $this->validationErrorResponse($validator);

        $admin->password = Hash::make($request->password);
        $admin->save();
        return $this->successResponse(null, 'Admin password changed successfully.', Response::HTTP_OK, false);
    }
}
