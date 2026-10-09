<?php
/**
 * Пример серверного обработчика формы заказа для хостинга с PHP.
 * Подключение: раскомментируйте в .htaccess строку
 *   RewriteRule ^api/order$ api/order.php [L]
 * и задайте $MAIL_TO. Без PHP работает JS-заглушка (редирект на /thanks/).
 */

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'method_not_allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'bad_json']);
    exit;
}

// Белый список полей — всё остальное игнорируем
$fields = ['name', 'company', 'phone', 'email', 'machine', 'config'];
$order  = [];
foreach ($fields as $f) {
    $order[$f] = isset($data[$f]) ? mb_substr(trim((string)$data[$f]), 0, 5000) : '';
}

if ($order['name'] === '' || $order['phone'] === '') {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'required_fields']);
    exit;
}

$MAIL_TO = 'orders@virshketech.com'; // TODO: заменить на реальный адрес

$body = "Заявка ТФО-160\n\n";
foreach ($order as $k => $v) {
    $body .= "$k: $v\n";
}
$body .= "\nIP: " . ($_SERVER['REMOTE_ADDR'] ?? '') . "\n";

$sent = @mail($MAIL_TO, 'Заявка ТФО-160 — VirshkeTech.com', $body,
    'From: noreply@virshketech.com' . "\r\n" .
    'Reply-To: ' . str_replace(["\r", "\n"], '', $order['email']) . "\r\n");

// Резервная копия заявок (если mail() недоступен на хостинге)
file_put_contents(__DIR__ . '/orders.log',
    date('c') . ' | ' . json_encode($order, JSON_UNESCAPED_UNICODE) .
    ' | mail=' . ($sent ? 'ok' : 'fail') . "\n",
    FILE_APPEND | LOCK_EX);

echo json_encode(['ok' => true]);
