<?php
/**
 * Роутер для локального запуска на встроенном сервере PHP:
 *   php -S 0.0.0.0:8080 router.php
 * (для быстрой проверки перед загрузкой на хостинг).
 * На боевом shared-хостинге этот файл не используется — там .htaccess.
 */

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// /api/... -> public/api/index.php с PATH_INFO, как это делает Apache+mod_rewrite
if (strpos($uri, '/api') === 0) {
    $_SERVER['PATH_INFO'] = substr($uri, 4); // '/data', '/admin/orders' и т.д.
    require __DIR__ . '/public/api/index.php';
    return true;
}

// Служебные файлы наружу не отдаём
if (preg_match('#/(data\.json(\.\w+)?|\.env.*|package.*\.json|server\.js)$#', $uri)) {
    http_response_code(404);
    return true;
}

// Статика из public/ (корень документа при локальном тесте = public/)
$file = __DIR__ . '/public' . $uri;
if ($uri !== '/' && is_file($file)) {
    if (preg_match('/\.html$/i', $file)) {
        header('Cache-Control: no-cache'); // как на боевом Apache
    }
    return false; // отдаёт встроенный сервер
}
if ($uri === '/') {
    readfile(__DIR__ . '/public/index.html');
    return true;
}
http_response_code(404);
readfile(__DIR__ . '/public/index.html');
return true;
