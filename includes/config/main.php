<?php

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

// Constants
define('PROJECT_ROOT', dirname(__DIR__));

// Load value from .env file
if (file_exists(PROJECT_ROOT . '/.env')) {
  $lines = file(PROJECT_ROOT . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
  foreach ($lines as $line) {
    if (strpos(trim($line), '#') === 0) continue;
    [$key, $value] = explode('=', $line, 2);
    $_ENV[$key] = trim($value);
  }
}

// Database configuration
$host    = getenv('DB_HOST') ?: 'localhost';
$dbname  = getenv('DB_NAME') ?: 'bnsp_jwp3';
$user    = getenv('DB_USER') ?: 'root';
$pass    = getenv('DB_PASS') ?: 'password';

try {
    // Create PDO connection
    $pdo = new \PDO("mysql:host={$host};dbname={$dbname};charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
    $pdo->exec('SET NAMES utf8mb4');
} catch (\PDOException $e) {
    throw new RuntimeException("Database connection failed: {$e->getMessage()}", $e->getCode(), $e);
}
