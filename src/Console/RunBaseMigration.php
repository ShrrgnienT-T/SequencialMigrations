<?php

namespace SequencialMigrations\Console;

use Illuminate\Console\Command;
use SequencialMigrations\BaseMigration;

class RunBaseMigration extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'migrate:base {direction=up}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Executa a BaseMigration e todas as migrations importadas nela';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $direction = $this->argument('direction');
        $base = new BaseMigration();

        if ($direction === 'down') {
            $this->info('Revertendo migrations🔻...');
            $base->down();
        } else {
            $this->info('Executando migrations🔼...');
            $base->up();
        }

        $this->info('Processo concluído!');
    }
}
