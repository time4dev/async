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

    /** @var string */
    protected static $autoloader;

    /** @var string */
    protected static $childProcessScript;

    protected static $currentId = 0;

    protected static $myPid = null;

    public static function init(string $autoloader = null)
    {
        if (! $autoloader) {
            $existingAutoloaderFiles = array_filter([
                __DIR__.'/../../../../autoload.php',
                __DIR__.'/../../../autoload.php',
                __DIR__.'/../../vendor/autoload.php',
                __DIR__.'/../../../vendor/autoload.php',
            ], function (string $path) {
                return file_exists($path);
            });

            $autoloader = reset($existingAutoloaderFiles);
        }

        self::$autoloader = $autoloader;
        self::$childProcessScript = __DIR__.'/ChildRuntime.php';

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
            self::$childProcessScript,
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
}
