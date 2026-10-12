# PSD2HTML — Структура сайта, ЧПУ-URL, дерево ресурсов MODX и SQL-схема

Документ закрывает **Задачу 1** (структура + URL), архитектуру ресурсов (Задача 5)
и **SQL-схему** (пункт 6 итогового формата). Архитектура сразу заложена под Этап 2:
таблицы заказов и личного кабинета определены заранее (миграция `stage2.sql`),
но создаются позже — чтобы не переделывать схему.

---

## 0. Ответы на уточняющие вопросы (Шаг 0) — предлагаемые значения по умолчанию

Все параметры вынесены в System Settings (`psd2html.*`), чтобы менять без кода.

| Вопрос | Предложение (MVP-значения) | Где меняется |
|---|---|---|
| Макс. размер файла | **30 МБ** (лимит PHP `upload_max_filesize`, страхуется JS-валидацией до отправки) | `psd2html.maxFileSizeMb` |
| Лимит конвертаций в сутки | **5 на IP + 5 на PHP-сессию** («или»): IP — первичный, сессия — для NAT/мобильных сетей | `psd2html.dailyLimitIp`, `psd2html.dailyLimitSession` |
| Язык интерфейса | **RU (MVP) + готовность к EN**: строки UI — через чанк-словарь, переключатель в nav | `psd2html.defaultLanguage`, `cultureKey` |
| Оплата (Этап 2) | **ЮKassa** (эквайринг, чеки 54-ФЗ, SDK из коробки). Robokassa — резерв за интерфейсом `PaymentGatewayInterface`. Для юрлиц — счёт по email | `psd2html.paymentGateway` |
| Хранение файлов | **72 часа**, затем автоочистка cron-ом | `psd2html.fileTtlHours` |

> Если нужны другие значения — правятся только настройки, код не меняется.

---

## 1. Структура ЧПУ-URL

Принципы: кириллические alias'ы (домен RU — понятнее пользователю и RU-поиску),
плоская структура (не глубже 2 уровней), технические эндпоинты — **вне** дерева
ресурсов, отдельный PHP в `/api/` (не индексируется, мимо FURL).

### Публичная часть (Этап 1)

| URL (alias) | Ресурс | Шаблон | Примечание |
|---|---|---|---|
| `/` | main | `home` | Конвертер в первом экране |
| `/o-servise/` | about | `inner` | О сервисе |
| `/kak-eto-rabotaet/` | how-it-works | `inner` | 4 шага + пример до/после |
| `/tarify/` | pricing | `inner` | Бесплатно / ручная вёрстка от X ₽ |
| `/faq/` | faq | `inner` | Микроразметка FAQPage |
| `/blog/` | blog | `article-list` | Список постов (pdoResources) |
| `/blog/{post-alias}/` | post | `article` | Пост блога |
| `/kontakty/` | contacts | `inner` | Форма FormIt |
| `/politika-konfidentsialnosti/` | privacy | `inner` | Юридический, noindex-опционально |
| `/polzovatelskoe-soglashenie/` | terms | `inner` | Юридический |
| `/result/{token}/` | result | `result` | Предпросмотр; token = 16 hex; **noindex** |
| `/404.html` → кастомная страница | error404 | `inner` | `system_page_404` |
| `/sitemap.xml` | pdoSitemap | — | Виртуальный URL |
| `/robots.txt` | файл | — | Серверный |

### API (вне FURL, прямые PHP-эндпоинты)

| URL | Метод | Назначение |
|---|---|---|
| `/api/csrf.php` | GET | CSRF-токен для формы |
| `/api/upload.php` | POST | Загрузка PSD (валидация, создание задачи) |
| `/api/status.php?task=UID` | GET | Ajax-поллинг статуса очереди |
| `/api/download.php?token=...` | GET | ZIP-архив результата |

Токен результата используется и в Этапе 2 для передачи контекста CTA
(«макет уже загружен» — заказ привязывается к `conversion_id`).

### Этап 2 (URL и ресурсы закладываем сейчас, контент — заглушки)

| URL | Ресурс | Шаблон |
|---|---|---|
| `/zakaz-vertki/` | order-new | `order` (форма + бриф) |
| `/lk/` | cabinet (контейнер) | `cabinet` |
| `/lk/konvertacii/` | cab-conversions | `cabinet` |
| `/lk/zakazy/` | cab-orders | `cabinet` |
| `/vhod/`, `/registraciya/`, `/vyshli/` | login/register/logout | `auth` |
| `/api/order-create.php`, `/api/payment-webhook.php` | POST | Заказы, платёж (E2) |

### .htaccess (фрагмент)

```apache
RewriteEngine On
RewriteBase /
# API — мимо MODX
RewriteRule ^api/ - [L]
# Стандартный фронт-контроллер MODX Revolution
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php?q=$1 [L,QSA]
```

Хранилище загрузок физически **вне docroot** (`/var/data/psd2html/`) — исполнять
нечему; дополнительно `php_admin_value engine Off` на путь, если vhost общий.

FURL-настройки MODX: `friendly_urls=true`, `request_param=q`, `container_suffix=/`,
`use_alias_only=false` (для `/blog/post/`).

---

## 2. Дерево ресурсов MODX

```
[home: main]                                  tpl=home    tv: seo_title, seo_description, og_image, intro
├── o-servise                                 tpl=inner   tv: seo_*, intro
├── kak-eto-rabotaet                          tpl=inner   tv: seo_*, intro
├── tarify                                    tpl=inner   tv: seo_*, intro, cta_enabled
├── faq                                       tpl=inner   tv: seo_* (контент = Q/A для FAQPage)
├── blog                                      tpl=article-list (контейнер)
│   └── {post-alias}…                         tpl=article tv: seo_*, intro, og_image
├── kontakty                                  tpl=inner   tv: seo_*
├── politika-konfidentsialnosti               tpl=inner   hidefromsearch=1
├── polzovatelskoe-soglashenie                tpl=inner   hidefromsearch=1
├── result                                    tpl=result  hidemenu=1, cacheable=0, noindex
├── [E2] zakaz-vertki                         tpl=order   hidemenu=1
├── [E2] lk/ (konvertacii, zakazy)            tpl=cabinet hidemenu=1
├── [E2] vhod / registraciya / vyshli         tpl=auth    hidemenu=1
└── [sys] 404                                 tpl=inner   (site_status/page_404)
```

### Шаблоны (Template records)

| Template | Описание | Ссылки на чанки |
|---|---|---|
| `base` | Каркас: `<head>` (мета/OG + critical CSS inline), header, footer, JS | `header`, `footer`, `nav`, `meta`, `scripts` |
| `home` | Наследует base; первый экран — конвертер, ниже «Как это работает», тарифы, FAQ, CTA | `converter`, `how-it-works`, `pricing-teaser`, `faq-block`, `cta-manual` |
| `inner` | Хлебные крошки + intro + контент | `breadcrumbs` |
| `article-list` | pdoResources со страницами блога | `breadcrumbs` |
| `article` | Пост + og_image | `breadcrumbs` |
| `result` | Предпросмотр + слот CTA «Заказать ручную вёрстку» | `cta-manual` |
| `order`, `cabinet`, `auth` | Этап 2 | — |

### TV-параметры

| TV | Тип | Назначение |
|---|---|---|
| `seo_title` | text | Переопределение `<title>` |
| `seo_description` | text | meta description |
| `og_image` | image | Open Graph / Twitter card |
| `intro` | text | Вводный текст под H1 |
| `hide_cta_manual` | checkbox | Локальное отключение CTA Этапа 2 |

---

## 3. Обоснование выбора парсинга PSD (Задача 3)

| Вариант | Плюсы | Минусы | Вердикт |
|---|---|---|---|
| **a) Imagick (ImageMagick + PSD-делегат)** | Нативная поддержка формата, экспорт композита и слоёв в растр | Текстовые слои растеризуются, нет доступа к TySh/именам как данным | **Растровый бэкенд** (превью + slices) |
| **b) Собственный PHP-парсер структуры PSD** | Полный доступ к дереву: имя, тип, видимость, bbox, текст/кегль из `TySh`; PSD — предсказуемый big-endian формат (~250 строк) | Сложные эффекты не воспроизводим (не нужно) | **Структурный источник** |
| **c) ag-psd на клиенте** | Экономия серверных ресурсов | Нельзя гарантировать лимиты/безопасность, десятки МБ в RAM браузера, результат нельзя отдать ZIP-ом с сервера | Отклонено для MVP |
| **d) Сторонний API** | Быстрый старт | Стоимость, конфиденциальность макетов клиентов, vendor lock-in | Отклонено |

**Рабочая связка (гибрид a+b):** собственный парсер читает дерево слоёв
(имена/типы/видимость/bbox/тексты), Imagick рендерит композит в превью и каждый
видимый растровый слой — в PNG/WebP. Генератор HTML собирает разметку из дерева,
подставляя растры Imagick и текстовые ноды. Это даёт семантический HTML5 без
зависимостей от коммерческих API.

Требования: Imagick ≥ 3.4.3; ImageMagick с делегатом PSD
(`convert -list format | grep -i psd` → `PSD* … Adobe Photoshop`);
`memory_limit=512M`; `IMAGICK_LIMIT_AREA` поднят.

---

## 4. SQL-схема (префикс таблиц MODX — `modx_`)

Файлы: `migration/stage1.sql` (Этап 1) и `migration/stage2.sql` (Этап 2, схема готова заранее).
Краткое описание таблиц — в файлах миграций (см. каталог `/migration`), здесь —_erDiagram:

```text
queue 1—1 history (done)          history N—1 user_links (E2)
queue: task_uid, status(enum queued/processing/done/error), attempts, ip, session_key, timestamps
history: token, stored_path, zip_path, orig_filename, layers_total/used, downloads, expires_at, user_id(E2)
ratelimit: bucket(ip|session)+day -> hits
orders (E2): order_no, user_id, conversion_id(brief context), status enum new/paid/in_work/review/done/canceled,
             brief_json, screens_count, price_rub, discount_rub, promocode, deadline
promocodes (E2), payments (E2: gateway/gateway_id/status/idempotence),
order_events (E2: from→to + notified для email)
```

Личный кабинет Этапа 2 использует **штатных пользователей MODX** (`modx_users`/
`modUserProfile`) — своих таблиц пользователей нет; связь анонимной истории с
аккаунтом — таблица `modx_psd2html_user_links` (claim по `session_key`).

---

## 5. План по этапам (чек-листы)

### Этап 1 (MVP) — выполняется сейчас
- [x] Структура URL, дерево ресурсов, шаблоны/ТВ (этот документ)
- [x] SQL: очередь, история, rate limit (`migration/stage1.sql`)
- [x] Загрузка + валидация (magic bytes `8BPS`), лимиты, случайные имена, хранение вне docroot
- [x] Cron-воркер очереди (queued/processing/done/error)
- [x] Парсер PSD + Imagick-рендер + генератор HTML/CSS
- [x] Ajax-поллинг, drag-n-drop UI, прогресс, предпросмотр, скачивание ZIP
- [x] Автоочистка файлов (TTL)
- [x] Чанки MODX, SEO (FURL, sitemap, robots, canonical, Organization/WebSite/FAQPage/BreadcrumbList), 404
- [ ] Копирайт страниц (О сервисе, Тарифы, FAQ…) — после приёмки кода
- [ ] Замер PageSpeed ≥ 90 на главной

### Этап 2 (только после подтверждения приёмки MVP)
- [ ] Миграция `stage2.sql`
- [ ] Авторизация (LoginManager), личный кабинет, claim истории
- [ ] Форма заказа + бриф (FormIt), выбор недавней конвертации (контекст CTA, без повторной загрузки)
- [ ] Расчёт стоимости (экраны × ставка × множители), промокоды
- [ ] ЮKassa: счёт → webhook → статус paid (резерв Robokassa за тем же интерфейсом)
- [ ] Email-уведомления о смене статуса
- [ ] Включение CTA-слотов (`psd2html.ctaEnabled=true`)
- [ ] Кабинет: история конвертаций + заказов, повторное скачивание

---

## 6. Инструкция развёртывания (Этап 1)

### 6.1 Требования к серверу
- PHP 8.1+, расширения: `imagick`, `zip`, `gd`, `mbstring`, `pdo_mysql`, `fileinfo`
- ImageMagick с делегатом PSD (проверка в разделе 3), `upload_max_filesize=32M`, `post_max_size=34M`, `memory_limit=512M`
- MySQL 5.7+ / MariaDB 10.4+ (utf8mb4, JSON)
- MODX Revolution 3.x + пакеты: **pdoTools**, **SEO Pro**, **FormIt**
- Cron с доступом к внешнему хранилищу
- Вне web-root: `mkdir -p /var/data/psd2html/{uploads,results,tmp,logs}`;
  группа `www-data`, права `750`; PHP-FPM и cron — от одной группы (`setfacl -R -m g:www-data:rx /var/data/psd2html`)

### 6.2 Шаги
1. Установить MODX; включить `friendly_urls`.
2. Создать шаблоны/TV/ресурсы по разделу 2 (можно вручную в manager; структура стабильна).
3. Разместить файлы репозитория:
   - `core/components/psd2html/*.php` — классы, worker, cleanup;
   - `assets/components/psd2html/{js,css}` — клиент;
   - `api/*.php` — эндпоинты;
   - `core/templates/chunks/*.tpl` — чанки (импортировать в элементы MODX).
4. Выполнить `mysql modx < migration/stage1.sql`.
5. Задать system settings `psd2html.*`: пути хранилищ, лимиты, TTL, секрет подписи токенов (`psd2html.secret` — random 32+ байт hex).
6. Обновить `.htaccess` (раздел 1).
7. Cron:
   ```cron
   * * * * * /usr/bin/php /var/www/mysite/core/components/psd2html/worker.php >> /var/log/psd2html-worker.log 2>&1
   */30 * * * * /usr/bin/php /var/www/mysite/core/components/psd2html/cleanup.php >> /var/log/psd2html-cleanup.log 2>&1
   ```
8. E2E-проверка: загрузить тестовый PSD → поллинг до `done` → открыть `/result/{token}/` → скачать ZIP (index.html + style.css + assets/).

### 6.3 Безопасность (что где реализовано)
- Whitelist `.psd` + MIME `image/vnd.adobe.photoshop` + magic bytes `8BPS` — `Uploader.php`
- Размер ≤ лимита: проверка `Content-Length` до приёма тела + поточный подсчёт — `Uploader.php`
- Случайные имена (`bin2hex(random_bytes())`), пользовательское имя хранится только как basename — `Uploader.php`
- Хранилище вне docroot; загруженные файлы никогда не исполняются; ZIP собирается из готовых raster-файлов — `Converter.php`, `download.php`
- CSRF-токен (HMAC от сессии) на upload — `csrf.php`, `main.js`
- Rate limit по IP+сессии — `RateLimiter.php`
- Санитайз имён слоёв → CSS-классы, экранирование текстов (`htmlspecialchars`) — `HtmlGenerator.php`
- noindex на /result/, `X-Content-Type-Options: nosniff`, CORS не отдаётся — API-файлы
- Логирование ошибок без выдачи стега клиенту (error_code → человекочитаемое сообщение) — `status.php`
