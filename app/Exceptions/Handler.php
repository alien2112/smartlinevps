<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
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

        $this->renderable(function (NotFoundHttpException $e, $request) {
            if ($request->wantsJson()) {
                abort(response()->json(responseFormatter(DEFAULT_404), 404));
            }
        });

        $this->renderable(function (HttpException $e, $request) {
            if ($request->wantsJson()) {
                // Log detailed error for debugging
                Log::error('HTTP Exception occurred', [
                    'message' => $e->getMessage(),
                    'code' => $e->getStatusCode(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                    'url' => $request->fullUrl(),
                    'method' => $request->method(),
                ]);

                // Return generic message to user
                $statusCode = $e->getStatusCode();
                $message = 'An error occurred. Please try again later.';

                // Provide slightly more specific messages for common status codes
                if ($statusCode === 401) {
                    $message = 'Unauthorized. Please log in.';
                } elseif ($statusCode === 403) {
                    $message = 'You do not have permission to perform this action.';
                } elseif ($statusCode === 404) {
                    $message = 'The requested resource was not found.';
                } elseif ($statusCode === 429) {
                    $message = 'Too many requests. Please try again later.';
                }

                abort(response()->json([
                    'response_code' => $statusCode,
                    'message' => $message,
                    'content' => null,
                    'errors' => []
                ], $statusCode));
            }
        });
    }
}
