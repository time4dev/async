<?php

use Time4dev\Async\Pool;
use Time4dev\Async\Process\ParallelProcess;
use Time4dev\Async\Process\Runnable;
use Time4dev\Async\Runtime\ParentRuntime;
use Time4dev\Async\Task;

if (! function_exists('async')) {
    /**
     * @param Task|callable $task
     *
     * @return ParallelProcess
     */
    function async($task): Runnable
    {
        return ParentRuntime::createProcess($task);
    }
}

if (! function_exists('await')) {
    function await(Pool $pool): array
    {
        return $pool->wait();
    }
}
