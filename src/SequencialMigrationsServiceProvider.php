<?php

namespace SequencialMigrations;

use Illuminate\Support\ServiceProvider;

class SequencialMigrationsServiceProvider extends ServiceProvider
{
    public function register()
    {
        //
    }

    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                \SequencialMigrations\Console\RunBaseMigration::class,
            ]);
        }
    }
}
