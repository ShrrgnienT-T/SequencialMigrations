<?php

namespace SequencialMigrations\Traits;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

trait HandlesCustomMigrationsTrait {
    public function runCustomMigrationsUp()
    {
        $executedCount = 0;
        $skippedCount = 0;
        $executedList = [];
        $skippedList = [];

        // Detecta se está rodando via Artisan (para usar cores)
        $isArtisan = defined('ARTISAN_BINARY') || (php_sapi_name() === 'cli' && isset($_SERVER['argv'][0]) && str_contains($_SERVER['argv'][0], 'artisan'));
        $out = function($text, $color = null) use ($isArtisan) {
            if ($isArtisan && $color) {
                // Cores ANSI
                $colors = [
                    'green' => "\033[32m",
                    'yellow' => "\033[33m",
                    'red' => "\033[31m",
                    'cyan' => "\033[36m",
                    'magenta' => "\033[35m",
                    'gray' => "\033[90m",
                    'reset' => "\033[0m",
                ];
                $c = $colors[$color] ?? '';
                $reset = $colors['reset'];
                echo "$c$text$reset";
            } else {
                echo $text;
            }
        };

        foreach ($this->migrations as $migration) {
            $migrationInstance = $this->resolveMigrationInstance($migration);
            if (!$migrationInstance) {
                $skippedCount++;
                $skippedList[] = [
                    'migration' => $migration,
                    'reason' => 'classe não encontrada ou inválida',
                    'type' => 'error',
                ];
                continue;
            }

            $table = $this->getMigrationTableName($migrationInstance);
            if ($table && Schema::hasTable($table)) {
                $skippedCount++;
                $skippedList[] = [
                    'migration' => $migration,
                    'reason' => "tabela '$table' já existe",
                    'type' => 'info',
                ];
                continue;
            }

            try {
                $migrationInstance->up();
                $executedCount++;
                $executedList[] = $migration;
            } catch (\Illuminate\Database\QueryException $e) {
                $skippedCount++;
                $skippedList[] = [
                    'migration' => $migration,
                    'reason' => 'erro de banco: ' . $e->getMessage(),
                    'type' => 'error',
                ];
                $this->logMigrationError($migration, $e);
                $this->suggestReorderingIfForeignKey($migration, $e);
            } catch (\Exception $e) {
                $skippedCount++;
                $skippedList[] = [
                    'migration' => $migration,
                    'reason' => 'erro: ' . $e->getMessage(),
                    'type' => 'error',
                ];
                $this->logMigrationError($migration, $e);
            }
        }

        $divider = str_repeat('=', 60);
        $section = function($title, $color = 'cyan') use ($out, $divider) {
            $out("\n$divider\n", 'gray');
            $out("$title\n", $color);
            $out("$divider\n", 'gray');
        };

        $section('RELATÓRIO DAS MIGRATIONS UP', 'magenta');
        $out("  ", null);
        $out("Executadas: ", 'green');
        $out("$executedCount\n", 'green');
        $out("Puladas: ", 'yellow');
        $out("$skippedCount\n", 'yellow');

        if ($executedList) {
            $section('MIGRATIONS EXECUTADAS', 'green');
            foreach ($executedList as $mig) {
                $out("  ✔ $mig\n", 'green');
            }
        }

        if ($skippedList) {
            $section('MIGRATIONS PULADAS', 'yellow');
            foreach ($skippedList as $skip) {
                $icon = $skip['type'] === 'error' ? '✖' : '➔';
                $color = $skip['type'] === 'error' ? 'red' : 'yellow';
                $out("  $icon {$skip['migration']} ", $color);
                $out("({$skip['reason']})\n", 'gray');
            }
        }

        $out("\n", null);
        $out($divider . "\n", 'gray');
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
                $table = $this->guessTableName($migrationInstance);
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

    /**
     * Instancia uma migration pelo nome da classe ou pelo nome do arquivo (anônimo).
     */
    protected function resolveMigrationInstance(string $migration)
    {
        // Se for uma classe existente, instancia normalmente
        if (class_exists($migration)) {
            return new $migration();
        }

        // Tenta localizar o arquivo na pasta migrations
        $file = database_path('migrations/' . $migration . '.php');
        if (file_exists($file)) {
            $ret = include $file;
            // Se o arquivo retorna um objeto (migration anônima)
            if (is_object($ret)) {
                return $ret;
            }
        }

        // Não encontrado
        return null;
    }
}
