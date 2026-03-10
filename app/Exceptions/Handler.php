<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Shared\Exceptions\DomainException as BaseDomainException;
use App\Shared\Exceptions\SodViolationException;
use App\Shared\Traits\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException as LaravelValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

/**
 * Global exception handler.
 *
 * Maps domain exceptions to the standardised API envelope.
 * All 5xx stack traces are masked in production.
 */
class Handler extends ExceptionHandler
{
    use ApiResponse;

    /** @var list<class-string<\Throwable>> */
    protected $dontReport = [];

    /** @var list<string> */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            // Future: send to Sentry / Pulse
        });
    }

    /**
     * Render exceptions for API requests into the standard envelope.
     */
    public function render($request, Throwable $e): mixed
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            return $this->renderApiException($request, $e);
        }

        return parent::render($request, $e);
    }

    private function renderApiException(Request $request, Throwable $e): JsonResponse|\Symfony\Component\HttpFoundation\Response
    {
        // ── Domain-specific exceptions ────────────────────────────────────────
        if ($e instanceof SodViolationException) {
            return $this->errorResponse($e->getMessage(), $e->errorCode, 403, $e->context);
        }

        if ($e instanceof BaseDomainException) {
            return $this->errorResponse(
                $e->getMessage(),
                $e->errorCode,
                $e->httpStatus,
                $e->context,
            );
        }

        // ── Laravel validation failure ────────────────────────────────────────
        if ($e instanceof LaravelValidationException) {
            return $this->errorResponse(
                'Validation failed.',
                'VALIDATION_ERROR',
                422,
                $e->errors()
            );
        }

        // ── Unauthenticated ───────────────────────────────────────────────────
        if ($e instanceof AuthenticationException) {
            return $this->errorResponse('Unauthenticated.', 'UNAUTHENTICATED', 401);
        }

        // ── Authorization failure (policy denied) ──────────────────────────────
        if ($e instanceof AuthorizationException) {
            return $this->errorResponse('This action is unauthorized.', 'FORBIDDEN', 403);
        }

        // ── HttpResponseException (e.g. rate limit 429, abort with Response) ───────
        if ($e instanceof \Illuminate\Http\Exceptions\HttpResponseException) {
            return $e->getResponse();
        }

        // ── Generic HTTP exceptions (404, 405, etc.) ──────────────────────────
        if ($e instanceof HttpException) {
            return $this->errorResponse(
                $e->getMessage() ?: 'HTTP error.',
                'HTTP_ERROR',
                $e->getStatusCode()
            );
        }

        // ── Unexpected exceptions — mask in production ────────────────────────
        $message = app()->isProduction()
            ? 'An unexpected error occurred. Please contact support.'
            : $e->getMessage();

        return $this->errorResponse($message, 'INTERNAL_ERROR', 500);
    }
}
