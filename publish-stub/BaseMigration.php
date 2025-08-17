<?php

// Comandos úteis para manipular este arquivo:
//
// 1. Importar todas as migrations:
//    php artisan migrate:import-base all
//
// 2. Importar apenas as migrations pendentes:
//    php artisan migrate:import-base pending
//
// 3. Importar apenas as migrations já executadas:
//    php artisan migrate:import-base executed
//
// Ou insira as migrations manualmente no array $migrations abaixo para definir a ordem de execução.

use SequencialMigrations\BaseMigration;

class CustomBaseMigration extends BaseMigration
{
    protected array $migrations = [
        // Adicione aqui suas migrations
    ];
}
