<?php

namespace Time4dev\Async\Commands;

use App\Exceptions\StopJobException;
use Illuminate\Console\Command;
use Illuminate\Database\Connection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use NotificationChannels\Telegram\Exceptions\CouldNotSendNotification;
use Psr\SimpleCache\InvalidArgumentException;
use Symfony\Component\Process\Process;
use Time4dev\Async\Pool;
use Time4dev\Async\AsyncModel;
use Time4dev\Async\Process\ParallelProcess;
use Time4dev\Async\Process\Runnable;
use Time4dev\Async\Runtime\ParentRuntime;
use Time4dev\Async\StopAsyncException;

class StopWorkerCommand extends Command
{
    protected $signature = 'async:restart';
    protected $description = 'Async queue worker restart';

    public function handle()
    {
        AsyncModel::setCancelStatus();
        $this->info('Send restart async signal.');
    }
}
