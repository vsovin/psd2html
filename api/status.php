<?php
/**
 * PSD2HTML — GET /api/status.php?task=<uid>
 * Поллинг статуса задачи клиентом. Ничего лишнего не отдаём: только статус,
 * прогресс (очередь/обработка) и ссылки результата при done.
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$uid = (string)($_GET['task'] ?? '');
if (!preg_match('/^[0-9a-f]{32}$/', $uid)) {
    p2h_fail('bad_task', 'Некорректный идентификатор задачи', 400);
}

$stmt = $pdo->prepare(
    'SELECT q.status, q.error_code, h.token, h.orig_filename, h.layers_total, h.layers_used
     FROM `' . P2H_PREFIX . "psd2html_queue` q
     LEFT JOIN `" . P2H_PREFIX . "psd2html_history` h ON h.id = q.conversion_id
     WHERE q.task_uid = :uid LIMIT 1"
);
$stmt->execute([':uid' => $uid]);
$row = $stmt->fetch();
if (!$row) {
    p2h_fail('not_found', 'Задача не найдена', 404);
}

// Позиция в очереди для «прогресса» ожидания (queued)
$pos = null;
if ($row['status'] === 'queued') {
    $p = $pdo->prepare("SELECT COUNT(*) FROM `" . P2H_PREFIX . "psd2html_queue` WHERE status IN ('queued','processing')");
    $p->execute();
    $pos = (int)$p->fetchColumn();
}

// Человекочитаемые сообщения об ошибках (без технических деталей)
$errorMessages = [
    'not_psd'   => 'Файл не является PSD или имеет неверное расширение.',
    'corrupted' => 'Файл повреждён — Photoshop не смог его открыть. Попробуйте пересохранить.',
    'too_big'   => 'Файл слишком большой для автоматической обработки.',
    'timeout'   => 'Обработка заняла слишком много времени. Уменьшите количество слоёв или закажите ручную вёрстку.',
    'internal'  => 'Внутренняя ошибка обработки. Попробуйте позже.',
];

$data = ['ok' => true, 'status' => $row['status'], 'queue_position' => $pos];
switch ($row['status']) {
    case 'done':
        $resultBase = rtrim((string)p2h_setting('resultUrl', '/result/'), '/') . '/';
        $data['result'] = [
            'token' => $row['token'],
            'page_url' => $resultBase . $row['token'] . '/',
            'download_url' => '/api/download.php?token=' . $row['token'],
            'preview_url' => '/api/preview.php?token=' . $row['token'],
            'filename' => $row['orig_filename'],
            'layers_total' => (int)$row['layers_total'],
            'layers_used' => (int)$row['layers_used'],
        ];
        break;
    case 'error':
        $code = (string)($row['error_code'] ?: 'internal');
        $data['error'] = ['code' => $code, 'message' => $errorMessages[$code] ?? $errorMessages['internal']];
        break;
}

p2h_json($data);
