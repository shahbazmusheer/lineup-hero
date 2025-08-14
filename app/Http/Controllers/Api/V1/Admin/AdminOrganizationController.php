<?php
namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\Organization;
use App\Models\User; // For creator_user_id
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use App\Mail\OrganizationCredentialsMail;
use Illuminate\Support\Facades\Log; // Import Log

class AdminOrganizationController extends Controller
{
    use ApiResponseTrait;

    public function index(Request $request) {
        $organizations = Organization::with('creator:id,first_name,last_name,email')
            ->orderBy('name', 'asc')
            ->paginate($request->input('per_page', 25));
        return $this->successResponse($organizations, 'Organizations retrieved successfully.');
    }

    public function store(Request $request) {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:organizations,name',
            'email' => 'required|email|max:255|unique:organizations,email', // Org's contact email
            'pricing_type' => ['sometimes', 'required', Rule::in(['general', 'custom'])],
            'custom_price_amount' => [
//                Rule::requiredIf(fn() => $request->input('pricing_type') === 'custom'),
                'nullable', 'numeric'],
            'creator_user_id' => 'nullable|integer|exists:users,id', // User who requested it
            'organization_code' => 'nullable|string|max:50|unique:organizations,organization_code',
            'annual_team_allocation' => 'required|integer|min:0',
            'subscription_status' => ['nullable', Rule::in(['active', 'inactive', 'past_due', 'canceled'])],
            'subscription_expires_at' => 'nullable|date',
        ]);
        if ($validator->fails()) return $this->validationErrorResponse($validator);

        $validatedData = $validator->validated();
        $rawPassword = Str::random(12); // Generate temporary password

        if (empty($validatedData['organization_code'])) {
            do { $validatedData['organization_code'] = 'ORG-' . strtoupper(Str::random(8)); }
            while (Organization::where('organization_code', $validatedData['organization_code'])->exists());
        } else {
            $validatedData['organization_code'] = strtoupper($validatedData['organization_code']);
        }
        $validatedData['password'] = Hash::make($rawPassword);
        $validatedData['subscription_status'] = $validatedData['subscription_status'] ?? 'inactive';

        $organization = Organization::create($validatedData);

        // Send credentials email to the organization's contact email
        try {
            Mail::to($organization->email)->send(new OrganizationCredentialsMail($organization, $rawPassword));
            Log::info("Organization credentials sent to {$organization->email} for new Org ID {$organization->id}");
        } catch (\Exception $e) {
            Log::error("Failed to send org credentials email to {$organization->email}: " . $e->getMessage());
            // Continue, but log the error
        }

        return $this->successResponse($organization->load('creator:id,first_name,email'), 'Organization created successfully. Credentials emailed.', HttpResponse::HTTP_CREATED);
    }

    public function show(Organization $organization) {
        $organization->load(['creator:id,first_name,email', 'teams:id,name,user_id']); // Load relevant data
        return $this->successResponse($organization);
    }

    public function update(Request $request, Organization $organization) {
        $validator = Validator::make($request->all(), [
            'name' => ['sometimes','required','string','max:255', Rule::unique('organizations', 'name')->ignore($organization->id)],
            'email' => ['sometimes','required','email','max:255', Rule::unique('organizations', 'email')->ignore($organization->id)],
            'organization_code' => ['sometimes','required','string','max:50', Rule::unique('organizations', 'organization_code')->ignore($organization->id)],
            'pricing_type' => ['sometimes', 'required', Rule::in(['general', 'custom'])],
            'custom_price_amount' => [
//                Rule::requiredIf(fn() => $request->input('pricing_type') === 'custom'),
                'nullable', 'numeric'],
            'creator_user_id' => ['sometimes','nullable','integer','exists:users,id'],
            'annual_team_allocation' => 'sometimes|required|integer|min:0',
            'teams_created_this_period' => 'sometimes|integer|min:0', // Admin might reset this
            'subscription_status' => ['sometimes','required', Rule::in(['active', 'inactive', 'past_due', 'canceled'])],
            'subscription_expires_at' => ['sometimes','nullable','date'],
            'stripe_customer_id' => 'sometimes|nullable|string|max:255',
            'stripe_subscription_id' => 'sometimes|nullable|string|max:255',
            // Admin CANNOT directly set password here, use a separate "reset org password" flow if needed
        ]);
        if ($validator->fails()) return $this->validationErrorResponse($validator);

        $organization->update($validator->validated());
        return $this->successResponse($organization->load('creator:id,first_name,email'), 'Organization updated successfully.');
    }

    public function destroy(Organization $organization) {
        // Add checks: cannot delete if it has active teams or recent activity?
        if ($organization->teams()->count() > 0) {
            return $this->errorResponse('Cannot delete organization with associated teams. Please reassign or delete teams first.', HttpResponse::HTTP_CONFLICT);
        }
        $organization->delete();
        return $this->deletedResponse('Organization deleted successfully.');
    }
}