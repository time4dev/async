<?php

namespace Time4dev\Async;

class StopAsyncException extends \Exception implements \Throwable
{
    protected $code = 'stop';
}
