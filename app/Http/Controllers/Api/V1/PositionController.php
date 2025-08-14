<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait; // <-- USE TRAIT
use App\Models\Position;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Http\Response; // For status codes

class PositionController extends Controller
{
    use ApiResponseTrait; // <-- INCLUDE TRAIT

    private $corePositions = ['P', 'C', '1B', '2B', '3B', 'SS', 'LF', 'CF', 'RF', 'OUT'];
    private $nonEditablePositions = ['OUT'];

    /**
     * Display a listing of positions.
     * Route: GET /positions (User)
     * Route: GET /admin/positions (Admin)
     */
    public function index(Request $request)
    {
        if ($request->user('api_admin')) { // Check if called from admin context
            $positions = Position::orderBy('id')->orderBy('name')->paginate($request->input('per_page', 50));
            // The successResponse trait handles paginator instances correctly
            return $this->successResponse($positions, 'Positions retrieved successfully (Admin View).');
        } else {
            $positions = Position::orderBy('id')->orderBy('name')->get();
            return $this->successResponse($positions, 'Positions retrieved successfully.');
        }
    }

    /**
     * Store a newly created position (Admin only).
     * Route: POST /admin/positions
     */
    public function store(Request $request)
    {
        // Authorization via 'auth:api_admin' middleware on route

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:10|unique:positions,name',
            'display_name' => 'required|string|max:50',
            'category' => 'required|string|max:50',
            'is_editable' => 'sometimes|boolean',
        ]);
        if ($validator->fails()) return $this->validationErrorResponse($validator);

        $validatedData = $validator->validated();
        $validatedData['is_editable'] = $validatedData['is_editable'] ?? true;
        $position = Position::create($validatedData);
        return $this->successResponse($position, 'Position created successfully.', Response::HTTP_CREATED);
    }

    /**
     * Display the specified position (Admin only).
     * Route: GET /admin/positions/{position}
     */
    public function show(Position $position)
    {
        // Authorization via 'auth:api_admin' middleware on route
        return $this->successResponse($position);
    }

    /**
     * Update the specified position (Admin only).
     * Route: PUT /admin/positions/{position}
     */
    public function update(Request $request, Position $position)
    {
        // Authorization via 'auth:api_admin' middleware on route

        if (!$position->is_editable || in_array($position->name, $this->nonEditablePositions)) {
            if (($request->has('name') && $request->input('name') !== $position->name) ||
                ($request->has('is_editable') && $request->boolean('is_editable') != $position->is_editable) ) {
                return $this->forbiddenResponse("Cannot change core attributes of non-editable position '{$position->name}'.");
            }
        }

        $validator = Validator::make($request->all(), [
            'name' => ['sometimes','required','string','max:10', Rule::unique('positions', 'name')->ignore($position->id)],
            'display_name' => 'sometimes|required|string|max:50',
            'category' => 'sometimes|required|string|max:50',
            'is_editable' => 'sometimes|required|boolean',
        ]);
        if ($validator->fails()) return $this->validationErrorResponse($validator);

        $validatedData = $validator->validated();
        if (in_array($position->name, $this->nonEditablePositions) && isset($validatedData['is_editable']) && $validatedData['is_editable']) {
            return $this->forbiddenResponse("Position '{$position->name}' cannot be made editable.");
        }

        $position->update($validatedData);
        return $this->successResponse($position, 'Position updated successfully.');
    }

    /**
     * Remove the specified position (Admin only).
     * Route: DELETE /admin/positions/{position}
     */
    public function destroy(Position $position)
    {
        // Authorization via 'auth:api_admin' middleware on route

        if (!$position->is_editable || in_array($position->name, $this->corePositions)) {
            return $this->forbiddenResponse("Cannot delete core/non-editable position '{$position->name}'.");
        }
        if ($position->playerPreferences()->exists()) {
            return $this->errorResponse("Cannot delete position '{$position->name}' as it is used in preferences.", Response::HTTP_CONFLICT);
        }
        try {
            $position->delete();
            return $this->successResponse(null, 'Position deleted successfully.', Response::HTTP_OK, false);
        } catch (\Exception $e) {
            // Log::error(...)
            return $this->errorResponse('Failed to delete position.', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
