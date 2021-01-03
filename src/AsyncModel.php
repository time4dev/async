<?php


namespace Time4dev\Async;


use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Time4dev\Async\Commands\WorkerCommand;
use Time4dev\Async\Process\Runnable;
use Time4dev\Async\Runtime\ParentRuntime;

/**
 * App\Models\AsyncModel
 *
 * @property int $id
 * @property int $pid
 * @property string $name
 * @property string $status
 * @property string $payload
 * @property string $started_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder|AsyncModel newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|AsyncModel newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|AsyncModel query()
 * @method static \Illuminate\Database\Eloquent\Builder|AsyncModel whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|AsyncModel whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|AsyncModel whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|AsyncModel wherePayload($value)
 * @method static \Illuminate\Database\Eloquent\Builder|AsyncModel wherePid($value)
 * @method static \Illuminate\Database\Eloquent\Builder|AsyncModel whereStartedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|AsyncModel whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder|AsyncModel whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class AsyncModel extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'async';

    /**
     * The "type" of the auto-incrementing ID.
     *
     * @var string
     */
    protected $keyType = 'integer';

    /**
     * @var array
     */
    protected $fillable = ['pid', 'status', 'name', 'payload', 'started_at', 'created_at', 'updated_at'];

    /**
     * @param $job
     * @return Runnable
     */
    public static function run($job)
    {
        return self::add(self::makeJob($job));
    }

    /**
     * @param mixed ...$jobs
     * @return array
     */
    public function batchRun(...$jobs): array
    {
        $processList = [];
        foreach ($jobs as $k => $job) {
            $processList[$k] = self::run($job);
        }

        return $processList;
    }

    /**
     * Make async job.
     *
     * @param $job
     *
     * @return mixed
     */
    protected static function makeJob($job)
    {
        if (is_string($job)) {
            return self::createClassJob($job);
        }

        return $job;
    }

    /**
     * @param string $job
     * @return \Closure
     */
    protected static function createClassJob(string $job): \Closure
    {
        [$class, $method] = Str::parseCallback($job, 'handle');

        return function () use ($class, $method) {
            return app()->call($class.'@'.$method);
        };
    }

    /**
     * @param Runnable|callable $process
     * @param int|null $outputLength
     *
     * @return Runnable
     */
    public static function add($process, ?int $outputLength = null)
    {
        if (!is_callable($process) && ! $process instanceof Runnable) {
            throw new InvalidArgumentException('The process passed to Pool::add should be callable.');
        }

        self::putInQueue($process);
        return $process;
    }

    public static function putInQueue($process)
    {
        if ($name = $name = method_exists($process, 'dbName')) {
            $name = $process->dbName();
        }

        if (!$name) {
            $name = get_class($process);
        }

        return AsyncModel::create([
            'name' => $name,
            'status' => WorkerCommand::STATUS_QUEUED,
            'payload' => ParentRuntime::encodeTask($process)
        ]);
    }
}

