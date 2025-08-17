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

            // Publica o stub BaseMigration.php para database/migrations
            $this->publishes([
                __DIR__.'/../publish-stub/BaseMigration.php' => database_path('migrations/BaseMigration.php'),
            ], 'sequencial-migrations-base');
        }
    }
}
