<?php
/**
 * Ma'lumotlar bazasiga ulanish (PDO / MySQL)
 * Sozlamalarni o'z serveringizga moslang.
 */

define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: 'dostavka');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        DB_HOST, DB_PORT, DB_NAME
    );

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    } catch (PDOException $e) {
        // Xato tafsilotlarini foydalanuvchiga ko'rsatmaymiz (ma'lumot sizib chiqmasligi uchun)
        error_log('DB connection error: ' . $e->getMessage());
        http_response_code(500);
        if (PHP_SAPI !== 'cli'
            && str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'error' => 'server_error']);
        } else {
            die('Server xatosi. Iltimos keyinroq urinib ko\'ring.');
        }
        exit;
    }

    return $pdo;
}
