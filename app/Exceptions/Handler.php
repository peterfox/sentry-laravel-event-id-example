<?php

namespace App\Exceptions;

use Closure;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Support\Arr;
use Illuminate\Support\ViewErrorBag;
use Sentry\Laravel\Integration;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    protected ?array $additionalJson = null;
    protected ?array $additionalViewProps = null;

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e, \Closure $pushDetails): void {
            $id = (string) Integration::captureUnhandledException($e);

            $pushDetails(json: ['id' => $id], view: ['errorId' => $id]);
        });
    }

    public function reportable(callable $reportUsing)
    {
        if (! $reportUsing instanceof Closure) {
            $reportUsing = $reportUsing(...);
        }

        return tap(new \App\ReportableHandler($reportUsing, $this->pushProps(...)), function ($callback) {
            $this->reportCallbacks[] = $callback;
        });
    }

    protected function pushProps(?array $json = null, ?array $view = null): void
    {
        $json = [...$this->additionalJson ?? [], ...$json ?? []];
        $this->additionalJson = $json !== [] ? $json : null;
        $view = [...$this->additionalViewProps ?? [], ...$view ?? []];
        $this->additionalViewProps = $view !== [] ? $view : null;
    }

    protected function convertExceptionToArray(Throwable $e)
    {
        return config('app.debug') ? [
            'message' => $e->getMessage(),
            ...$this->additionalJson !== null ? $this->additionalJson : [],
            'exception' => get_class($e),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => collect($e->getTrace())->map(fn ($trace) => Arr::except($trace, ['args']))->all(),
        ] : [
            'message' => $this->isHttpException($e) ? $e->getMessage() : 'Server Error',
            ...$this->additionalJson !== null ? $this->additionalJson : []
        ];
    }

    protected function renderHttpException(HttpExceptionInterface $e)
    {
        $this->registerErrorViewPaths();

        if ($view = $this->getHttpExceptionView($e)) {
            try {
                return response()->view($view, [
                    'errors' => new ViewErrorBag,
                    'exception' => $e,
                    ...$this->additionalViewProps !== null ? $this->additionalViewProps : []
                ], $e->getStatusCode(), $e->getHeaders());
            } catch (Throwable $t) {
                config('app.debug') && throw $t;

                $this->report($t);
            }
        }

        return $this->convertExceptionToResponse($e);
    }
}
