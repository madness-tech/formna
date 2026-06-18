<?php

require_once __DIR__ . '/src/core/env.php';
$config = load_config();

return [
    'paths' => [
        'migrations' => '%%PHINX_CONFIG_DIR%%/database/migrations',
        'seeds' => '%%PHINX_CONFIG_DIR%%/database/seeds'
    ],
    'environments' => [
        'default_migration_table' => 'phinx_migrations',
        'default_environment' => 'development',
        'development' => [
            'adapter' => 'mysql',
            'host' => $config['db']['host'],
            'name' => $config['db']['name'],
            'user' => $config['db']['user'],
            'pass' => $config['db']['password'],
            'port' => $config['db']['port'],
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ],
        'production' => [
            'adapter' => 'mysql',
            'host' => $config['db']['host'],
            'name' => $config['db']['name'],
            'user' => $config['db']['user'],
            'pass' => $config['db']['password'],
            'port' => $config['db']['port'],
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ]
    ],
    'version_order' => 'creation'
];
