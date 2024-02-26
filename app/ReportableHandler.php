<?php

namespace App;

use Illuminate\Support\Traits\ReflectsClosures;
use Throwable;

class ReportableHandler extends \Illuminate\Foundation\Exceptions\ReportableHandler
{
    use ReflectsClosures;

    /**
     * The underlying callback.
     *
     * @var callable
     */
    protected $callback;

    /**
     * Indicates if reporting should stop after invoking this handler.
     *
     * @var bool
     */
    protected $shouldStop = false;

    protected ?\Closure $pushProps = null;

    /**
     * Create a new reportable handler instance.
     *
     * @param  callable  $callback
     * @return void
     */
    public function __construct(callable $callback, callable $pushProps)
    {
        $this->callback = $callback;
        $this->pushProps = $pushProps(...);
    }

    /**
     * Invoke the handler.
     *
     * @param  \Throwable  $e
     * @return bool
     */
    public function __invoke(Throwable $e)
    {
        $result = call_user_func($this->callback, $e, $this->pushProps);

        if ($result === false) {
            return false;
        }

        return ! $this->shouldStop;
    }
}
