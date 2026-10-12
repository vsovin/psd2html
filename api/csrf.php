<?php
/**
 * PSD2HTML — GET /api/csrf.php — выдача CSRF-токена для формы загрузки.
 * Клиент вызывает при инициализации виджета конвертера.
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

try {
    p2h_json(['ok' => true, 'csrf' => p2h_csrf_token()]);
} catch (Throwable $e) {
    error_log('psd2html csrf: ' . $e->getMessage());
    p2h_fail('server', 'Сервер временно недоступен', 503);
}
