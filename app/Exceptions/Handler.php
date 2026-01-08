<?php

namespace App\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotAcceptableHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<Throwable>>
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     *
     * @return void
     */
    public function register()
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Throwable  $e
     * @return \Symfony\Component\HttpFoundation\Response
     *
     * @throws \Throwable
     */
    public function render($request, Throwable $e)
    {
        // Check if request expects JSON response (API calls)
        if ($request->expectsJson()) {
            return $this->handleApiException($request, $e);
        }

        return parent::render($request, $e);
    }

    /**
     * Handle API exceptions with standardized JSON response
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Throwable  $e
     * @return \Illuminate\Http\JsonResponse
     */
    protected function handleApiException($request, Throwable $e)
    {
        $statusCode = $this->getStatusCode($e);
        $message = $this->getMessage($e, $request);
        $errors = $this->getErrors($e, $request);

        $response = [
            'success' => false,
            'statusCode' => $statusCode,
            'message' => $message,
            'errors' => $errors,
        ];

        // Add additional debug information for development environment
        if (config('app.debug') && config('app.env') !== 'production') {
            $response['debug'] = [
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTrace(),
            ];
        }

        return response()->json($response, $statusCode);
    }

    /**
     * Get HTTP status code from exception
     *
     * @param  \Throwable  $e
     * @return int
     */
    protected function getStatusCode(Throwable $e): int
    {
        if ($e instanceof ValidationException) {
            return 422;
        }

        if ($e instanceof AuthenticationException) {
            return 401;
        }

        if ($e instanceof AuthorizationException) {
            return 403;
        }

        if ($e instanceof ModelNotFoundException) {
            return 404;
        }

        if ($e instanceof NotFoundHttpException) {
            return 404;
        }

        if ($e instanceof MethodNotAllowedHttpException) {
            return 405;
        }

        if ($e instanceof NotAcceptableHttpException) {
            return 406;
        }

        if ($e instanceof ConflictHttpException) {
            return 409;
        }

        if ($e instanceof HttpException) {
            return $e->getStatusCode();
        }

        // Default to 500 for unknown exceptions
        return 500;
    }

    /**
     * Get error message from exception
     *
     * @param  \Throwable  $e
     * @param  \Illuminate\Http\Request  $request
     * @return string
     */
    protected function getMessage(Throwable $e, $request): string
    {
        if ($e instanceof ValidationException) {
            return 'Validation failed';
        }

        if ($e instanceof AuthenticationException) {
            return 'Unauthenticated';
        }

        if ($e instanceof AuthorizationException) {
            return 'Unauthorized: ' . ($e->getMessage() ?: 'You do not have permission to perform this action');
        }

        if ($e instanceof ModelNotFoundException) {
            $model = $this->getModelName($e);
            return "Resource not found: {$model}";
        }

        if ($e instanceof NotFoundHttpException) {
            $endpoint = $request->path();
            return "Endpoint not found: {$endpoint}";
        }

        if ($e instanceof MethodNotAllowedHttpException) {
            $method = $request->method();
            $endpoint = $request->path();
            return "Method not allowed: {$method} {$endpoint}";
        }

        if ($e instanceof NotAcceptableHttpException) {
            return 'Not acceptable: The requested format is not supported';
        }

        if ($e instanceof ConflictHttpException) {
            return 'Conflict: ' . ($e->getMessage() ?: 'The request conflicts with the current state');
        }

        // Return exception message or generic message for production
        if (config('app.debug') && config('app.env') !== 'production') {
            return $e->getMessage() ?: 'Server error occurred';
        }

        return 'Server error occurred';
    }

    /**
     * Get detailed errors from exception
     *
     * @param  \Throwable  $e
     * @param  \Illuminate\Http\Request  $request
     * @return array|null
     */
    protected function getErrors(Throwable $e, $request): ?array
    {
        // Validation errors
        if ($e instanceof ValidationException) {
            return $e->errors();
        }

        // Authorization error - include action attempted
        if ($e instanceof AuthorizationException) {
            return [
                'action' => $request->path(),
                'message' => $e->getMessage() ?: 'You do not have permission to perform this action',
            ];
        }

        // Model not found - include model and ID if available
        if ($e instanceof ModelNotFoundException) {
            $errors = [
                'resource' => $this->getModelName($e),
            ];

            // Try to extract the ID from the exception message or request
            if (preg_match('/\[(\d+)\]/', $e->getMessage(), $matches)) {
                $errors['id'] = $matches[1];
            }

            return $errors;
        }

        // Not found endpoint - include requested URL
        if ($e instanceof NotFoundHttpException) {
            return [
                'endpoint' => $request->path(),
                'url' => $request->fullUrl(),
            ];
        }

        // Method not allowed - include method and allowed methods
        if ($e instanceof MethodNotAllowedHttpException) {
            $allowedMethods = $e->getHeaders()['Allow'] ?? 'Unknown';
            return [
                'method' => $request->method(),
                'endpoint' => $request->path(),
                'allowed_methods' => explode(', ', $allowedMethods),
            ];
        }

        // Not acceptable - include accept headers
        if ($e instanceof NotAcceptableHttpException) {
            return [
                'endpoint' => $request->path(),
                'requested_format' => $request->header('Accept'),
                'supported_formats' => ['application/json'],
            ];
        }

        // Conflict - include conflict details
        if ($e instanceof ConflictHttpException) {
            return [
                'endpoint' => $request->path(),
                'message' => $e->getMessage() ?: 'Resource conflict detected',
                'method' => $request->method(),
            ];
        }

        // For development, include exception message in errors
        if (config('app.debug') && config('app.env') !== 'production') {
            return [
                'message' => $e->getMessage(),
                'endpoint' => $request->path(),
                'method' => $request->method(),
            ];
        }

        return null;
    }

    /**
     * Extract model name from ModelNotFoundException
     *
     * @param  \Illuminate\Database\Eloquent\ModelNotFoundException  $e
     * @return string
     */
    protected function getModelName(ModelNotFoundException $e): string
    {
        $model = $e->getModel();

        // Get just the class name without namespace
        if ($model) {
            $parts = explode('\\', $model);
            return end($parts);
        }

        return 'Resource';
    }

    /**
     * Convert an authentication exception into a response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Illuminate\Auth\AuthenticationException  $exception
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function unauthenticated($request, AuthenticationException $exception)
    {
        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'statusCode' => 401,
                'message' => 'Unauthenticated',
                'errors' => null,
            ], 401);
        }

        return redirect()->guest(route('login'));
    }
}

