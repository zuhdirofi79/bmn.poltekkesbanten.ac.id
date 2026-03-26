<?php

namespace App\Exceptions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array
     */
    protected $dontFlash = [
        'password',
        'password_confirmation',
    ];

    /**
     * Report or log an exception.
     *
     * @param  \Throwable  $exception
     * @return void
     *
     * @throws \Exception
     */
    public function report(Throwable $exception)
    {
        parent::report($exception);
    }

    /**
     * Render an exception into an HTTP response.
     * Production-safe: never expose Laravel's default 500 debug/HTML crash for unhandled errors.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Throwable  $exception
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function render($request, Throwable $exception)
    {
        try {
            return $this->renderSafe($request, $exception);
        } catch (Throwable $inner) {
            try {
                Log::error('FAILSAFE_RENDER_CRASHED', [
                    'message' => $inner->getMessage(),
                    'trace' => $inner->getTraceAsString(),
                ]);
            } catch (Throwable $ignore) {
                // last resort
            }
            return response('System temporarily unavailable', 503);
        }
    }

    /**
     * @param  Request  $request
     */
    protected function renderSafe($request, Throwable $exception)
    {
        if ($exception instanceof ValidationException) {
            return parent::render($request, $exception);
        }

        if ($exception instanceof AuthenticationException) {
            return parent::render($request, $exception);
        }

        if ($exception instanceof \Illuminate\Auth\Access\AuthorizationException) {
            return parent::render($request, $exception);
        }

        if ($exception instanceof \Illuminate\Session\TokenMismatchException) {
            return parent::render($request, $exception);
        }

        if ($exception instanceof HttpExceptionInterface) {
            $status = $exception->getStatusCode();
            // Preserve maintenance (503), 404/403/419, etc. Only replace bare 500 HTTP errors.
            if ($status !== 500) {
                return parent::render($request, $exception);
            }
        }

        try {
            Log::error('APPLICATION_EXCEPTION_FAILSAFE', [
                'message' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
                'exception_class' => get_class($exception),
            ]);
        } catch (Throwable $ignore) {
        }

        $payload = ['message' => 'System temporarily unavailable'];

        if ($request->expectsJson() || $request->ajax() || $request->wantsJson()) {
            return response()->json($payload, 503);
        }

        try {
            return response()
                ->view('errors.failsafe', [
                    'message' => 'System temporarily unavailable',
                    'title' => 'Unavailable',
                ], 503);
        } catch (Throwable $ignore) {
            return response('System temporarily unavailable', 503);
        }
    }
}
