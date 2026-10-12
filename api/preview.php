<?php
/**
 * PSD2HTML — GET /api/preview.php?token=<16hex> — PNG композита для предпросмотра.
 * Отдаём только whitelist-файл preview.png из папки результата (path traversal исключён).
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$token = (string)($_GET['token'] ?? '');
if (!preg_match('/^[0-9a-f]{16}$/', $token)) {
    p2h_fail('bad_token', 'Некорректный токен', 400);
}

$stmt = $pdo->prepare('SELECT preview_path FROM `' . P2H_PREFIX . "psd2html_history` WHERE token=:t AND expires_at > NOW() LIMIT 1");
$stmt->execute([':t' => $token]);
$path = $stmt->fetchColumn();
if (!$path) {
    p2h_fail('not_found', 'Превью недоступно', 404);
}

// Жёстко: путь должен совпадать с results/<token>/preview.png
$expected = 'results/' . $token . '/preview.png';
if ($path !== $expected) {
    p2h_fail('not_found', 'Превью недоступно', 404);
}
$abs = realpath(P2H_STORAGE . '/' . $expected);
if ($abs === false || !str_starts_with($abs, realpath(P2H_RESULTS) ?: '/')) {
    p2h_fail('not_found', 'Превью недоступно', 404);
}

header('Content-Type: image/png');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: public, max-age=3600');
readfile($abs);
