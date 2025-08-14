<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth; // Import JWTAuth facade
use App\Models\User;
use App\Models\Admin;
use Illuminate\Http\Response as HttpResponse; // For HTTP status codes

class UniversalLoginController extends Controller
{
    use ApiResponseTrait;

    protected function formatTokenResponse($token, $authenticatedEntity, string $type, int $roleId)
    {
        return [
            'access_token' => $token,
            'token_type' => 'bearer',
            // Get TTL from the guard that was used for authentication
            'expires_in' => auth()->guard('api_' . $type)->factory()->getTTL() * 60,
            'user_type' => $type, // 'admin' or 'user'
            'role_id' => $roleId,
            'user' => $authenticatedEntity // The actual User or Admin model
        ];
    }

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator);
        }

        $credentials = $request->only('email', 'password');

        // --- Attempt to log in as Admin first ---
        if (Auth::guard('api_admin')->attempt($credentials)) {
            /** @var \App\Models\Admin $admin */
            $admin = Auth::guard('api_admin')->user();
            if ($admin) { // Double check user is fetched
                $token = JWTAuth::fromUser($admin); // Generate token for admin
                // Assuming Admins have role_id = 1
                return $this->successResponse(
                    $this->formatTokenResponse($token, $admin, 'admin', $admin->role_id ?? 1),
                    'Admin login successful.'
                );
            }
        }

        // --- If Admin login fails, attempt to log in as User ---
        if (Auth::guard('api_user')->attempt($credentials)) {
            /** @var \App\Models\User $user */
            $user = Auth::guard('api_user')->user();
            if ($user) { // Double check user is fetched
                $token = JWTAuth::fromUser($user); // Generate token for user
                // Assuming Users have role_id = 2
                return $this->successResponse(
                    $this->formatTokenResponse($token, $user, 'user', $user->role_id ?? 2),
                    'User login successful.'
                );
            }
        }

        // If both attempts fail
        // To avoid user enumeration, return a generic message.
        // You could also check if email exists in *either* table first, then which password fails,
        // but that leaks more info.
        return $this->errorResponse('Invalid credentials.', HttpResponse::HTTP_UNAUTHORIZED);
    }

    /**
     * Universal Logout (detects guard based on token).
     * Note: This might be complex if token doesn't inherently identify the guard.
     * A simpler approach is separate logout endpoints, or client sends user_type.
     * For JWT, the token itself is usually guard-agnostic once issued.
     * The middleware determines the guard.
     *
     * This method assumes the JWT middleware has already authenticated and identified the user/admin.
     */
    public function logout(Request $request)
    {
        try {
            // Check which guard is currently active, if possible, or just logout
            // $guard = Auth::guard(); // This gets the default guard based on request
            // If your JWT tokens are structured to identify type (e.g., in claims), you could use that.
            // For simplicity, let's assume the auth middleware has set the correct guard instance.
            Auth::logout(); // This should log out the currently authenticated user/admin via JWT
            return $this->successResponse(null, 'Successfully logged out.');
        } catch (\Exception $e) {
            Log::error('Universal logout failed: ' . $e->getMessage());
            return $this->errorResponse('Could not log out.', HttpResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}