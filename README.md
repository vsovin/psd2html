# VirshkeTech.com — лендинг ТФО-160

Статический многостраничный сайт (HTML5 + CSS3 + Vanilla JS, без зависимостей).

## Структура
- `/` — главная с переключателем аудитории, калькулятором и формой заказа
- `/cnc-pro/` `/cnc-lite/` `/manual/` — страницы под сегменты A/B/C
- `/calculator/` `/order/` `/thanks/` — сервисные страницы
- `assets/` — style.css, script.js, scheme.svg
- `.htaccess` — чистые URL, gzip, кэш, MIME
- `api/order.php` — пример серверного обработчика формы (опционально)

## Деплой на виртуальный хостинг
1. Загрузите содержимое корня проекта в `public_html` (или `www`/`htdocs`)
   через FTP/панель хостинга. НЕ загружайте `tools/` и `node_modules/`.
2. Проверьте, что `.htaccess` активен (на некоторых панелях скрытые файлы
   не видны в файловом менеджере — включите показ скрытых файлов).
3. Сайт работает как статика сразу: форма заказа на хостинге без backend
   имитирует отправку и ведёт на `/thanks/`.
4. Для реальной приёма заявок (нужен PHP):
   - раскомментируйте в `.htaccess`: `RewriteRule ^api/order$ api/order.php [L]`
   - в `api/order.php` задайте `$MAIL_TO`; заявки дублируются в `api/orders.log`.

## Локальная проверка
    python3 -m http.server 8080   # затем открыть http://localhost:8080/
Тесты: `node tools/test_site.js` (jsdom), проверка ссылок: `python3 tools/check_links.py`.

## SEO
`robots.txt` и `sitemap.xml` содержат домен virshketech.com — при смене домена обновите.
