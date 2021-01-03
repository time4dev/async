<?php

namespace Time4dev\Async\Commands;

use Illuminate\Console\GeneratorCommand;

class JobMakeCommand extends GeneratorCommand
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'make:async-job';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new async job class';

    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'Async job';

    /**
     * Get the stub file for the generator.
     *
     * @return string
     */
    protected function getStub()
    {
        return __DIR__.'/stubs/job.stub';
    }

    /**
     * Get the default namespace for the class.
     *
     * @param string $rootNamespace
     * @return string
     */
    protected function getDefaultNamespace($rootNamespace)
    {
        return $rootNamespace.'\AsyncJobs';
    }
}
