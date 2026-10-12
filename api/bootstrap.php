<?php
/**
 * PSD2HTML — общий bootstrap для API-эндпоинтов (Этап 1).
 *
 * Задача: дать /api/*.php доступ к PDO (через конфигурацию MODX) и system-настройкам
 * psd2html.* БЕЗ полной загрузки ядра MODX (легче и быстрее для Ajax).
 * Чтение config.core.php / config.inc.php MODX — штатный источник параметров БД.
 *
 * Безопасность: файл не отдаёт ничего лишнего, CORS закрыт, nosniff включён.
 */

declare(strict_types=1);

// --- HTTP-заголовки безопасности -------------------------------------------------
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header("Cache-Control: no-store");

if (!defined('P2H_BOOTSTRAPPED')) {
    define('P2H_BOOTSTRAPPED', true);

    /** Абсолютный путь к корню сайта (= .../public_html). */
    $root = dirname(__DIR__);
    define('P2H_ROOT', $root);

    // --- Параметры БД из конфига MODX ---------------------------------------------
    // config.inc.php определяет $config['options'] (xPDO), ключи prefixed mysql.*
    $modxConfigFile = $root . '/core/config/config.inc.php';
    if (!is_file($modxConfigFile)) {
        p2h_fail('server', 'Сервер временно недоступен', 503);
    }
    /** @var array $config */
    include $modxConfigFile;
    $opts = $config['options'] ?? [];

    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        $opts['xpdo_driver']['host'] ?? 'localhost',
        $opts['xpdo_driver']['port'] ?? '3306',
        $opts['xpdo_driver']['dbname'] ?? 'modx'
    );

    try {
        $pdo = new PDO($dsn, $opts['xpdo_driver']['user'] ?? '', $opts['xpdo_driver']['pass'] ?? '', [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // ошибки — исключения
            PDO::ATTR_EMULATE_PREPARES   => false,                  // нативные плейсхолдеры
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    } catch (PDOException $e) {
        // Клиенту — только код; подробности — в лог error_log
        error_log('psd2html DB: ' . $e->getMessage());
        p2h_fail('server', 'Сервер временно недоступен', 503);
    }

    /** Префикс таблиц MODX (из того же конфига). */
    define('P2H_PREFIX', $opts['xpdo_table_prefix'] ?? 'modx_');

    // --- System settings psd2html.* из таблицы modx_system_settings ---------------
    // Кэшируем в APCu/apc_fetch при наличии; иначе — один запрос за всем префиксом.
    $settings = [];
    $stmt = $pdo->prepare('SELECT key_, value FROM `' . P2H_PREFIX . 'system_settings` WHERE key_ LIKE :p');
    $stmt->execute([':p' => 'psd2html.%']);
    foreach ($stmt->fetchAll() as $row) {
        $settings[substr($row['key_'], strlen('psd2html.'))] = $row['value'];
    }

    /** Значение настройки с дефолтом. */
    function p2h_setting(string $key, mixed $default = null): mixed
    {
        global $settings;
        return isset($settings[$key]) && $settings[$key] !== '' ? $settings[$key] : $default;
    }

    // --- Хранилище ВНЕ docroot ------------------------------------------------------
    // Все пути файлов — относительные; абсолютные собираем от storagePath.
    $storage = rtrim((string)p2h_setting('storagePath', '/var/data/psd2html'), '/');
    define('P2H_STORAGE', $storage);
    define('P2H_UPLOADS', $storage . '/uploads');
    define('P2H_RESULTS', $storage . '/results');

    // --- Сессия (для session_key rate-limit и CSRF) ---------------------------------
    // Идентификатор берём из cookie PHPSESSID без создания новой сессии на GET-поллинге.
    $sid = $_COOKIE['PHPSESSID'] ?? '';
    define('P2H_SESSION_ID', is_string($sid) ? $sid : '');

    // --- CSRF: HMAC(secret, session_id + '|psd2html') -------------------------------
    // Простая схема без stateful-сессий на сервере приложений под балансировщиком.
    function p2h_csrf_token(): string
    {
        $secret = (string)p2h_setting('secret', '');
        if ($secret === '') {
            throw new RuntimeException('psd2html.secret not configured');
        }
        return hash_hmac('sha256', P2H_SESSION_ID . '|psd2html-upload', $secret);
    }

    function p2h_csrf_check(?string $token): void
    {
        if (!is_string($token) || !hash_equals(p2h_csrf_token(), $token)) {
            p2h_fail('csrf', 'Сессия устарела, обновите страницу', 403);
        }
    }

    // --- JSON-ответы -----------------------------------------------------------------
    function p2h_json(mixed $data, int $code = 200): never
    {
        http_response_code($code);
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /** Единый формат ошибки: code (машинный) + message (человекочитаемый, RU). */
    function p2h_fail(string $code, string $message, int $http = 400): never
    {
        p2h_json(['ok' => false, 'error' => ['code' => $code, 'message' => $message]], $http);
    }

    /** Нормализованный IP: IPv6 анонимизируется до /56 (защита приватности + стабильный bucket). */
    function p2h_client_ip(): string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $packed = inet_pton($ip);
            // обнуляем младшие 72 бита (/56)
            $packed = substr($packed, 0, 7) . str_repeat("\0", 9);
            return inet_ntop($packed);
        }
        return $ip;
    }
}
