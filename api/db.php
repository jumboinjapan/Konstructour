<?php
// api/db.php
function konstructour_db(): PDO {
    // Абсолютный путь — исключаем ситуацию, когда cron и web пишут в разные файлы
    $dbPath = __DIR__ . '/konstructour.db';

    // Гарантируем, что директория существует и доступна
    $dir = dirname($dbPath);
    if (!is_dir($dir)) {
        throw new RuntimeException("DB dir missing: $dir");
    }
    if (!is_writable($dir)) {
        throw new RuntimeException("DB dir not writable: $dir");
    }

    $pdo = new PDO('sqlite:' . $dbPath, null, null, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // ошибки = исключения
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);

    // Журналирование и целостность
    $pdo->exec("PRAGMA journal_mode=WAL;");
    $pdo->exec("PRAGMA synchronous=NORMAL;");
    $pdo->exec("PRAGMA foreign_keys=ON;"); // Важно: включается на КАЖДОМ соединении

    return $pdo;
}
