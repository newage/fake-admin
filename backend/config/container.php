<?php

declare(strict_types=1);

use Laminas\ServiceManager\ServiceManager;

// Environment variables: in Docker they are already set, the .env at the repo root is for running from the host
Dotenv\Dotenv::createImmutable(dirname(__DIR__, 2))->safeLoad();

// Load configuration
$config = require __DIR__ . '/config.php';

$dependencies                       = $config['dependencies'];
$dependencies['services']['config'] = $config;

// Build container
return new ServiceManager($dependencies);
