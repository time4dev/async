<?php

namespace Time4dev\Async;

use RuntimeException;

trait Invocation
{
    /**
     * Handle method name.
     *
     * @var string
     */
    protected $handleMethod = 'handle';

    /**
     * Call this class.
     */
    public function __invoke()
    {
        if (! method_exists($this, $this->handleMethod)) {
            throw new RuntimeException(sprintf('`handle` method must be define in (%s)', __CLASS__));
        }

        return app()->call([$this, $this->handleMethod]);
    }
}
