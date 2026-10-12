-- ============================================================================
-- PSD2HTML — Этап 1 (MVP): таблицы автoконвертера
-- Префикс таблиц MODX по умолчанию: modx_ (при другом префиксе — заменить)
-- Запуск: mysql -u <user> -p <dbname> < migration/stage1.sql
-- ============================================================================

-- 1. Очередь задач конвертации.
--    Жизненный цикл: queued -> processing -> done | error
CREATE TABLE IF NOT EXISTS `modx_psd2html_queue` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `task_uid`      CHAR(32)     NOT NULL COMMENT 'Публичный ID задачи (hex), отдаётся клиенту для поллинга',
  `conversion_id` INT UNSIGNED NULL     COMMENT 'Связь с history (заполняется при status=done)',
  `status`        ENUM('queued','processing','done','error') NOT NULL DEFAULT 'queued',
  `error_code`    VARCHAR(32)  NULL     COMMENT 'not_psd|corrupted|too_big|timeout|internal',
  `error_msg`     VARCHAR(255) NULL     COMMENT 'Техническое сообщение (клиенту не отдаётся)',
  `attempts`      TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Число попыток воркера',
  `created_ip`    VARBINARY(16) NOT NULL COMMENT 'IP автора (IPv6-packed; для IPv6 хранить /56)',
  `session_key`   CHAR(64)     NULL     COMMENT 'SHA-256 от идентификатора PHP-сессии (не сам id)',
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `started_at`    DATETIME NULL,
  `finished_at`   DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_task_uid` (`task_uid`),
  KEY `idx_status_created` (`status`, `created_at`),
  KEY `idx_rate_ip`   (`created_ip`, `created_at`),
  KEY `idx_rate_sess` (`session_key`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. История конвертаций = публичные результаты (+ токены для /result/{token}/ и ZIP).
CREATE TABLE IF NOT EXISTS `modx_psd2html_history` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `token`         CHAR(16)     NOT NULL COMMENT 'URL-токен результата: 16 hex, случайный',
  `queue_id`      INT UNSIGNED NOT NULL,
  `orig_filename` VARCHAR(255) NOT NULL COMMENT 'Имя файла пользователя (только basename, санитизировано)',
  `stored_path`   VARCHAR(255) NOT NULL COMMENT 'Относительный путь исходника: uploads/YY/MM/<rand>.psd (вне docroot!)',
  `zip_path`      VARCHAR(255) NULL     COMMENT 'Относительный путь ZIP: results/<token>/result.zip',
  `preview_path`  VARCHAR(255) NULL     COMMENT 'PNG композита для предпросмотра: results/<token>/preview.png',
  `file_size`     INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Байт исходного PSD',
  `layers_total`  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `layers_used`   SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Видимых обработанных слоёв',
  `downloads`     INT UNSIGNED NOT NULL DEFAULT 0,
  `expires_at`    DATETIME NOT NULL COMMENT 'Автоочистка: created_at + psd2html.fileTtlHours',
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_token` (`token`),
  KEY `idx_queue` (`queue_id`),
  KEY `idx_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Rate limiting: скользящий суточный счётчик (быстрее, чем COUNT(*) по очереди).
CREATE TABLE IF NOT EXISTS `modx_psd2html_ratelimit` (
  `bucket`     CHAR(64) NOT NULL COMMENT 'sha256(ip) или sha256(session_id)',
  `kind`       ENUM('ip','session') NOT NULL,
  `day`        DATE NOT NULL,
  `hits`       SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`bucket`, `day`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Логи ошибок обработки (для дебага воркера; очищается cleanup.php вместе с файлами).
CREATE TABLE IF NOT EXISTS `modx_psd2html_log` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `queue_id`   INT UNSIGNED NULL,
  `level`      ENUM('info','warn','error') NOT NULL DEFAULT 'error',
  `message`    TEXT NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_queue` (`queue_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- System Settings MODX (можно создать в manager либо SQL-ом ниже)
-- psd2html.storagePath      — абсолютный путь вне docroot, напр. /var/data/psd2html
-- psd2html.maxFileSizeMb    — 30
-- psd2html.dailyLimitIp     — 5
-- psd2html.dailyLimitSession— 5
-- psd2html.fileTtlHours     — 72
-- psd2html.secret           — hex-секрет ≥32 байт для HMAC CSRF/подписи
-- psd2html.resultUrl        — https://site.example/result/ (базовый URL страницы результата)
-- psd2html.ctaEnabled       — 0 (Этап 2: включать после запуска модуля заказов)
-- ----------------------------------------------------------------------------
