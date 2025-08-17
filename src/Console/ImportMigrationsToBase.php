<?php

namespace SequencialMigrations\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class ImportMigrationsToBase extends Command
{
    protected $signature = 'migrate:import-base {type=all : Tipo de importação (all|pending|executed)}';
    protected $description = 'Importa migrations (nomeadas e anônimas) para o arquivo BaseMigration.php';

    public function handle()
    {
        $type = $this->argument('type');
        $migrationsPath = database_path('migrations');
        $baseFile = $migrationsPath . '/BaseMigration.php';

        // 1. Listar todos os arquivos de migration
        $files = collect(File::files($migrationsPath))
            ->filter(fn($f) => $f->getExtension() === 'php' && $f->getFilename() !== 'BaseMigration.php')
            ->map(fn($f) => $f->getFilenameWithoutExtension())
            ->values();

        // 2. Listar migrations já executadas
        $executed = collect(DB::table('migrations')->pluck('migration'));

        // 3. Determinar lista final
        if ($type === 'pending') {
            $list = $files->diff($executed)->values();
        } elseif ($type === 'executed') {
            $list = $files->intersect($executed)->values();
        } else {
            $list = $files;
        }

        // 4. Gerar conteúdo do array para o novo padrão (return array)
        $array = $list->map(fn($name) => "    '".$name."',")->implode("\n");

        $content = <<<PHP
    <?php
    // Edite este array para definir a ordem das suas migrations customizadas.
    // Este arquivo é gerado automaticamente pelo comando migrate:import-base
    return [
    {$array}
    ];
    PHP;
        File::put($baseFile, $content);

        $this->info("Arquivo BaseMigration.php atualizado com as migrations do tipo: $type");
    }
}
