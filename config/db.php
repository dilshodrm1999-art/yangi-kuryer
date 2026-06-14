<?php
/**
 * Ma'lumotlar bazasiga ulanish (PDO / MySQL)
 *
 * XAVFSIZLIK: maxfiy ma'lumotlarni (parol) to'g'ridan-to'g'ri shu faylga
 * yozmang. Eng yaxshi yo'l — config/local.php fayli (u .gitignore'da,
 * git'ga tushmaydi). Agar u mavjud bo'lsa, undagi qiymatlar ishlatiladi.
 *
 * config/local.php namunasi:
 *   <?php
 *   return [
 *     'host' => 'sql310.infinityfree.com',
 *     'port' => '3306',
 *     'name' => 'if0_42103004_kuryer',
 *     'user' => 'if0_42103004',
 *     'pass' => 'YANGI_PAROL',
 *   ];
 */

$localCfg = [];
if (is_file(__DIR__ . '/local.php')) {
    $localCfg = require __DIR__ . '/local.php';
    if (!is_array($localCfg)) { $localCfg = []; }
}

define('DB_HOST', getenv('DB_HOST') ?: ($localCfg['host'] ?? 'sql310.infinityfree.com'));
define('DB_PORT', getenv('DB_PORT') ?: ($localCfg['port'] ?? '3306'));
define('DB_NAME', getenv('DB_NAME') ?: ($localCfg['name'] ?? 'if0_42103004_kuryer'));
define('DB_USER', getenv('DB_USER') ?: ($localCfg['user'] ?? 'if0_42103004'));
define('DB_PASS', getenv('DB_PASS') ?: ($localCfg['pass'] ?? ''));

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
