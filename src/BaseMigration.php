<?php

namespace SequencialMigrations;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use SequencialMigrations\Traits\HandlesCustomMigrationsTrait;

class BaseMigration extends Migration
{
    /**
     * Lista de migrations a serem executadas em ordem.
     * Use apenas o nome da classe (sem namespace).
     */
    protected array $migrations = [
        // Migrations padrão Laravel (classes nomeadas)
        
    ];

    use HandlesCustomMigrationsTrait;

    public function up()
    {
        $this->runCustomMigrationsUp();
    }

    public function down()
    {
        $this->runCustomMigrationsDown();
    }
}
