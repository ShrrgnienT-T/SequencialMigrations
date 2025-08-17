<?php

namespace SequencialMigrations\Traits;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

trait HandlesCustomMigrationsTrait {
    public function runCustomMigrationsUp()
    {
        foreach ($this->migrations as $className) {
            $file = $this->getMigrationFilePath($className);
            $migrationInstance = null;

            if ($file) {
                if (class_exists($className)) {
                    // Migration nomeada: instancia normalmente
                    $migrationInstance = new $className();
                } else {
                    // Migration anônima: inclui o arquivo e espera um objeto
                    $ret = include $file;
                    if (is_object($ret)) {
                        $migrationInstance = $ret;
                    }
                }
            }

            if (is_object($migrationInstance) && method_exists($migrationInstance, 'up')) {
                $table = $this->guessTableName($migrationInstance, 'up');
                if ($table && Schema::hasTable($table)) {
                    echo "Tabela '$table' já existe. Pulando migration $className.\n";
                    $this->registerMigration($className, $file);
                    continue;
                }
                try {
                    $migrationInstance->up();
                    $this->registerMigration($className, $file);
                } catch (\Illuminate\Database\QueryException $e) {
                    if (str_contains($e->getMessage(), 'already exists')) {
                        echo "⚠️  Tabela já existe ao rodar $className. Pulando esta migration! 😅\n";
                        $this->registerMigration($className, $file);
                        continue;
                    }
                    throw $e;
                }
            } else {
                echo "Migration class $className não encontrada ou inválida!\n";
            }
        }
    }

    public function runCustomMigrationsDown()
    {
        foreach (array_reverse($this->migrations) as $className) {
            $file = $this->getMigrationFilePath($className);
            $migrationInstance = null;

            if ($file) {
                if (class_exists($className)) {
                    $migrationInstance = new $className();
                } else {
                    $ret = include $file;
                    if (is_object($ret)) {
                        $migrationInstance = $ret;
                    }
                }
            }

            if (is_object($migrationInstance) && method_exists($migrationInstance, 'down')) {
                $table = $this->guessTableName($migrationInstance, 'down');
                if ($table && !Schema::hasTable($table)) {
                    echo "Tabela '$table' não existe. Pulando down da migration $className.\n";
                    $this->removeMigration($className, $file);
                    continue;
                }
                $migrationInstance->down();
                $this->removeMigration($className, $file);
            } else {
                echo "Migration class $className não encontrada ou inválida!\n";
            }
        }
    }

    protected function getMigrationFilePath(string $className): string
    {
        $dir = database_path('migrations');
        $files = glob($dir . '/*_' . strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $className)) . '.php');
        if (empty($files)) {
            $files = glob($dir . '/*' . $className . '.php');
        }
        return $files[0] ?? '';
    }

    protected function registerMigration($className, $file)
    {
        $migration = $this->getMigrationNameFromFile($className, $file);
        if (!DB::table('migrations')->where('migration', $migration)->exists()) {
            DB::table('migrations')->insert([
                'migration' => $migration,
                'batch' => $this->getNextBatchNumber(),
            ]);
        }
    }

    protected function removeMigration($className, $file)
    {
        $migration = $this->getMigrationNameFromFile($className, $file);
        DB::table('migrations')->where('migration', $migration)->delete();
    }

    protected function getMigrationNameFromFile($className, $file)
    {
        if ($file) {
            return pathinfo($file, PATHINFO_FILENAME);
        }
        return $className;
    }

    protected function getNextBatchNumber()
    {
        $batch = DB::table('migrations')->max('batch');
        return $batch ? $batch + 1 : 1;
    }

    protected function guessTableName($instance, $direction = 'up')
    {
        if (!is_object($instance)) {
            return null;
        }
        if (property_exists($instance, 'table')) {
            return $instance->table;
        }
        if (method_exists($instance, 'getTableName')) {
            return $instance->getTableName();
        }
        $reflection = new \ReflectionClass($instance);
        if ($reflection->hasMethod('up')) {
            $method = $reflection->getMethod('up');
            $file = $method->getFileName();
            $start = $method->getStartLine();
            $end = $method->getEndLine();
            $lines = file($file);
            $body = implode("\n", array_slice($lines, $start - 1, $end - $start + 1));
            if (preg_match("/Schema::create\\(['\"]([a-zA-Z0-9_]+)['\"]/", $body, $match)) {
                return $match[1];
            }
        }
        $class = get_class($instance);
        if (preg_match('/Create([A-Za-z0-9]+)Table/', $class, $matches)) {
            return Str::snake($matches[1]);
        }
        return null;
    }
}
