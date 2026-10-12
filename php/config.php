<?php
/**
 * VirshkeTech — конфигурация PHP-бэкенда для виртуального (shared) хостинга.
 * Аналог .env: скопируйте config.sample.php в config.php и заполните значения.
 * config.php не должен попадать в публичный каталог и в git (.gitignore).
 */

return [
    // Пароль админки. ОБЯЗАТЕЛЬНО смените перед публикацией.
    'admin_password' => 'virshke-admin',

    // Почта для уведомлений о заявках. Пусто = письма не отправляются,
    // заявки пишутся только в api/orders/ (по одному JSON на заявку).
    'order_email' => '',
    'mail_from'   => '', // конверт, например info@ваш-домен.ru

    // Опциональный webhook (Telegram-бот / CRM): POST JSON {order:{...}}
    'webhook_url' => '',

    // Каталоги. По умолчанию настроены на схему shared-хостинга:
    //   public_html/  — сайт (публично)
    //   vt-backend/   — php/ + data/ (вне веб-рута)
    // На локальном тесте пути совпадают с реальным расположением файлов.
    'data_file'    => dirname(__DIR__) . '/data.json', // контент сайта (site/pages/products/calculator)
    'orders_dir'   => __DIR__ . '/data/orders',        // заявки: по одному JSON на заявку
    'sessions_dir' => __DIR__ . '/data/sessions',      // токены админки
];
