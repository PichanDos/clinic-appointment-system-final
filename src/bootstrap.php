<?php
declare(strict_types=1);

session_start();

$config = file_exists(__DIR__ . '/../config.local.php')
    ? require __DIR__ . '/../config.local.php'
    : require __DIR__ . '/../config.example.php';
date_default_timezone_set($config['timezone']);

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/repository.php';

function db(): PDO
{
    static $pdo = null;
    global $config;
    if ($pdo === null) {
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $config['host'], $config['port'], $config['database']);
        $pdo = new PDO($dsn, $config['username'], $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }
    return $pdo;
}

