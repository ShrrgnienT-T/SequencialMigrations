<?php

namespace SequencialMigrations;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use SequencialMigrations\Traits\HandlesCustomMigrationsTrait;

class BaseMigration extends Migration
{
    protected array $migrations = [];

    use HandlesCustomMigrationsTrait;

    public function __construct()
    {
        $file = database_path('migrations/BaseMigration.php');
        if (file_exists($file)) {
            $migrations = include $file;
            if (is_array($migrations)) {
                $this->migrations = $migrations;
            }
        }
    // Não chama parent::__construct() pois Migration não possui construtor
    }

    public function up()
    {
        $this->runCustomMigrationsUp();
    }

    public function down()
    {
        $this->runCustomMigrationsDown();
    }
}
