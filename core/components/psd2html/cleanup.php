<?php
/**
 * PSD2HTML — автоочистка (Задача 3: «автоочистка файлов через N часов»).
 * Запуск по cron каждые ~30 минут.
 *
 * Удаляет:
 *  - папки results/<token>/ и исходники uploads/YY/MM/<rand>.psd с expires_at < NOW();
 *  - записи очереди старше 7 дней (done/error) и соответствующие строки истории;
 *  - устаревшие строки ratelimit (за вчера и раньше);
 *  - modx_psd2html_log старше 14 дней.
 */

declare(strict_types=1);

$root = dirname(__DIR__, 3);
$configFile = $root . '/core/config/config.inc.php';
if (!is_file($configFile)) { exit("нет config\n"); }
include $configFile;
$opts = $config['options'] ?? [];
$prefix = $opts['xpdo_table_prefix'] ?? 'modx_';

$pdo = new PDO(
    sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        $opts['xpdo_driver']['host'] ?? 'localhost',
        $opts['xpdo_driver']['port'] ?? '3306',
        $opts['xpdo_driver']['dbname'] ?? 'modx'),
    $opts['xpdo_driver']['user'] ?? '',
    $opts['xpdo_driver']['pass'] ?? '',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);

$settings = [];
foreach ($pdo->query("SELECT key_, value FROM `{$prefix}system_settings` WHERE key_ LIKE 'psd2html.%'") as $r) {
    $settings[substr($r['key_'], strlen('psd2html.'))] = $r['value'];
}
$storage = rtrim((string)($settings['storagePath'] ?? '/var/data/psd2html'), '/');

/** Рекурсивное удаление каталога с проверкой, что путь внутри хранилища. */
function rrmdir(string $dir): void
{
    foreach (scandir($dir) ?: [] as $e) {
        if ($e === '.' || $e === '..') { continue; }
        $p = $dir . '/' . $e;
        is_dir($p) ? rrmdir($p) : @unlink($p);
    }
    @rmdir($dir);
}

// --- 1. Протухшие результаты + исходники --------------------------------------------
$expired = $pdo->query(
    "SELECT id, token, stored_path FROM `{$prefix}psd2html_history` WHERE expires_at < NOW() LIMIT 500"
)->fetchAll();

$delHist = $pdo->prepare("DELETE FROM `{$prefix}psd2html_history` WHERE id=:id");
$delQueue = $pdo->prepare("DELETE FROM `{$prefix}psd2html_queue` WHERE conversion_id=:cid");

foreach ($expired as $row) {
    // Защита от path traversal: только ожидаемый формат путей
    $resDir = realpath($storage . '/results/' . $row['token']);
    if ($resDir !== false && str_starts_with($resDir, realpath($storage . '/results') ?: '')) {
        rrmdir($resDir);
    }
    $srcAbs = realpath(dirname($storage . '/' . $row['stored_path']) . '/' . basename($row['stored_path']));
    if ($srcAbs !== false && str_starts_with($srcAbs, realpath($storage . '/uploads') ?: '')) {
        @unlink($srcAbs);
    }
    $delQueue->execute([':cid' => $row['id']]);
    $delHist->execute([':id' => $row['id']]);
}

// --- 2. Остатки очереди без истории / старые логи / ratelimit ------------------------
$pdo->exec("DELETE FROM `{$prefix}psd2html_queue` WHERE created_at < NOW() - INTERVAL 7 DAY AND status IN ('done','error')");
$pdo->exec("DELETE FROM `{$prefix}psd2html_log` WHERE created_at < NOW() - INTERVAL 14 DAY");
$pdo->exec("DELETE FROM `{$prefix}psd2html_ratelimit` WHERE day < CURDATE() - INTERVAL 1 DAY");

fwrite(STDOUT, 'psd2html cleanup ok: ' . count($expired) . " записей\n");
