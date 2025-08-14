<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait; // <-- USE TRAIT
use App\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Http\Response;

class OrganizationController extends Controller
{
    use ApiResponseTrait; // <-- INCLUDE TRAIT

    /**
     * Display a listing of organizations.
     * Route: GET /organizations (User)
     * Route: GET /admin/organizations (Admin)
     */
    public function index(Request $request)
    {
        if ($request->user('api_admin')) {
            $organizations = Organization::orderBy('name')->paginate($request->input('per_page', 25));
            return $this->successResponse($organizations, 'Organizations retrieved successfully (Admin View).');
        } else {
            $organizations = $organizations = $request->user()->administeredOrganizations()->select('id', 'name', 'organization_code', 'email', 'subscription_status', 'subscription_expires_at')->orderBy('name')->get();
            return $this->successResponse($organizations, 'Organizations retrieved successfully.');
        }
    }

    /**
     * Store a newly created organization (Admin only).
     * Route: POST /admin/organizations
     */
    public function store(Request $request)
    {
        // Auth via 'auth:api_admin' middleware
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:organizations,name',
            "organization_code" => 'required|string|max:50|unique:organizations,organization_code',
            'email' => 'nullable|email|max:255|unique:organizations,email',
        ]);
        if ($validator->fails()) return $this->validationErrorResponse($validator);

        $organization = Organization::create($validator->validated());
        return $this->successResponse($organization, 'Organization created successfully.', Response::HTTP_CREATED);
    }

    /**
     * Display the specified organization (Admin only).
     * Route: GET /admin/organizations/{organization}
     */
    public function show(Organization $organization)
    {
        // Auth via 'auth:api_admin' middleware
        $organization->load('teams:id,name,organization_id');
        return $this->successResponse($organization);
    }

    /**
     * Display the specified organization for a regular User.
     * Route: GET /organizations/{organization} (User authenticated)
     */
    public function showForUser(Organization $organization) // Route model binding
    {
        $orgData = $organization->only(['id', 'name', 'organization_code', 'email']); // Or select specific fields

        // Example: If you want to show teams of THIS organization that the CURRENT USER owns
         $user = auth()->user();
         $teamsInThisOrgOwnedByUser = $user->teams()
                                           ->where('organization_id', $organization->id)
                                           ->get();
         $orgData['teams'] = $teamsInThisOrgOwnedByUser;


        return $this->successResponse($orgData, 'Organization details retrieved successfully.');
    }

    /**
     * Display the specified organization by its unique code.
     * Accessible by authenticated users.
     * Route: GET /organizations/by-code/{organization_code}
     */
    public function showByCode(Request $request, string $organization_code)
    {
        // No specific user ownership check needed here, as the purpose is often
        // for a user to find/verify an organization by a code they might have received.
        // The 'auth:api_user' middleware ensures a user is logged in.

        if (empty(trim($organization_code))) {
            return $this->errorResponse('Organization code cannot be empty.', Response::HTTP_BAD_REQUEST);
        }

        // Find the organization by its code. Codes should ideally be case-insensitive during lookup.
        // Store codes in uppercase in DB and search for uppercase version.
        $organization = Organization::where('organization_code', strtoupper(trim($organization_code)))->first();

        if (!$organization) {
            return $this->notFoundResponse('Organization with this code not found.');
        }

        // Return basic, publicly relevant organization data
        // Avoid returning sensitive details if any exist on the model for admins
        $orgData = [
            'id' => $organization->id,
            'name' => $organization->name,
            'organization_code' => $organization->organization_code, // Confirm the code
            'email' => $organization->email, // If public contact email
        ];

        return $this->successResponse($orgData, 'Organization details retrieved successfully.');
    }

    /**
     * Update the specified organization (Admin only).
     * Route: PUT /admin/organizations/{organization}
     */
    public function update(Request $request, Organization $organization)
    {
        // Auth via 'auth:api_admin' middleware
        $validator = Validator::make($request->all(), [
            'name' => ['sometimes','required','string','max:255', Rule::unique('organizations', 'name')->ignore($organization->id)],
            'organization_code' => ['sometimes','required','string','max:50', Rule::unique('organizations', 'organization_code')->ignore($organization->id)],
            'email' => ['nullable','email','max:255', Rule::unique('organizations', 'email')->ignore($organization->id)],
            'subscription_status' => ['sometimes','required'],
            'subscription_expires_at' => ['sometimes', 'nullable'],
            'creator_user_id' => ['sometimes', 'nullable'],
        ]);
        if ($validator->fails()) return $this->validationErrorResponse($validator);

        $organization->update($validator->validated());
        $organization->load('teams:id,name,organization_id');
        return $this->successResponse($organization, 'Organization updated successfully.');
    }

    /**
     * Remove the specified organization (Admin only).
     * Route: DELETE /admin/organizations/{organization}
     */
    public function destroy(Organization $organization)
    {
        // Auth via 'auth:api_admin' middleware
        // $organization->teams()->update(['organization_id' => null]); // Handled by DB constraint ON DELETE SET NULL
        $organization->delete();
        return $this->successResponse(null, 'Organization deleted successfully.', Response::HTTP_OK, false);
    }
}
