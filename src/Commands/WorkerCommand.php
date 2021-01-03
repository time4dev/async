<?php

namespace Time4dev\Async\Commands;

use Illuminate\Console\Command;
use NotificationChannels\Telegram\Exceptions\CouldNotSendNotification;

class WorkerCommand extends Command
{
    protected $signature = 'async:queue {--sleep=3}';
    protected $description = 'Async queue worker';

    /**
     * @throws CouldNotSendNotification
     */
    public function handle()
    {
        $sleep = $this->option('sleep');

        while (true) {
            sleep($sleep);
        }
    }
}
