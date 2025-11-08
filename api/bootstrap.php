<?php

require_once __DIR__ . '\vendor\autoload.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

use Doctrine\DBAL\DriverManager;

// Optional: Manual autoloader (if needed for legacy classes)
spl_autoload_register(function($class) {
    $classPath = str_replace('\\', '/', $class) . '.php';
    $paths = [
        __DIR__ . '/vendor/doctrine/dbal/src/' . $classPath,
        __DIR__ . '/vendor/doctrine/common/src/' . $classPath,
    ];
    foreach ($paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            return true;
        }
    }
    return false;
});

// Database connection parameters
$connectionParams = [
    'driver'   => 'pdo_pgsql',
    'host'     => 'localhost',
    'port'     => '5432',
    'dbname'   => 'dummy',
    'user'     => 'postgres',
    'password' => '1234',
];

try {
    // Create Doctrine DBAL connection
    $conn = DriverManager::getConnection($connectionParams);

    // Optional: Set schema search path
    $conn->executeStatement("SET search_path TO _10009_1pl");

} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
    echo "Error in file: " . $e->getFile() . " on line " . $e->getLine() . "<br>";
    exit(1);
}
