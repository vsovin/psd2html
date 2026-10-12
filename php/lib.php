<?php
/**
 * Ядро PHP-бэкенда VirshkeTech: хранилище, санитайзеры, валидация, авторизация.
 * Порт серверной логики server.js (Express) под обычный виртуальный хостинг
 * без Node.js. Только stdlib PHP >= 7.4 (json, mbstring не требуется).
 */

declare(strict_types=1);

// --- polyfill mb_* для хостингов без расширения mbstring ------------------
// Shared-хостинг почти всегда даёт mbstring, но страховка копеечная.
if (!function_exists('mb_substr')) {
    function mb_substr(string $s, int $start, ?int $length = null): string
    {
        if (function_exists('iconv')) {
            $r = $length === null ? @iconv_substr($s, $start, PHP_INT_MAX, 'UTF-8') : @iconv_substr($s, $start, $length, 'UTF-8');
            return $r === false ? '' : $r;
        }
        return $length === null ? substr($s, $start) : substr($s, $start, $length);
    }
}
if (!function_exists('mb_strlen')) {
    function mb_strlen(string $s): int
    {
        if (function_exists('iconv')) {
            return (int)@iconv_strlen($s, 'UTF-8');
        }
        return strlen($s);
    }
}

// --- Загрузка конфигурации ------------------------------------------------

function config(): array
{
    static $cfg = null;
    if ($cfg === null) {
        $base = require __DIR__ . '/config.php';
        // На shared-хостинге php/ может лежать вне веб-рута — тогда путь
        // к data.json задаётся абсолютным в config.sample.php.
        foreach (['data_file', 'orders_dir', 'sessions_dir'] as $key) {
            $dir = dirname($base[$key]);
            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
        }
        $cfg = $base;
    }
    return $cfg;
}

// --- HTTP-ответы ----------------------------------------------------------

function json_out(int $status, array $payload): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function read_json_body(): array
{
    $raw = file_get_contents('php://input');
    if (strlen($raw) > 256 * 1024) {
        json_out(413, ['ok' => false, 'error' => 'Слишком большой запрос.']);
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

// --- Хранилище контента (data.json) ---------------------------------------

function default_data(): array
{
    return [
        'site' => ['phone' => '', 'email' => '', 'address' => '', 'social' => []],
        'pages' => new stdClass(),
        'products' => [],
        'filters' => [],
        'calculator' => ['baseOptions' => [], 'modules' => []],
    ];
}

function deep_merge(array $defaults, $value)
{
    if (!is_array($value)) {
        return $value === null ? $defaults : $value;
    }
    if (!is_array($defaults)) {
        return $value;
    }
    $out = $defaults;
    foreach ($value as $k => $v) {
        $out[$k] = array_key_exists($k, $defaults) && is_array($defaults[$k]) && is_array($v)
            ? deep_merge($defaults[$k], $v)
            : $v;
    }
    return $out;
}

function read_data(): array
{
    $file = config()['data_file'];
    if (!is_file($file)) {
        return default_data();
    }
    $parsed = json_decode((string)file_get_contents($file), true);
    if (!is_array($parsed)) {
        return default_data();
    }
    return deep_merge(default_data(), $parsed);
}

/** Атомарная запись: temp-file -> бэкап -> rename. Конкуренция между
 *  PHP-процессами закрывается эксклюзивным lock-файлом. */
function write_data(array $data): void
{
    $file = config()['data_file'];
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    if (strlen($json) > 4 * 1024 * 1024) {
        throw new RuntimeException('data.json превышает 4 МБ — выгрузите и очистите заявки.');
    }

    $lock = $file . '.lock';
    $fp = @fopen($lock, 'c');
    $got = false;
    if ($fp && flock($fp, LOCK_EX, $got)) {
        try {
            write_data_unlocked($file, $json);
        } finally {
            flock($fp, LOCK_UN);
            fclose($fp);
        }
        return;
    }
    // lock недоступен (некоторые FS на shared-хостинге) — пишем как есть
    write_data_unlocked($file, $json);
}

function write_data_unlocked(string $file, string $json): void
{
    $tmp = $file . '.tmp';
    if (file_put_contents($tmp, $json, LOCK_EX) === false) {
        throw new RuntimeException('не удалось выполнить запись файла ' . $tmp);
    }
    if (is_file($file)) {
        @copy($file, $file . '.bak');
    }
    if (!@rename($tmp, $file)) {
        // fallback для FS, где rename через границы прав запрещён
        file_put_contents($file, $json, LOCK_EX);
        @unlink($tmp);
    }
}

// --- Заявки: по одному JSON-файлу на заявку -------------------------------
// На shared-хостинге конкурентная запись одного data.json опасна (нет flock
// на части ФС, нет long-running процесса), поэтому заявки хранятся файлами.

function orders_dir(): string
{
    return config()['orders_dir'];
}

function load_orders(): array
{
    $dir = orders_dir();
    $orders = [];
    foreach (glob($dir . '/*.json') ?: [] as $path) {
        $o = json_decode((string)file_get_contents($path), true);
        if (is_array($o) && isset($o['id'])) {
            $o['__file'] = $path;
            $orders[] = $o;
        }
    }
    usort($orders, static fn($a, $b) => (int)$a['id'] <=> (int)$b['id']);
    return $orders;
}

function next_order_id(): int
{
    $max = 0;
    foreach (glob(orders_dir() . '/*.json') ?: [] as $path) {
        $name = basename($path, '.json');
        if (ctype_digit($name)) {
            $max = max($max, (int)$name);
        }
    }
    return $max + 1;
}

function save_order(array $order): void
{
    $path = rtrim(orders_dir(), '/') . '/' . sprintf('%09d', (int)$order['id']) . '.json';
    unset($order['__file'], $order['errors']);
    file_put_contents($path, json_encode($order, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
}

function find_order(int $id): ?array
{
    foreach (load_orders() as $o) {
        if ((int)$o['id'] === $id) {
            return $o;
        }
    }
    return null;
}

// --- Санитайзеры (граница «админка -> файл») -------------------------------

function clean_line($v, int $max = 300): string
{
    $s = preg_replace('/\s+/u', ' ', trim((string)$v));
    return mb_substr($s, 0, $max);
}

function clean_text($v, int $max = 4000): string
{
    return mb_substr(trim((string)$v), 0, $max);
}

function round_price($n): int
{
    return (int)round((float)$n);
}

const DANGEROUS_SCHEME_RE = '/^\s*(javascript|data|vbscript|file|blob)\s*:/i';
const URL_VALUE_RE        = '/^(https?:|tel:|mailto:|\/\/)/i';

function looks_like_url_value($s): bool
{
    return (bool)preg_match(URL_VALUE_RE, trim((string)$s));
}

function looks_like_url_key(string $key): bool
{
    return (bool)preg_match('/(^|_)(url|image|img|link|href|logo)$/i', $key)
        || (bool)preg_match('/(url|image|img|link|href|social)/i', $key);
}

function sanitize_scalar($value, string $key, int $max = 4000): string
{
    $s = (string)($value ?? '');
    // опасные схемы режем всегда, даже если значение не выглядит как URL
    if (preg_match(DANGEROUS_SCHEME_RE, $s)) {
        return '';
    }
    $isUrlField = looks_like_url_key($key) || looks_like_url_value($s);
    $t = trim($s);
    if ($isUrlField) {
        if ($t !== '' && !looks_like_url_value($t) && ($t[0] ?? '') !== '/') {
            return ''; // не относительный путь и не разрешённая схема
        }
    }
    return mb_substr($t, 0, $max);
}

function sanitize_pages_node($node, string $key)
{
    if (is_array($node)) {
        $isList = array_keys($node) === range(0, count($node) - 1);
        $out = [];
        foreach ($node as $k => $v) {
            $out[$k] = is_string($k) ? sanitize_pages_node($v, $k) : $v;
        }
        return $isList ? array_values($out) : $out;
    }
    return sanitize_scalar($node, $key);
}

function sanitize_site(array $site): array
{
    $out = [];
    foreach ($site as $k => $v) {
        if ($k === 'social' && is_array($v)) {
            foreach ($v as $sk => $sv) {
                $out['social'][$sk] = sanitize_scalar($sv, $sk . '_url', 300);
            }
        } elseif ($k === 'phone' || $k === 'phoneRaw') {
            $out[$k] = clean_line($v, 40);
        } else {
            $out[$k] = is_array($v) ? sanitize_pages_node($v, $k) : sanitize_scalar($v, $k, 500);
        }
    }
    return $out;
}

function sanitize_product(array $src): array
{
    $out = [];
    foreach ($src as $k => $v) {
        if (is_int($v) || is_float($v)) {
            $out[$k] = round_price($v);
        } elseif (is_bool($v)) {
            $out[$k] = $v;
        } elseif (is_array($v)) {
            $out[$k] = sanitize_pages_node($v, $k);
        } else {
            $out[$k] = sanitize_scalar($v, $k);
        }
    }
    $out['id']    = preg_replace('/[^a-zA-Z0-9_-]/', '', clean_line($src['id'] ?? '', 60));
    $out['name']  = clean_line($src['name'] ?? '', 120);
    $out['price'] = round_price($src['price'] ?? 0);
    $img = trim((string)($src['image'] ?? ''));
    $out['image'] = ($img === '' || preg_match(DANGEROUS_SCHEME_RE, $img)) ? '' : clean_line($img, 400);
    return $out;
}

function sanitize_calculator(array $calc): array
{
    $normItem = static function ($it) {
        $it = (array)$it;
        return [
            'id'     => preg_replace('/[^a-zA-Z0-9_-]/', '', clean_line($it['id'] ?? '', 60)),
            'label'  => clean_line($it['label'] ?? '', 160),
            'hint'   => clean_line($it['hint'] ?? '', 200),
            'price'  => round_price($it['price'] ?? 0),
            'active' => ($it['active'] ?? true) !== false,
        ];
    };
    $out = [];
    foreach ($calc as $k => $v) {
        if ($k === 'baseOptions' || $k === 'modules') {
            continue;
        }
        $out[$k] = is_string($v) ? clean_line($v, 300) : $v;
    }
    $out['baseOptions'] = array_values(array_filter(
        array_map($normItem, (array)($calc['baseOptions'] ?? [])),
        static fn($x) => $x['id'] !== '' && $x['label'] !== ''
    ));
    $out['modules'] = array_values(array_filter(
        array_map($normItem, (array)($calc['modules'] ?? [])),
        static fn($x) => $x['id'] !== '' && $x['label'] !== ''
    ));
    return $out;
}

function merge_by_id(array $previous, array $incoming): array
{
    $prev = [];
    foreach ($previous as $item) {
        if (is_array($item) && isset($item['id'])) {
            $prev[(string)$item['id']] = $item;
        }
    }
    $out = [];
    foreach ($incoming as $item) {
        if (is_array($item) && isset($item['id']) && isset($prev[(string)$item['id']])) {
            $out[] = array_merge($prev[(string)$item['id']], $item);
        } else {
            $out[] = $item;
        }
    }
    return $out;
}

/** Полная валидация структуры из POST api/admin/save */
function build_sanitized_content(array $payload, array $current): array
{
    $body = $payload;
    $newProducts = array_map('sanitize_product', array_map(fn($p) => (array)$p, (array)($body['products'] ?? [])));
    $out = [
        ...$current,
        'site'       => array_merge((array)$current['site'], sanitize_site((array)($body['site'] ?? []))),
        'pages'      => sanitize_pages_node((array)($body['pages'] ?? []), 'pages'),
        'products'   => merge_by_id((array)$current['products'], $newProducts),
        'filters'    => array_values(array_filter(
            array_map(static fn($f) => [
                'id'    => preg_replace('/[^a-zA-Z0-9_-]/', '', clean_line(((array)$f)['id'] ?? '', 60)),
                'label' => clean_line(((array)$f)['label'] ?? '', 120),
            ], (array)($body['filters'] ?? $current['filters'] ?? [])),
            static fn($f) => $f['id'] !== '' && $f['label'] !== ''
        )),
        'calculator' => sanitize_calculator((array)($body['calculator'] ?? [])),
    ];
    unset($out['orders']); // заявки живут файлами, в data.json их быть не должно

    $problems = [];
    if (empty($out['site']['phone'])) {
        $problems[] = 'Укажите телефон компании.';
    }
    if (!empty($out['site']['email']) && !is_valid_email($out['site']['email'])) {
        $problems[] = 'Некорректный e-mail компании.';
    }
    if (!$out['calculator']['baseOptions']) {
        $problems[] = 'В калькуляторе должна быть хотя бы одна база.';
    }
    foreach ($out['calculator']['baseOptions'] as $b) {
        if ($b['price'] <= 0) {
            $problems[] = 'Цена базы «' . $b['label'] . '» должна быть больше нуля.';
        }
    }
    foreach ($out['calculator']['modules'] as $m) {
        if ($m['price'] <= 0) {
            $problems[] = 'Цена модуля «' . $m['label'] . '» должна быть больше нуля.';
        }
    }
    $ids = array_merge(
        array_column($out['calculator']['baseOptions'], 'id'),
        array_column($out['calculator']['modules'], 'id')
    );
    if (count($ids) !== count(array_unique($ids))) {
        $problems[] = 'id баз и модулей должны быть уникальны.';
    }
    foreach ($out['products'] as $p) {
        $p = (array)$p;
        if (empty($p['id'])) {
            $problems[] = 'У изделия пустой id.';
        }
        if (((int)($p['price'] ?? 0)) < 0) {
            $problems[] = 'Отрицательная цена у «' . ($p['name'] ?? '') . '».';
        }
    }

    return ['content' => $out, 'problems' => $problems];
}

// --- Расчёт стоимости (источник истины — цены из data.json) ---------------

function calc_total(array $calculator, $baseId, array $wantedModules): array
{
    $items = [];
    $total = 0;

    $baseId = (string)($baseId ?? '');
    $base = null;
    foreach ((array)$calculator['baseOptions'] as $b) {
        $b = (array)$b;
        if (($b['id'] ?? '') === $baseId && !empty($b['active'])) {
            $base = $b;
            break;
        }
    }
    if (!$base) {
        return ['error' => 'База комплектации не выбрана или недоступна.', 'items' => [], 'total' => 0];
    }
    $items[] = ['id' => $base['id'], 'label' => $base['label'], 'price' => round_price($base['price']), 'type' => 'base'];
    $total += round_price($base['price']);

    foreach ($wantedModules as $id) {
        $mod = null;
        foreach ((array)$calculator['modules'] as $m) {
            $m = (array)$m;
            if (($m['id'] ?? '') === (string)$id && !empty($m['active'])) {
                $mod = $m;
                break;
            }
        }
        if (!$mod) {
            return ['error' => 'Модуль «' . clean_line($id, 60) . '» не найден или отключён.', 'items' => [], 'total' => 0];
        }
        if (in_array($mod['id'], array_column($items, 'id'), true)) {
            continue; // дубликаты игнорируем
        }
        $items[] = ['id' => $mod['id'], 'label' => $mod['label'], 'price' => round_price($mod['price']), 'type' => 'module'];
        $total += round_price($mod['price']);
    }
    return ['items' => $items, 'total' => $total];
}

// --- Валидация полей заявки ------------------------------------------------

function normalize_phone($raw): string
{
    $digits = preg_replace('/\D/', '', (string)$raw);
    if (strlen($digits) === 11 && $digits[0] === '8') {
        $digits = '7' . substr($digits, 1);
    }
    if (strlen($digits) !== 11 || $digits[0] !== '7') {
        return '';
    }
    return '+7 (' . substr($digits, 1, 3) . ') ' . substr($digits, 4, 3) . '-'
        . substr($digits, 7, 2) . '-' . substr($digits, 9, 2);
}

function is_valid_email($s): bool
{
    return (bool)preg_match('/^[^\s@]+@[^\s@]+\.[a-zA-Zа-яА-Я]{2,}$/u', trim((string)$s));
}

// --- Авторизация админки: токен = файл в sessions_dir --------------------
// Память процесса на shared-хостинке живёт один запрос, поэтому сессии
// храним в виде файлов `<token>` c временем истечения внутри.

const TOKEN_TTL = 43200; // 12 часов, сек

function issue_token(): string
{
    $token = bin2hex(random_bytes(32));
    $dir = config()['sessions_dir'];
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    file_put_contents($dir . '/' . $token, (string)(time() + TOKEN_TTL));
    // чистим протухшие
    foreach (glob($dir . '/*') ?: [] as $f) {
        if (basename($f) === $token) {
            continue;
        }
        if (((int)@file_get_contents($f)) < time()) {
            @unlink($f);
        }
    }
    return $token;
}

function check_token(?string $token): bool
{
    if (!$token || !preg_match('/^[a-f0-9]{64}$/', $token)) {
        return false;
    }
    $file = config()['sessions_dir'] . '/' . $token;
    $exp = (int)@file_get_contents($file);
    if ($exp < time()) {
        @unlink($file);
        return false;
    }
    file_put_contents($file, (string)(time() + TOKEN_TTL)); // скользящее истечение
    return true;
}

function bearer_token(): string
{
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $auth = $headers['Authorization'] ?? $headers['authorization']
        ?? ($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
    if (preg_match('/Bearer\s+(\S+)/i', (string)$auth, $m)) {
        return trim($m[1]);
    }
    return '';
}

function require_admin(): void
{
    if (!check_token(bearer_token())) {
        json_out(401, ['ok' => false, 'error' => 'Сессия администратора недействительна. Войдите заново.']);
    }
}

// Защита от перебора пароля: 8 попыток с одного IP за 15 минут (файловый счётчик)
function allow_login_attempt(): bool
{
    $ip   = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $dir  = config()['sessions_dir'] . '/attempts';
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    $file = $dir . '/' . md5($ip) . '.json';
    $now  = time();
    $arr  = [];
    if (is_file($file)) {
        $arr = array_values(array_filter(
            (array)json_decode((string)file_get_contents($file), true),
            static fn($t) => $now - (int)$t < 900
        ));
    }
    if (count($arr) >= 8) {
        file_put_contents($file, json_encode($arr));
        return false;
    }
    $arr[] = $now;
    file_put_contents($file, json_encode($arr));
    return true;
}

function verify_password(string $password): bool
{
    $expected = hash('sha256', config()['admin_password']);
    $given    = hash('sha256', $password);
    return hash_equals($expected, $given);
}

// --- Уведомления (письмо / webhook) — best effort --------------------------

function send_order_mail(array $order): bool
{
    $to = config()['order_email'];
    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    $lines = [
        'Заявка №' . $order['id'] . ' — ' . number_format((float)$order['total'], 0, ',', ' ') . ' ₽',
        'Имя: ' . $order['name'],
        !empty($order['company']) ? 'Компания: ' . $order['company'] : '',
        'Телефон: ' . $order['phone'],
        !empty($order['email']) ? 'E-mail: ' . $order['email'] : '',
        !empty($order['machine']) ? 'Модель станка: ' . $order['machine'] : '',
        '',
        'Комплектация:',
    ];
    foreach ((array)$order['items'] as $i) {
        $i = (array)$i;
        $lines[] = '— ' . $i['label'] . ': ' . number_format((float)$i['price'], 0, ',', ' ') . ' ₽';
    }
    $subject = '=?UTF-8?B?' . base64_encode('VirshkeTech: новая заявка №' . $order['id'] . ' (' . $order['name'] . ')') . '?=';
    $from = config()['mail_from'] ?: $to;
    $headers = implode("\r\n", [
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'From: ' . $from,
        'Reply-To: ' . (!empty($order['email']) && is_valid_email($order['email']) ? $order['email'] : $from),
    ]);
    return @mail($to, $subject, implode("\r\n", array_filter($lines, static fn($l) => $l !== '')), $headers);
}

function send_webhook(array $order): void
{
    $url = config()['webhook_url'];
    if ($url === '' || !preg_match('#^https?://#i', $url)) {
        return;
    }
    $payload = json_encode(['order' => [
        'id' => $order['id'], 'name' => $order['name'], 'phone' => $order['phone'], 'total' => $order['total'],
    ]]);
    $ctx = stream_context_create(['http' => [
        'method'  => 'POST',
        'header'  => "Content-Type: application/json\r\n",
        'content' => $payload,
        'timeout' => 5,
        'ignore_errors' => true,
    ]]);
    @file_get_contents($url, false, $ctx); // результат не критичен
}

// --- CSV -------------------------------------------------------------------

function orders_to_csv(array $orders): string
{
    $head = ['ID', 'Дата', 'Статус', 'Имя', 'Компания', 'Телефон', 'E-mail', 'Станок', 'Комплектация', 'Сумма, руб'];
    $cell = static fn($v) => '"' . str_replace('"', '""', (string)($v ?? '')) . '"';
    $rows = array_map(static function ($o) use ($cell) {
        $o = (array)$o;
        $comp = [];
        foreach ((array)($o['items'] ?? []) as $i) {
            $i = (array)$i;
            $comp[] = $i['label'] . ' — ' . $i['price'] . ' ₽';
        }
        return implode(';', array_map($cell, [
            $o['id'], $o['createdAt'], $o['status'], $o['name'], $o['company'],
            $o['phone'], $o['email'], $o['machine'], implode('; ', $comp), $o['total'],
        ]));
    }, $orders);
    return "\xEF\xBB\xBF" . implode("\r\n", [implode(';', array_map($cell, $head)), ...$rows]) . "\r\n";
}
