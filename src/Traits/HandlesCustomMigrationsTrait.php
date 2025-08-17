<?php

namespace SequencialMigrations\Traits;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

trait HandlesCustomMigrationsTrait {
    public function runCustomMigrationsUp()
    {
        $relatorio = [
            'executadas' => 0,
            'puladas' => 0,
            'motivos' => [],
            'erros' => [],
        ];
        $logPath = database_path('migrations/migrations_log.txt');
        $log = [];
        $ordemSugerida = [];
        foreach ($this->migrations as $className) {
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

            if (is_object($migrationInstance) && method_exists($migrationInstance, 'up')) {
                $table = $this->guessTableName($migrationInstance, 'up');
                if ($table && Schema::hasTable($table)) {
                    $relatorio['puladas']++;
                    $relatorio['motivos'][] = "Pulada: $className (tabela '$table' já existe)";
                    $this->registerMigration($className, $file);
                    continue;
                }
                try {
                    $migrationInstance->up();
                    $this->registerMigration($className, $file);
                    $relatorio['executadas']++;
                } catch (\Illuminate\Database\QueryException $e) {
                    $relatorio['puladas']++;
                    $relatorio['motivos'][] = "Pulada: $className (erro de banco: " . $e->getMessage() . ")";
                    $relatorio['erros'][] = [
                        'migration' => $className,
                        'erro' => $e->getMessage(),
                        'arquivo' => $file,
                    ];
                    $log[] = "[ERRO] Migration: $className\nArquivo: $file\nErro: " . $e->getMessage() . "\n";
                    // Sugestão simples: se erro for de FK, sugere mover para depois da tabela referenciada
                    if (preg_match('/foreign key constraint.*references `(.*?)`/i', $e->getMessage(), $match)) {
                        $ordemSugerida[] = "Sugestão: mova $className após a migration que cria a tabela '{$match[1]}'";
                    }
                    continue;
                } catch (\Exception $e) {
                    $relatorio['puladas']++;
                    $relatorio['motivos'][] = "Pulada: $className (erro inesperado: " . $e->getMessage() . ")";
                    $relatorio['erros'][] = [
                        'migration' => $className,
                        'erro' => $e->getMessage(),
                        'arquivo' => $file,
                    ];
                    $log[] = "[ERRO] Migration: $className\nArquivo: $file\nErro: " . $e->getMessage() . "\n";
                    continue;
                }
            } else {
                $relatorio['puladas']++;
                $relatorio['motivos'][] = "Pulada: $className (classe não encontrada ou inválida)";
            }
        }
        // Escreve log detalhado se houver erros
        if (!empty($log)) {
            $logContent = "==== LOG DE ERROS DAS MIGRATIONS ====".PHP_EOL.implode(PHP_EOL, $log);
            if (!empty($ordemSugerida)) {
                $logContent .= PHP_EOL."\nSugestões de reordenação:".PHP_EOL.implode(PHP_EOL, $ordemSugerida);
            }
            file_put_contents($logPath, $logContent, FILE_APPEND);
        }
        // Exibe relatório
        echo "\n--- Relatório das migrations UP ---\n";
        echo "Executadas: {$relatorio['executadas']}\n";
        echo "Puladas: {$relatorio['puladas']}\n";
        if (!empty($relatorio['motivos'])) {
            echo "Motivos das puladas:\n";
            foreach ($relatorio['motivos'] as $motivo) {
                echo "- $motivo\n";
            }
        }
        if (!empty($ordemSugerida)) {
            echo "\nSugestões de reordenação:\n";
            foreach ($ordemSugerida as $sug) {
                echo "- $sug\n";
            }
        }
        echo "-------------------------------\n\n";
    }

    public function runCustomMigrationsDown()
    {
        $relatorio = [
            'executadas' => 0,
            'puladas' => 0,
            'motivos' => [],
        ];
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
                    $relatorio['puladas']++;
                    $relatorio['motivos'][] = "Pulada: $className (tabela '$table' não existe)";
                    $this->removeMigration($className, $file);
                    continue;
                }
                $migrationInstance->down();
                $this->removeMigration($className, $file);
                $relatorio['executadas']++;
            } else {
                $relatorio['puladas']++;
                $relatorio['motivos'][] = "Pulada: $className (classe não encontrada ou inválida)";
            }
        }
        // Exibe relatório
        echo "\n--- Relatório das migrations DOWN ---\n";
        echo "Executadas: {$relatorio['executadas']}\n";
        echo "Puladas: {$relatorio['puladas']}\n";
        if (!empty($relatorio['motivos'])) {
            echo "Motivos das puladas:\n";
            foreach ($relatorio['motivos'] as $motivo) {
                echo "- $motivo\n";
            }
        }
        echo "-------------------------------\n\n";
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
