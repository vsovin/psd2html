<?php
/**
 * PSD2HTML — POST /api/upload.php
 * Приём PSD, валидация, rate limit, постановка в очередь.
 * Ответ: {ok:true, task_uid:"..."} | {ok:false, error:{code,message}}
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once P2H_ROOT . '/core/components/psd2html/Uploader.php';
require_once P2H_ROOT . '/core/components/psd2html/RateLimiter.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    p2h_fail('method', 'Метод не поддерживается', 405);
}

// --- CSRF -------------------------------------------------------------------------
p2h_csrf_check($_POST['csrf'] ?? null);

// --- Rate limit -------------------------------------------------------------------
$ip = p2h_client_ip();
$sid = P2H_SESSION_ID !== '' ? P2H_SESSION_ID : null;
$limiter = new RateLimiter($pdo);
$check = $limiter->check($ip, $sid);
if (!$check['allowed']) {
    p2h_fail('limit', sprintf(
        'Достигнут суточный лимит конвертаций (%s). Попробуйте завтра или закажите ручную вёрстку.',
        $check['reason'] === 'ip' ? 'по вашему IP' : 'по вашей сессии'
    ), 429);
}

// --- Загрузка + очередь -------------------------------------------------------------
$maxMb = (int)p2h_setting('maxFileSizeMb', 30);
$uploader = new Uploader($pdo, P2H_UPLOADS, $maxMb * 1048576);

try {
    $res = $uploader->handle($_FILES['psd'] ?? [], $ip, $sid);
} catch (UploadException $e) {
    p2h_fail($e->errorCode, $e->getMessage(), 400);
}

$limiter->hit($ip, $sid);   // считаем только успешно созданные задачи

p2h_json(['ok' => true, 'task_uid' => $res['task_uid']], 201);
