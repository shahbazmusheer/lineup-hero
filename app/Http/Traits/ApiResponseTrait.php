<?php

namespace App\Http\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response; // Import for status codes
use Illuminate\Support\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Validator as IlluminateValidator;
trait ApiResponseTrait
{
    /**
     * Send a success response.
     *
     * @param mixed $data
     * @param string $message
     * @param int $statusCode
     * @return \Illuminate\Http\JsonResponse
     */
    protected function successResponse(
        $data = null,
        string $message = 'Operation successful.',
        int $statusCode = Response::HTTP_OK,
        bool $includeDataKeyEvenIfNull = true // Default to including data key with [] or null
    ): JsonResponse
    {
        $responsePayload = [
            'success' => true,
            'message' => $message,
        ];

        if ($statusCode !== Response::HTTP_NO_CONTENT) {
            if ($data instanceof LengthAwarePaginator) {
                $responsePayload['data'] = $data->items();
                $responsePayload['meta'] = [ /* ... pagination meta ... */
                    'current_page' => $data->currentPage(), 'from' => $data->firstItem(),
                    'last_page' => $data->lastPage(), 'path' => $data->path(),
                    'per_page' => $data->perPage(), 'to' => $data->lastItem(), 'total' => $data->total(),
                ];
                $responsePayload['links'] = [ /* ... pagination links ... */
                    'first' => $data->url(1), 'last' => $data->url($data->lastPage()),
                    'prev' => $data->previousPageUrl(), 'next' => $data->nextPageUrl(),
                ];
            } elseif ($data !== null) {
                $responsePayload['data'] = $data;
            } elseif ($includeDataKeyEvenIfNull) { // Only include 'data' key if this flag is true and $data is null
                $responsePayload['data'] = []; // Or null, based on preference
            }
            // If $data is null AND $includeDataKeyEvenIfNull is false, 'data' key is omitted.
        }
        // If statusCode IS Response::HTTP_NO_CONTENT, 'data' key is always omitted.

        return response()->json($responsePayload, $statusCode);
    }
    protected function successResponse_old($data = null, string $message = 'Operation successful.', int $statusCode = Response::HTTP_OK): JsonResponse
    {
        $responsePayload = [
            'success' => true,
            'message' => $message,
        ];

        // If status code is 204 (No Content), data should strictly be absent or null.
        // Otherwise, include data, even if it's an empty array by default.
        if ($statusCode !== Response::HTTP_NO_CONTENT) {
            if ($data instanceof LengthAwarePaginator) {
                $responsePayload['data'] = $data->items();
                $responsePayload['meta'] = [ /* ... pagination meta ... */
                    'current_page' => $data->currentPage(), 'from' => $data->firstItem(),
                    'last_page' => $data->lastPage(), 'path' => $data->path(),
                    'per_page' => $data->perPage(), 'to' => $data->lastItem(), 'total' => $data->total(),
                ];
                $responsePayload['links'] = [ /* ... pagination links ... */
                    'first' => $data->url(1), 'last' => $data->url($data->lastPage()),
                    'prev' => $data->previousPageUrl(), 'next' => $data->nextPageUrl(),
                ];
            } elseif ($data !== null) {
                $responsePayload['data'] = $data;
            } else {
                // For 200 OK with no specific data, send empty array or null based on preference
                $responsePayload['data'] = []; // Or null
            }
        }
        // If statusCode is 204, the 'data' key will not be added to $responsePayload intentionally

        return response()->json($responsePayload, $statusCode);
    }

    /**
     * Send an error response.
     * If $details is a MessageBag or array, it's now primarily for logging or internal use,
     * as the main message should be specific.
     *
     * @param string $message The main error message to display.
     * @param int $statusCode The HTTP status code.
     * @param mixed|null $internalDetails Optional details for logging or internal debugging, NOT for direct display.
     * @return \Illuminate\Http\JsonResponse
     */
    protected function errorResponse(string $message, int $statusCode = Response::HTTP_BAD_REQUEST, $internalDetails = null): JsonResponse
    {
        $responsePayload = [
            'success' => false,
            'message' => $message,
        ];

        // Optionally, you could add 'details' to the response for debugging if needed,
        // but the primary message is now always what's passed as $message.
        // if ($internalDetails !== null && (is_array($internalDetails) || is_object($internalDetails))) {
        //     if (app()->environment('local', 'development')) { // Only show details in dev
        //         $responsePayload['debug_details'] = $internalDetails;
        //     }
        // }

        return response()->json($responsePayload, $statusCode);
    }

    /**
     * Send a validation error response, using the first validation error as the main message.
     *
     * @param \Illuminate\Validation\Validator $validator
     * @return \Illuminate\Http\JsonResponse
     */
    protected function validationErrorResponse(IlluminateValidator $validator): JsonResponse
    {
        $firstErrorMessage = 'The given data was invalid.'; // Default message
        if ($validator->errors()->isNotEmpty()) {
            $firstErrorMessage = $validator->errors()->first(); // Get the first actual validation error message
        }

        return $this->errorResponse(
            $firstErrorMessage, // Use the first validation error as the primary message
            Response::HTTP_UNPROCESSABLE_ENTITY
        // Optionally pass $validator->errors() as $internalDetails if needed for logging/debugging
        // , $validator->errors()
        );
    }


    /**
     * Send a not found error response.
     *
     * @param string $message
     * @return \Illuminate\Http\JsonResponse
     */
    protected function notFoundResponse(string $message = 'Resource not found.'): JsonResponse
    {
        return $this->errorResponse($message, Response::HTTP_NOT_FOUND);
    }

    /**
     * Send an unauthorized error response.
     *
     * @param string $message
     * @return \Illuminate\Http\JsonResponse
     */
    protected function unauthorizedResponse(string $message = 'Unauthenticated.'): JsonResponse
    {
        return $this->errorResponse($message, Response::HTTP_UNAUTHORIZED);
    }

    /**
     * Send a forbidden error response.
     *
     * @param string $message
     * @return \Illuminate\Http\JsonResponse
     */
    protected function forbiddenResponse(string $message = 'Forbidden.'): JsonResponse
    {
        return $this->errorResponse($message, Response::HTTP_FORBIDDEN);
    }

    /**
     * Get a human-readable duration string from a number of days.
     *
     * @param int $days
     * @return string
     */
    protected function getHumanReadableDuration(int $days): string
    {
        if ($days <= 0) {
            return "for a very short period"; // Or handle as error
        }
        if ($days >= 360 && $days <= 370) { // Roughly a year
            return "annually (for {$days} days)";
        } elseif ($days >= 175 && $days <= 185) { // Roughly 6 months
            return "for 6 months (approx. {$days} days)";
        } elseif ($days >= 28 && $days <= 32) { // Roughly a month
            return "monthly (approx. {$days} days)";
        } elseif ($days % 7 === 0 && $days / 7 >= 1 && $days / 7 <= 8) { // Weeks
            $weeks = $days / 7;
            return "for {$weeks} " . ($weeks > 1 ? "weeks" : "week") . " ({$days} days)";
        }
        return "for {$days} days"; // Default
    }
}
