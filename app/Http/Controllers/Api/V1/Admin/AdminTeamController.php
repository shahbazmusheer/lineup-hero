<?php
namespace App\Http\Controllers\Api\V1\Admin;
use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\Team; use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class AdminTeamController extends Controller {
    use ApiResponseTrait;
    public function index(Request $request) {
        $query = Team::query()
            ->with([
                'user:id,first_name,last_name,email', // User who owns the team
                'organization:id,name,organization_code,email' // Org the team is linked to
            ])
            ->orderBy('id', 'desc');
        // Add search/filter logic here if needed
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where('name', 'LIKE', "%{$search}%")
                ->orWhereHas('user', fn($q) => $q->where('email', 'LIKE', "%{$search}%"))
                ->orWhereHas('organization', fn($q) => $q->where('name', 'LIKE', "%{$search}%"));
        }
        $teams = $query->paginate($request->input('per_page', 25));
        return $this->successResponse($teams, 'All teams retrieved successfully.');
    }
    public function show(Team $team) {
        $team->load(['user:id,first_name,last_name,email', 'organization:id,name,organization_code,email', 'players', 'games']);
        return $this->successResponse($team);
    }
    // Update & Destroy methods allow admin to override/delete any team
    public function update(Request $request, Team $team) {
        $validator = Validator::make($request->all(), [ /* same as store but 'sometimes|required' */
            'name' => 'sometimes|required|string|max:255',
            'sport_type' => ['sometimes','required', Rule::in(['baseball', 'softball'])],
            'team_type' => ['sometimes','required', Rule::in(['travel', 'recreation', 'school'])],
            'age_group' => 'sometimes|required|string|max:50',
            'season' => 'nullable|string|max:50', 'year' => 'nullable|integer|digits:4',
            'city' => 'nullable|string|max:100', 'state' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100', 'organization_id' => 'nullable|exists:organizations,id',
            'is_setup_complete' => 'sometimes|boolean',
            'direct_activation_status' => 'sometimes|string|in:active,inactive',
            'direct_activation_expires_at' => 'sometimes|date',
            'is_editable_until' => 'sometimes|date',
        ]);
        if ($validator->fails()) return $this->validationErrorResponse($validator);

        $validatedData = $validator->validated();

        $team->update($validatedData);
        return $this->successResponse($team->load(['user:id,first_name,last_name,email', 'organization:id,name,organization_code,email', 'players', 'games']), 'Team updated by admin.');
    }
    public function destroy(Team $team) {
        $team->delete();
        return $this->deletedResponse('Team deleted by admin.');
    }
}