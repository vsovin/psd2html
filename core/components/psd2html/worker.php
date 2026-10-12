<?php
/**
 * PSD2HTML — cron-воркер очереди (Задача 3).
 *
 * Запуск: * * * * * php worker.php   (каждую минуту; одна копия за раз — flock)
 *
 * Логика одного прогона:
 *  1. Вернуть в queued «зависшие» processing (started_at старше PROCESS_TIMEOUT, attempts < MAX).
 *  2. Взять N задач queued (FIFO), атомарно пометить processing (защита от параллельных прогонов).
 *  3. Для каждой: Converter->convert(); успех -> done + заполнение history; ошибка -> error + лог.
 *  4. Изоляция: память и время на процесс ограничены; каждая задача в try/catch/finally.
 */

declare(strict_types=1);

// --- Одиночность запуска (flock) --------------------------------------------------
$lock = fopen(__DIR__ . '/worker.lock', 'c');
if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
    fwrite(STDOUT, "psd2html worker: уже запущен, выходим\n");
    exit(0);
}

require_once __DIR__ . '/Converter.php';

// --- Конфигурация из MODX (тот же путь, что API bootstrap) -------------------------
$root = dirname(__DIR__, 3);                       // /var/www/site
$configFile = $root . '/core/config/config.inc.php';
if (!is_file($configFile)) { fwrite(STDERR, "нет config.inc.php\n"); exit(1); }
include $configFile;
$opts = $config['options'] ?? [];
$prefix = $opts['xpdo_table_prefix'] ?? 'modx_';

try {
    $pdo = new PDO(
        sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            $opts['xpdo_driver']['host'] ?? 'localhost',
            $opts['xpdo_driver']['port'] ?? '3306',
            $opts['xpdo_driver']['dbname'] ?? 'modx'),
        $opts['xpdo_driver']['user'] ?? '',
        $opts['xpdo_driver']['pass'] ?? '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
} catch (PDOException $e) {
    fwrite(STDERR, 'DB: ' . $e->getMessage() . "\n");
    exit(1);
}

$settings = [];
foreach ($pdo->query("SELECT key_, value FROM `{$prefix}system_settings` WHERE key_ LIKE 'psd2html.%'") as $r) {
    $settings[substr($r['key_'], strlen('psd2html.'))] = $r['value'];
}
$storage = rtrim((string)($settings['storagePath'] ?? '/var/data/psd2html'), '/');
$maxAttempts = 2;                                   // попытки на задачу
$batch = 5;                                         // задач за прогон (остальное — следующий минутный тик)

// --- Ресурсы процесса: жёсткие лимиты, чтобы один битый PSD не убил воркер ----------
@ini_set('memory_limit', '512M');
set_time_limit(Converter::PROCESS_TIMEOUT * $batch + 30);

// 1. Requeue зависших -----------------------------------------------------------------
$pdo->prepare(
    "UPDATE `{$prefix}psd2html_queue`
     SET status='queued', attempts=attempts+1
     WHERE status='processing' AND started_at < (NOW() - INTERVAL :to SECOND) AND attempts < :ma"
)->execute([':to' => Converter::PROCESS_TIMEOUT, ':ma' => $maxAttempts]);
// Превысившие попытки — в error timeout
$pdo->prepare(
    "UPDATE `{$prefix}psd2html_queue`
     SET status='error', error_code='timeout', error_msg='превышено число попыток', finished_at=NOW()
     WHERE status='processing' AND started_at < (NOW() - INTERVAL :to SECOND)"
)->execute([':to' => Converter::PROCESS_TIMEOUT]);

// 2. Выборка queued FIFO с атомарным захватом ----------------------------------------
$rows = $pdo->query(
    "SELECT q.*, h.token, h.stored_path, h.orig_filename
     FROM `{$prefix}psd2html_queue` q
     JOIN `{$prefix}psd2html_history` h ON h.id = q.conversion_id
     WHERE q.status='queued' ORDER BY q.id ASC LIMIT {$batch}"
)->fetchAll();

$grab = $pdo->prepare(
    "UPDATE `{$prefix}psd2html_queue` SET status='processing', started_at=NOW()
     WHERE id=:id AND status='queued'"
);

$converter = new Converter($storage);

foreach ($rows as $row) {
    // Атомарный захват: если другой процесс уже забрал — пропускаем
    $grab->execute([':id' => $row['id']]);
    if ($grab->rowCount() === 0) { continue; }

    $ok = false;
    $code = null; $msg = null; $result = null;
    try {
        $result = $converter->convert([
            'token' => $row['token'],
            'stored_path' => $row['stored_path'],
            'orig_filename' => $row['orig_filename'],
        ]);
        $ok = true;
    } catch (ConversionException $e) {
        $code = $e->code; $msg = mb_substr($e->getMessage(), 0, 250);
    } catch (Throwable $e) {
        $code = 'internal'; $msg = mb_substr(get_class($e) . ': ' . $e->getMessage(), 0, 250);
        error_log('psd2html worker fatal: ' . $e->getMessage());
    } finally {
        // 3. Финализация статуса -------------------------------------------------------
        if ($ok && $result !== null) {
            $updH = $pdo->prepare(
                "UPDATE `{$prefix}psd2html_history`
                 SET zip_path=:zip, preview_path=:pv, layers_total=:lt, layers_used=:lu
                 WHERE id=:id"
            );
            $updH->execute([
                ':zip' => $result['zip_rel'], ':pv' => $result['preview_rel'],
                ':lt' => $result['layers_total'], ':lu' => $result['layers_used'],
                ':id' => $row['conversion_id'],
            ]);
            $pdo->prepare(
                "UPDATE `{$prefix}psd2html_queue` SET status='done', finished_at=NOW() WHERE id=:id"
            )->execute([':id' => $row['id']]);
        } else {
            $pdo->prepare(
                "UPDATE `{$prefix}psd2html_queue`
                 SET status='error', error_code=:c, error_msg=:m, finished_at=NOW() WHERE id=:id"
            )->execute([':id' => $row['id'], ':c' => $code, ':m' => $msg]);
            $pdo->prepare(
                "INSERT INTO `{$prefix}psd2html_log` (queue_id, level, message) VALUES (:q,'error',:m)"
            )->execute([':q' => $row['id'], ':m' => ($code ?: 'internal') . ': ' . $msg]);
        }
        // Освобождаем ресурсы Imagick между задачами
        if (class_exists('Imagick')) { Imagick::clearCache(); }
        gc_collect_cycles();
    }
}

fwrite(STDOUT, 'psd2html worker ok: ' . count($rows) . " задач(и)\n");
flock($lock, LOCK_UN);
fclose($lock);
