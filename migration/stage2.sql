-- ============================================================================
-- PSD2HTML — Этап 2: модуль платных заказов + личный кабинет.
-- СХЕМА ГОТОВА ЗАРАНЕЕ, но запускать этот файл нужно ТОЛЬКО при старте Этапа 2
-- (после приёмки MVP). Таблица пользователей — штатная MODX (modx_users),
-- своих не заводим.
-- Запуск: mysql -u <user> -p <dbname> < migration/stage2.sql
-- ============================================================================

-- 1. Привязка анонимных сессий к аккаунту (claim истории после регистрации).
CREATE TABLE IF NOT EXISTS `modx_psd2html_user_links` (
  `user_id`     INT UNSIGNED NOT NULL,
  `session_key` CHAR(64)     NOT NULL COMMENT 'SHA-256 идентификатора сессии до авторизации',
  `claimed_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`, `session_key`),
  CONSTRAINT `fk_p2h_user` FOREIGN KEY (`user_id`) REFERENCES `modx_users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. История конвертаций начинает «видеть» пользователя (для ЛК и повторных скачиваний).
ALTER TABLE `modx_psd2html_history`
  ADD COLUMN `user_id` INT UNSIGNED NULL AFTER `token`,
  ADD KEY `idx_user` (`user_id`);

-- 3. Заказы ручной вёрстки. Статусы: new -> paid -> in_work -> review -> done (+ canceled).
CREATE TABLE IF NOT EXISTS `modx_psd2html_orders` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_no`      CHAR(14)     NOT NULL COMMENT 'Человекочитаемый номер, напр. P2H-261013-0001',
  `user_id`       INT UNSIGNED NOT NULL,
  `conversion_id` INT UNSIGNED NULL COMMENT 'Контекст CTA: макет уже загружен, повторная загрузка не нужна',
  `status`        ENUM('new','paid','in_work','review','done','canceled') NOT NULL DEFAULT 'new',
  `brief_json`    JSON NOT NULL COMMENT 'Бриф: layout(fixed/responsive), framework, screens, deadline, comments',
  `screens_count` SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  `price_rub`     DECIMAL(10,2) NOT NULL,
  `discount_rub`  DECIMAL(10,2) NOT NULL DEFAULT 0,
  `promocode`     VARCHAR(64) NULL,
  `deadline`      DATE NULL,
  `comment_admin` TEXT NULL,
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_order_no` (`order_no`),
  KEY `idx_user_status` (`user_id`, `status`),
  CONSTRAINT `fk_ord_user` FOREIGN KEY (`user_id`) REFERENCES `modx_users` (`id`),
  CONSTRAINT `fk_ord_conv` FOREIGN KEY (`conversion_id`) REFERENCES `modx_psd2html_history` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Промокоды (опционально на этапе запуска).
CREATE TABLE IF NOT EXISTS `modx_psd2html_promocodes` (
  `code`        VARCHAR(64) NOT NULL,
  `percent_off` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Скидка в %',
  `fixed_off`   DECIMAL(10,2) NOT NULL DEFAULT 0 COMMENT 'Фикс. скидка, ₽ (приоритет над процентом)',
  `max_uses`    INT UNSIGNED NULL,
  `used`        INT UNSIGNED NOT NULL DEFAULT 0,
  `valid_until` DATE NULL,
  PRIMARY KEY (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Платежи (привязка к шлюзу; gateway: yookassa|robokassa|invoice).
CREATE TABLE IF NOT EXISTS `modx_psd2html_payments` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id`    INT UNSIGNED NOT NULL,
  `gateway`     VARCHAR(32) NOT NULL DEFAULT 'yookassa',
  `gateway_id`  VARCHAR(128) NULL COMMENT 'ID оплаты/счёта в шлюзе',
  `amount_rub`  DECIMAL(10,2) NOT NULL,
  `status`      ENUM('init','waiting','succeeded','canceled') NOT NULL DEFAULT 'init',
  `idempotence` CHAR(36) NULL COMMENT 'Idempotence-Key ЮKassa (защита от двойного платежа)',
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_order` (`order_id`),
  CONSTRAINT `fk_pay_order` FOREIGN KEY (`order_id`) REFERENCES `modx_psd2html_orders` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Журнал смены статусов заказа (источник email-уведомлений; notified — флаг отправки).
CREATE TABLE IF NOT EXISTS `modx_psd2html_order_events` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id`    INT UNSIGNED NOT NULL,
  `from_status` VARCHAR(16) NULL,
  `to_status`   VARCHAR(16) NOT NULL,
  `notified`    TINYINT(1) NOT NULL DEFAULT 0,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_order` (`order_id`),
  CONSTRAINT `fk_ev_order` FOREIGN KEY (`order_id`) REFERENCES `modx_psd2html_orders` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- System Settings Этапа 2:
-- psd2html.ctaEnabled          = 1        (показывать слоты «Заказать ручную вёрстку»)
-- psd2html.paymentGateway      = yookassa
-- psd2html.ykShopId / ykSecret — ключи ЮKassa (хранить вне repo, manager-only)
-- psd2html.pricePerScreen      = 3500     (₽/экран, базовая ставка расчёта)
-- psd2html.multiplierResponsive= 1.3      (адаптив), multiplierBootstrap = 1.15
-- ----------------------------------------------------------------------------
