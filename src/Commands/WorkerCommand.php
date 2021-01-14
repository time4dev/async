<?php

namespace Time4dev\Async\Commands;

use App\Exceptions\StopJobException;
use Illuminate\Console\Command;
use Illuminate\Database\Connection;
use Illuminate\Support\Collection;
use NotificationChannels\Telegram\Exceptions\CouldNotSendNotification;
use Symfony\Component\Process\Process;
use Time4dev\Async\Pool;
use Time4dev\Async\AsyncModel;
use Time4dev\Async\Process\ParallelProcess;
use Time4dev\Async\Process\Runnable;
use Time4dev\Async\Runtime\ParentRuntime;
use Time4dev\Async\StopAsyncException;

class WorkerCommand extends Command
{
    protected $signature = 'async:queue {--sleep=2} {--timeout=600}';
    protected $description = 'Async queue worker';

    const STATUS_QUEUED = 'queued';
    const STATUS_START_PROCESS = 'start_process';
    const STATUS_PROCESS = 'process';

    /** @var string */
    protected $binary = PHP_BINARY;

    /** @var Connection */
    protected $database;

    /** @var int */
    protected static $currentId = 0;

    /** @var null|int */
    protected static $myPid = null;

    /** @var array */
    protected $processList = [];

    /**
     * @throws CouldNotSendNotification
     */
    public function handle()
    {
        if (!self::isSupported()) {
            return $this->error('Не поддерживается.');
        }

        $now = now()->format('Y-m-d H:i:s');
        $this->alert("Daemon async started {$now}");

        $sleep = $this->option('sleep');
        $this->database = app('db')->connection('mysql');
        $this->registerListener();

        $this->database
            ->table('async')
            ->whereIn('status', [self::STATUS_PROCESS, self::STATUS_START_PROCESS])
            ->delete();

        while (true) {
            sleep($sleep);

            $started = $this->database
                ->table('async')
                ->whereIn('status', [self::STATUS_PROCESS, self::STATUS_START_PROCESS])
                ->count();

            $concurrency = config('async.concurrency', 20);
            $limit = $concurrency - $started;

            if ($started >= $concurrency) {
                continue;
            }

            $inProgress = $this->database
                ->table('async')
                ->where('status', self::STATUS_PROCESS)
                ->orderBy('id')
                ->get();

            $inProgress->each(function ($row) {
                if (isset($this->processList[$row->pid])) {
                    $process = $this->processList[$row->pid];

                    if ($process->getCurrentExecutionTime() > $this->option('timeout')) {
                        $this->markAsTimedOut($process);
                    }
                } else {

                }
            });

            $queued = $this->getNextProcess($limit);

            if ($queued->isEmpty()) {
                continue;
            }

            $this->tryStartProcess($queued);
        }
    }

    public function tryStartProcess(Collection $queued)
    {
        $this->database
            ->table('async')
            ->whereIn('id', $queued->pluck('id'))
            ->update([
                'status' => self::STATUS_START_PROCESS
            ]);

        $outputLength = null;
        $this->line("count --> {$queued->count()}");

        $queued->each(function ($row) use ($outputLength) {
            usleep(200000);

            $res = $process = new Process([
                $this->binary,
                ParentRuntime::getChildProcessScript(),
                ParentRuntime::getAutoloader(),
                $row->payload,
                $outputLength,
                base_path(),
            ]);

            $process = ParallelProcess::create($process, self::getId());

            $process->then(function ($output) use ($row) {
                event(sprintf("async.%s.%s", $row->name, 'success'), [$row->id, $row->description, $output]);
            })->catch(function (\Throwable $exception) use ($row, $process) {
                $class = get_class($exception);
                $this->comment("process #{$process->getPid()} Exception {$class}");

                if ($exception instanceof StopAsyncException) {
                    event(sprintf("async.%s.%s", $row->name, 'stop'), [$row->id, $row->description, $exception]);
                    return;
                }

                event(sprintf("async.%s.%s", $row->name, 'fail'), [$row->id, $row->description, $exception]);
            })->timeout(function ()  use ($row) {
                event(sprintf("async.%s.%s", $row->name, 'timeout'), [$row->id, $row->description]);
            });

            if ($process instanceof ParallelProcess) {
                $process->getProcess()->setTimeout($this->option('timeout'));
            }

            $process->start();
            $this->processList[$process->getPid()] = $process;
            $this->info("process #{$process->getPid()} Started");

            $this->database
                ->table('async')
                ->where('id', $row->id)
                ->update([
                    'pid' => $process->getPid(),
                    'status' => self::STATUS_PROCESS,
                    'started_at' => now()
                ]);

            event(sprintf("async.%s.%s.%s", $row->name, $row->id, 'start'), [$row->id, $row->description, $process->getPid()]);
            event(sprintf("async.%s.%s", $row->name, 'start'), [$row->id, $row->description, $process->getPid()]);
        });
    }

    public function getNextProcess(int $limit)
    {
        return $this->database->transaction(function () use ($limit) {
            return $this->database->table('async')
                ->lock($this->getLockForPopping())
                ->where('status', self::STATUS_QUEUED)
                ->limit($limit)
                ->get();
        });
    }

    protected function getLockForPopping()
    {
        $databaseEngine = $this->database->getPdo()->getAttribute(\PDO::ATTR_DRIVER_NAME);
        $databaseVersion = $this->database->getConfig('version') ?? $this->database->getPdo()->getAttribute(\PDO::ATTR_SERVER_VERSION);

        if ($databaseEngine == 'mysql' && !strpos($databaseVersion, 'MariaDB') && version_compare($databaseVersion, '8.0.1', '>=') ||
            $databaseEngine == 'pgsql' && version_compare($databaseVersion, '9.5', '>=')) {
            return 'FOR UPDATE SKIP LOCKED';
        }

        return true;
    }

    public static function isSupported(): bool
    {
        return
            function_exists('pcntl_async_signals')
            && function_exists('posix_kill')
            && function_exists('proc_open');
    }

    protected static function getId(): string
    {
        if (self::$myPid === null) {
            self::$myPid = getmypid();
        }

        self::$currentId += 1;
        return (string)self::$currentId . (string)self::$myPid;
    }

    protected function registerListener()
    {
        pcntl_async_signals(true);

        pcntl_signal(SIGCHLD, function ($signo, $status) {
            while (true) {
                $pid = pcntl_waitpid(-1, $processState, WNOHANG | WUNTRACED);

                if ($pid <= 0) {
                    break;
                }

                $process = $this->processList[$pid] ?? null;

                if (!$process) {
                    continue;
                }

                if ($status['status'] === 0) {
                    $this->markAsFinished($process);

                    continue;
                }

                $this->markAsFailed($process);
            }
        });
    }

    public function markAsFinished(Runnable $process)
    {
        $this->info("process #{$process->getPid()} markAsFinished");

        unset($this->processList[$process->getPid()]);
        $this->deleteRowByProcess($process);
        $process->triggerSuccess();
    }

    public function markAsTimedOut(Runnable $process)
    {
        $this->info("process #{$process->getPid()} markAsTimedOut");

        $process->stop();
        unset($this->processList[$process->getPid()]);
        $this->deleteRowByProcess($process);
        $process->triggerTimeout();
    }

    public function markAsFailed(Runnable $process)
    {
        $this->info("process #{$process->getPid()} markAsFailed");

        unset($this->processList[$process->getPid()]);
        $this->deleteRowByProcess($process);
        $process->triggerError();
    }

    public function deleteRowByProcess(Runnable $process)
    {
        if ($pid = $process->getPid()) {
            $this->database
                ->table('async')
                ->where('pid', $pid)
                ->delete();
        }
    }
}
