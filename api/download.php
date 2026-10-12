<?php
/**
 * PSD2HTML — GET /api/download.php?token=<16hex> — выдача ZIP результата.
 * Файл лежит ВНЕ docroot; читаем через PHP и отдаём потоком. Имя скачивания —
 * производное от исходного (без user-controlled path!). Счётчик скачиваний.
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$token = (string)($_GET['token'] ?? '');
if (!preg_match('/^[0-9a-f]{16}$/', $token)) {
    p2h_fail('bad_token', 'Некорректный токен', 400);
}

$stmt = $pdo->prepare(
    'SELECT zip_path, orig_filename FROM `' . P2H_PREFIX . "psd2html_history` WHERE token=:t AND zip_path IS NOT NULL LIMIT 1"
);
$stmt->execute([':t' => $token]);
$row = $stmt->fetch();
if (!$row) {
    p2h_fail('not_found', 'Результат не найден или срок хранения истёк', 404);
}

$abs = realpath(P2H_STORAGE . '/' . $row['zip_path']);
$okDir = realpath(P2H_RESULTS);
if ($abs === false || $okDir === false || !str_starts_with($abs, $okDir) || !is_readable($abs)) {
    p2h_fail('not_found', 'Файл результата недоступен', 404);
}

// Имя для Content-Disposition: <basename без .psd>.zip, только безопасные символы
$base = preg_replace('/[^\p{L}\p{N}._-]+/u', '_', pathinfo($row['orig_filename'], PATHINFO_FILENAME)) ?: 'layout';
$downloadName = mb_substr($base, 0, 80) . '.zip';

$pdo->prepare("UPDATE `" . P2H_PREFIX . "psd2html_history` SET downloads = downloads + 1 WHERE token=:t")
    ->execute([':t' => $token]);

header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $downloadName . '"; filename*=UTF-8\'\'' . rawurlencode($downloadName));
header('Content-Length: ' . filesize($abs));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=0, no-cache');

readfile($abs);
