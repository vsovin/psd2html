<?php
/**
 * Точка входа API: public/api/index.php
 * Все запросы к /api/... приходят сюда (см. .htaccess), PATH_INFO
 * содержит путь после «index.php», например «/admin/orders».
 */

declare(strict_types=1);

require dirname(__DIR__, 2) . '/php/lib.php';

header('X-Content-Type-Options: nosniff');

$path = trim($_SERVER['PATH_INFO'] ?? ($_GET['r'] ?? ''), '/');
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// --- Публичные эндпоинты ----------------------------------------------------

if ($path === 'health') {
    json_out(200, [
        'ok' => true,
        'mail' => config()['order_email'] !== '',
        'backend' => 'php',
    ]);
}

if ($path === 'data' && $method === 'GET') {
    $data = read_data();
    unset($data['orders']); // заявки публично не отдаём
    json_out(200, ['ok' => true] + $data);
}

if ($path === 'order' && $method === 'POST') {
    $body = read_json_body();
    $errors = [];

    $name = clean_line($body['name'] ?? '', 120);
    if (mb_strlen($name) < 2) {
        $errors[] = 'Укажите имя (минимум 2 символа).';
    }
    $company = clean_line($body['company'] ?? '', 160);
    $machine = clean_text($body['machine'] ?? '', 1000);

    $phone = normalize_phone($body['phone'] ?? '');
    if ($phone === '') {
        $errors[] = 'Укажите корректный номер телефона.';
    }
    $email = clean_line($body['email'] ?? '', 120);
    if ($email !== '' && !is_valid_email($email)) {
        $errors[] = 'Некорректный e-mail.';
    }
    if (empty($body['consent'])) {
        $errors[] = 'Требуется согласие на обработку персональных данных.';
    }

    $calc = calc_total(read_data()['calculator'], $body['base'] ?? [], (array)($body['modules'] ?? []));
    if (!empty($calc['error'])) {
        $errors[] = $calc['error'];
    }
    if ($errors) {
        json_out(400, ['ok' => false, 'errors' => $errors]);
    }

    $order = [
        'id'        => next_order_id(),
        'name'      => $name,
        'company'   => $company,
        'phone'     => $phone,
        'email'     => $email,
        'machine'   => $machine,
        'base'      => clean_line($body['base'] ?? '', 60),
        'items'     => $calc['items'],
        'total'     => $calc['total'],
        'status'    => 'new',
        'createdAt' => gmdate('Y-m-d\TH:i:s\Z'),
        'source'    => clean_line($body['source'] ?? '', 120) ?: 'site',
    ];

    try {
        save_order($order);
    } catch (Throwable $e) {
        json_out(500, ['ok' => false, 'errors' => ['Не удалось сохранить заявку. Повторите позже.']]);
    }

    // Уведомления — best effort, ошибка письма не ломает приём заявки
    try { send_order_mail($order); } catch (Throwable $e) { /* ignore */ }
    try { send_webhook($order); } catch (Throwable $e) { /* ignore */ }

    json_out(200, ['ok' => true, 'id' => $order['id'], 'total' => $order['total']]);
}

// --- Админские эндпоинты ------------------------------------------------------

if ($path === 'admin/login' && $method === 'POST') {
    if (!allow_login_attempt()) {
        json_out(429, ['ok' => false, 'error' => 'Слишком много попыток. Подождите 15 минут.']);
    }
    $body = read_json_body();
    if (!verify_password((string)($body['password'] ?? ''))) {
        json_out(401, ['ok' => false, 'error' => 'Неверный пароль.']);
    }
    json_out(200, ['ok' => true, 'token' => issue_token(), 'expiresIn' => TOKEN_TTL * 1000]);
}

if ($path === 'admin/logout' && $method === 'POST') {
    require_admin();
    $file = config()['sessions_dir'] . '/' . bearer_token();
    @unlink($file);
    json_out(200, ['ok' => true]);
}

if ($path === 'admin/session' && $method === 'GET') {
    require_admin();
    json_out(200, ['ok' => true]);
}

if ($path === 'admin/data' && $method === 'GET') {
    require_admin();
    $data = read_data();
    $data['orders'] = array_map(static function ($o) {
        unset($o['__file']);
        return $o;
    }, load_orders());
    json_out(200, ['ok' => true, 'data' => $data]);
}

if ($path === 'admin/save' && $method === 'POST') {
    require_admin();
    $current = read_data();
    $result = build_sanitized_content(read_json_body(), $current);
    if ($result['problems']) {
        json_out(400, ['ok' => false, 'errors' => $result['problems']]);
    }
    try {
        write_data($result['content']);
    } catch (Throwable $e) {
        json_out(500, ['ok' => false, 'error' => 'Ошибка записи data.json: ' . $e->getMessage()]);
    }
    json_out(200, ['ok' => true]);
}

if ($path === 'admin/orders' && $method === 'GET') {
    require_admin();
    $status = clean_line($_GET['status'] ?? '', 20);
    $orders = load_orders();
    if ($status !== '' && $status !== 'all') {
        $orders = array_values(array_filter($orders, static fn($o) => ($o['status'] ?? '') === $status));
    }
    $orders = array_map(static function ($o) {
        unset($o['__file']);
        return $o;
    }, array_reverse($orders));
    json_out(200, ['ok' => true, 'orders' => $orders]);
}

// admin/orders/{id}/status  и  admin/orders/export.csv
if (preg_match('#^admin/orders/export\.csv$#', $path) && $method === 'GET') {
    require_admin();
    $status = clean_line($_GET['status'] ?? '', 20);
    $orders = load_orders();
    if ($status !== '' && $status !== 'all') {
        $orders = array_values(array_filter($orders, static fn($o) => ($o['status'] ?? '') === $status));
    }
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="virshketech-orders-' . time() . '.csv"');
    echo orders_to_csv($orders);
    exit;
}

if (preg_match('#^admin/orders/(\d+)/status$#', $path, $m) && $method === 'POST') {
    require_admin();
    $allowed = ['new', 'in_progress', 'done', 'archived'];
    $next = clean_line((read_json_body()['status'] ?? ''), 20);
    if (!in_array($next, $allowed, true)) {
        json_out(400, ['ok' => false, 'error' => 'Недопустимый статус.']);
    }
    $order = find_order((int)$m[1]);
    if (!$order) {
        json_out(404, ['ok' => false, 'error' => 'Заявка не найдена.']);
    }
    $order['status'] = $next;
    $order['updatedAt'] = gmdate('Y-m-d\TH:i:s\Z');
    save_order($order);
    unset($order['__file']);
    json_out(200, ['ok' => true, 'order' => $order]);
}

json_out(404, ['ok' => false, 'error' => 'Метод не найден.']);
