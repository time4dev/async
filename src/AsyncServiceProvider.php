<?php

namespace Time4dev\Async;

use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;
use Time4dev\Async\Commands\JobMakeCommand;
use Time4dev\Async\Commands\WorkerCommand;

class AsyncServiceProvider extends BaseServiceProvider implements DeferrableProvider
{
    /**
     * Package boot.
     */
    public function boot(): void
    {
        $this->publishConfigs();
    }

    /**
     * Publish async config files.
     */
    protected function publishConfigs(): void
    {
        $this->publishes([
            __DIR__.'/../config/async.php' => config_path('async.php'),
        ], 'config');
    }

    /**
     * {@inheritdoc}
     */
    public function register(): void
    {
        $this->mergeDefaultConfigs();
        $this->registerServices();
        $this->registerCommands();
    }

    /**
     * Merge default async config to config service.
     */
    protected function mergeDefaultConfigs(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/async.php', 'async');
    }

    /**
     * Register package services.
     */
    protected function registerServices(): void
    {
        $this->app->singleton('command.async.make', JobMakeCommand::class);
        $this->app->singleton('command.async.worker', WorkerCommand::class);
    }

    /**
     * Register package commands.
     */
    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands(['command.async.make']);
            $this->commands(['command.async.worker']);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function provides(): array
    {
        return ['command.async.make', 'command.async.worker'];
    }
}
