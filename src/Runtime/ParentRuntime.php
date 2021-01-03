<?php

namespace Time4dev\Async\Runtime;

use Closure;
use Opis\Closure\SerializableClosure;
use Time4dev\Async\Task;
use function Opis\Closure\serialize;
use function Opis\Closure\unserialize;
use Time4dev\Async\Pool;
use Time4dev\Async\Process\ParallelProcess;
use Time4dev\Async\Process\Runnable;
use Time4dev\Async\Process\SynchronousProcess;
use Symfony\Component\Process\Process;

class ParentRuntime
{
    /** @var bool */
    protected static $isInitialised = false;
    protected static $currentId = 0;
    protected static $autoloader = null;
    protected static $myPid = null;

    public static function init(string $autoloader = null)
    {
        if (!$autoloader) {
            $autoloader = self::getAutoloader();
        }

        self::$autoloader = $autoloader;
        self::$isInitialised = true;
    }

    /**
     * @param $task
     * @param int|null $outputLength
     * @param string|null $binary
     * @return Runnable
     */
    public static function createProcess($task, ?int $outputLength = null, ?string $binary = 'php'): Runnable
    {
        if (! self::$isInitialised) {
            self::init();
        }

        if (! Pool::isSupported()) {
            return new SynchronousProcess($task, self::getId());
        }

        $process = new Process([
            $binary,
            self::getChildProcessScript(),
            self::$autoloader,
            self::encodeTask($task),
            $outputLength,
            base_path(),
        ]);

        return new ParallelProcess($process, self::getId());
    }

    /**
     * @param Task|callable $task
     *
     * @return string
     */
    public static function encodeTask($task): string
    {
        if ($task instanceof Closure) {
            $task = new SerializableClosure($task);
        }

        return base64_encode(serialize($task));
    }

    public static function decodeTask(string $task)
    {
        return unserialize(base64_decode($task));
    }

    protected static function getId(): string
    {
        if (self::$myPid === null) {
            self::$myPid = getmypid();
        }

        self::$currentId += 1;

        return (string) self::$currentId.(string) self::$myPid;
    }

    public static function getAutoloader()
    {
        return __DIR__.'/RuntimeAutoload.php';
    }

    public static function getChildProcessScript()
    {
        return __DIR__.'/ChildRuntime.php';
    }
}
