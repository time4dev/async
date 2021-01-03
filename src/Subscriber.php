<?php


namespace Time4dev\Async;


use Illuminate\Events\Dispatcher;

abstract class Subscriber
{
    protected $name;

    abstract public function onSuccess(int $rowId, ?string $description, $output);
    abstract public function onStart(int $rowId, ?string $description, int $pid);
    abstract public function onStop(int $rowId, ?string $description, \Throwable $throwable = null);
    abstract public function onTimeout(int $rowId, ?string $description, \Throwable $throwable = null);
    abstract public function onFail(int $rowId, ?string $description, \Throwable $throwable = null);

    /**
     * @param Dispatcher $events
     */
    public function subscribe(Dispatcher $events)
    {
        $events->listen(
            sprintf("%s.%s",$this->name, 'start'),
            static::class.'@onStart'
        );

        $events->listen(
            sprintf("%s.%s",$this->name, 'stop'),
            static::class.'@onStop'
        );

        $events->listen(
            sprintf("%s.%s",$this->name, 'fail'),
            static::class.'@onFail'
        );

        $events->listen(
            sprintf("%s.%s",$this->name, 'timeout'),
            static::class.'@onTimeout'
        );

        $events->listen(
            sprintf("%s.%s",$this->name, 'success'),
            static::class.'@onSuccess'
        );
    }
}
