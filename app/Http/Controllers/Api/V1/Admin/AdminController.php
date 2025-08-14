<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait; // <-- USE TRAIT
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Http\Response;

class AdminUserController extends Controller // Corrected class name
{
    use ApiResponseTrait; // <-- INCLUDE TRAIT

    public function index(Request $request)
    {
        $users = User::query()
            ->select(['id', 'first_name', 'last_name', 'email', 'phone', 'created_at'])
            ->orderBy('last_name')->orderBy('first_name')
            ->paginate($request->input('per_page', 25));
        return $this->successResponse($users, 'Users retrieved successfully.');
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:100', 'last_name' => 'required|string|max:100',
            'email' => 'required|string|email|max:100|unique:users,email',
            'phone' => 'nullable|string|max:20|unique:users,phone',
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);
        if ($validator->fails()) return $this->validationErrorResponse($validator);

        $user = User::create([ /* ... user data ... */
            'first_name' => $request->first_name, 'last_name' => $request->last_name,
            'email' => $request->email, 'phone' => $request->phone,
            'password' => Hash::make($request->password),
        ]);
        // $user->makeHidden('password'); // Not needed, model $hidden handles it
        return $this->successResponse($user, 'User created successfully.', Response::HTTP_CREATED);
    }

    public function show(User $user)
    {
        $user->load('teams:id,name,user_id');
        return $this->successResponse($user);
    }

    public function update(Request $request, User $user)
    {
        $validator = Validator::make($request->all(), [
            'first_name' => 'sometimes|required|string|max:100',
            'last_name' => 'sometimes|required|string|max:100',
            'email' => ['sometimes','required','string','email','max:100', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable','string','max:20', Rule::unique('users', 'phone')->ignore($user->id)],
            'password' => ['nullable', 'confirmed', Password::defaults()],
        ]);
        if ($validator->fails()) return $this->validationErrorResponse($validator);

        $validatedData = $validator->validated();
        $user->fill(collect($validatedData)->except('password')->toArray());
        if (!empty($validatedData['password'])) {
            $user->password = Hash::make($validatedData['password']);
        }
        $user->save();
        $user->load('teams:id,name,user_id');
        return $this->successResponse($user, 'User updated successfully.');
    }

    public function destroy(Request $request, User $user)
    {
        $admin = $request->user('api_admin');
        if ($admin && $admin->id === $user->id && get_class($admin) === get_class($user)) { // Prevent admin deleting self if User model used for admin
            return $this->forbiddenResponse('Administrators cannot delete their own user account if it\'s a regular user type.');
        }
        // If Admin model is different, self-delete is prevented in AdminAuthController
        // This AdminUserController manages 'User' models.

        try {
            $user->delete();
            return $this->successResponse(null, 'User deleted successfully.', Response::HTTP_OK, false);
        } catch (\Exception $e) {
            // Log::error(...)
            return $this->errorResponse('Failed to delete user.', Response::HTTP_INTERNAL_SERVER_ERROR, $e->getMessage());
        }
    }
}
