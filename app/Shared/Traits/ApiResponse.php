<?php

declare(strict_types=1);

namespace App\Shared\Traits;

use Illuminate\Http\JsonResponse;

/**
 * Standardised JSON envelope for every API response.
 *
 * Success envelope:
 * {
 *   "success": true,
 *   "message": "...",
 *   "data": { ... }
 * }
 *
 * Error envelope:
 * {
 *   "success": false,
 *   "message": "...",
 *   "error_code": "DOMAIN_RULE_CODE",
 *   "errors": { "field": ["..."] }  // optional
 * }
 */
trait ApiResponse
{
    /**
     * Return a successful JSON response.
     */
    protected function successResponse(
        mixed $data = null,
        string $message = 'OK',
        int $status = 200,
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $status);
    }

    /**
     * Return an error JSON response.
     *
     * @param  array<string, mixed>  $errors  Field-level validation errors (optional)
     */
    protected function errorResponse(
        string $message,
        string $errorCode = 'INTERNAL_ERROR',
        int $status = 500,
        array $errors = [],
    ): JsonResponse {
        $payload = [
            'success' => false,
            'message' => $message,
            'error_code' => $errorCode,
        ];

        if (! empty($errors)) {
            $payload['errors'] = $errors;
        }

        return response()->json($payload, $status);
    }
}
