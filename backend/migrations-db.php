<?php

declare(strict_types=1);

use App\Db\ConnectionFactory;

require __DIR__ . '/vendor/autoload.php';

Dotenv\Dotenv::createImmutable(dirname(__DIR__))->safeLoad();

// For migrating the test DB: DB_NAME=fake_admin_test vendor/bin/doctrine-migrations ...
return ConnectionFactory::params();
