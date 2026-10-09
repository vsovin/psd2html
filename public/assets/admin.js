/**
 * VirshkeTech — мини-CMS (admin.js, vanilla JS).
 *
 * Схема полей задана ниже: админка умеет редактировать только то, что описано,
 * поэтому структура data.json не может быть случайно сломана из браузера.
 * Сервер валидирует и санитизирует всё повторно — админка не является границей безопасности.
 */
(function () {
  'use strict';

  var TOKEN_KEY = 'vt_admin_token';
  var $ = function (s) { return document.querySelector(s); };
  var $$ = function (sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); };

  /* ----------------------------- helpers --------------------------------- */

  function el(tag, cls, text) {
    var n = document.createElement(tag);
    if (cls) n.className = cls;
    if (text != null) n.textContent = String(text); // экранирование через textContent
    return n;
  }

  /** Экранирование для атрибутов (title, aria-label) */
  function esc(value) {
    return String(value == null ? '' : value)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  }

  function getToken() { try { return localStorage.getItem(TOKEN_KEY) || ''; } catch (e) { return ''; } }
  function setToken(t) { try { t ? localStorage.setItem(TOKEN_KEY, t) : localStorage.removeItem(TOKEN_KEY); } catch (e) {} }

  function api(path, options) {
    var opts = options || {};
    opts.headers = Object.assign({
      'Content-Type': 'application/json',
      Authorization: 'Bearer ' + getToken()
    }, opts.headers || {});
    return fetch(path, opts).then(function (response) {
      return response.json()
        .catch(function () { return { ok: false, error: 'Некорректный ответ сервера.' }; })
        .then(function (json) {
          if (response.status === 401) { forceLogin(json.error || 'Сессия истекла.'); }
          return { status: response.status, json: json };
        });
    });
  }

  function getPath(obj, dotted) {
    return dotted.split('.').reduce(function (a, k) { return a == null ? undefined : a[k]; }, obj);
  }
  function setPath(obj, dotted, value) {
    var keys = dotted.split('.');
    var last = keys.pop();
    var node = obj;
    keys.forEach(function (k) {
      if (node[k] == null || typeof node[k] !== 'object') node[k] = {};
      node = node[k];
    });
    node[last] = value;
  }

  function money(value) {
    var n = Math.round(Number(value) || 0);
    return String(n).replace(/\B(?=(\d{3})+(?!\d))/g, '\u00A0');
  }

  /* ------------------------------ схема ----------------------------------- */

  var SITE_FIELDS = [
    { path: 'company', label: 'Название компании' },
    { path: 'tagline', label: 'Слоган в подвале' },
    { path: 'phone', label: 'Телефон (как показывать)', placeholder: '+7 (900) 000-00-00' },
    { path: 'phoneRaw', label: 'Телефон для ссылки tel:', placeholder: '+79000000000' },
    { path: 'email', label: 'E-mail' },
    { path: 'address', label: 'Адрес', type: 'textarea' },
    { path: 'workHours', label: 'Часы работы' },
    { path: 'social.telegram', label: 'Telegram (URL)' },
    { path: 'social.whatsapp', label: 'WhatsApp (URL)' },
    { path: 'social.youtube', label: 'YouTube (URL)' },
    { path: 'inn', label: 'ИНН / ОГРН' },
    { path: 'footerNote', label: 'Строка копирайта' }
  ];

  var CALC_TEXT_FIELDS = [
    { path: 'title', label: 'Заголовок калькулятора' },
    { path: 'subtitle', label: 'Подзаголовок' },
    { path: 'baseTitle', label: 'Заголовок шага 1 (база)' },
    { path: 'modulesTitle', label: 'Заголовок шага 2 (модули)' },
    { path: 'totalLabel', label: 'Подпись итога' },
    { path: 'currency', label: 'Валюта (символ)' }
  ];

  var PAGE_LABELS = {
    home: 'Главная', products: 'Каталог', tfo160: 'ТФО-160', chuck: 'Модуль патрона',
    tailstock: 'Задняя бабка', robot: 'Робот подачи', vise: 'Автозажим тисков',
    udg: 'Электронная УДГ', gear: 'Электронная зубрёжка', threading: 'Станок резьбы',
    order: 'Заявка', contacts: 'Контакты', thanks: 'Спасибо', notFound: '404'
  };

  var FIELD_LABELS = {
    title: 'Заголовок', subtitle: 'Подзаголовок', lead: 'Лид (краткое описание)',
    heroTitle: 'Hero: заголовок', heroSubtitle: 'Hero: подзаголовок',
    heroImage: 'Hero: картинка (URL)', heroCtaText: 'Hero: текст кнопки', heroCtaLink: 'Hero: ссылка кнопки',
    image: 'Картинка (URL)', badge: 'Плашка над заголовком', name: 'Название',
    sectionProductsTitle: 'Блок «Изделия»: заголовок', sectionProductsText: 'Блок «Изделия»: текст',
    sectionAdvantagesTitle: 'Блок «Преимущества»: заголовок',
    sectionCalculatorTitle: 'Блок «Калькулятор»: заголовок', sectionCalculatorText: 'Блок «Калькулятор»: текст',
    ctaTitle: 'CTA: заголовок', ctaText: 'CTA: текст', ctaButtonText: 'CTA: кнопка',
    agreeText: 'Текст согласия с ПД', successTitle: 'Заголовок после отправки', note: 'Примечание к полю',
    phoneLabel: 'Подпись у телефона', extraTitle: 'Блок «Что дальше»', mapNote: 'Заглушка карты',
    priceFrom: 'Цена «от», ₽', price: 'Цена, ₽', docsTitle: 'Блок «Документация»',
    seoTitle: 'Title вкладки браузера'
  };

  var LONG_KEYS = /subtitle|lead|text|note|about|description/i;
  var ARRAY_MODE = {
    advantages: 'objects',   // [{title,text,icon}]
    specs: 'pairs',          // [{label,value}]
    compatibility: 'triples',// [{group,controls,note}]
    faq: 'pairs'             // [{q,a}]
  };
  var LIST_FIELDS = ['features', 'extra']; // строковые массивы: редактируются построчно

  /* ------------------------------ состояние -------------------------------- */

  var state = { data: null, baseline: null, dirty: false };

  function markDirty() {
    state.dirty = true;
    $('#save-state').textContent = 'Есть несохранённые изменения';
  }

  function showAlert(sel, message, isError) {
    var box = $(sel);
    if (!box) return;
    box.textContent = message;
    box.hidden = !message;
    box.className = 'alert ' + (isError ? 'alert-danger' : 'alert-success');
    if (!isError && message) setTimeout(function () { box.hidden = true; }, 5000);
  }

  /* --------------------------- сборка payload ------------------------------ */

  /** Собираем контентную часть из текущего состояния (без orders) */
  function buildPayload() {
    var d = state.data;
    return {
      site: d.site,
      pages: d.pages,
      products: d.products,
      filters: d.filters,
      calculator: d.calculator
    };
  }

  /* ------------------------------ КОНТАКТЫ --------------------------------- */

  function renderSiteFields() {
    var host = $('#site-fields');
    host.textContent = '';
    SITE_FIELDS.forEach(function (field) {
      var value = getPath(state.data.site, field.path);
      host.appendChild(buildField(field, value, function (next) {
        setPath(state.data.site, field.path, next);
        markDirty();
      }));
    });
  }

  function buildField(field, value, onChange) {
    var wrap = el('div', 'field' + (field.type === 'textarea' ? ' field-wide' : ''));
    var id = 'af-' + field.path.replace(/[^a-z0-9]/gi, '-');
    var label = el('label', null, field.label);
    label.setAttribute('for', id);
    wrap.appendChild(label);

    var input;
    if (field.type === 'textarea') {
      input = document.createElement('textarea');
      input.rows = 3;
      input.value = value == null ? '' : value;
    } else {
      input = document.createElement('input');
      input.type = field.type === 'number' ? 'number' : 'text';
      input.value = value == null ? '' : value;
    }
    input.id = id;
    if (field.placeholder) input.placeholder = field.placeholder;
    input.addEventListener('input', function () { onChange(input.value); });
    wrap.appendChild(input);
    return wrap;
  }

  /* -------------------------------- ТЕКСТЫ --------------------------------- */

  function renderPageSelect() {
    var select = $('#page-select');
    select.textContent = '';
    Object.keys(PAGE_LABELS).forEach(function (key) {
      if (!state.data.pages[key]) return;
      var opt = document.createElement('option');
      opt.value = key;
      opt.textContent = PAGE_LABELS[key];
      select.appendChild(opt);
    });
    select.onchange = renderPageFields;
    renderPageFields();
  }

  function renderPageFields() {
    var key = $('#page-select').value || 'home';
    var page = state.data.pages[key] || {};
    var host = $('#page-fields');
    host.textContent = '';

    Object.keys(page).forEach(function (fieldKey) {
      var value = page[fieldKey];

      if (Array.isArray(value)) {
        host.appendChild(renderArrayEditor(key, fieldKey, value));
        return;
      }
      if (value && typeof value === 'object') return; // вложенные объекты не трогаем

      var isNumber = /price/i.test(fieldKey);
      var field = {
        path: fieldKey,
        label: (FIELD_LABELS[fieldKey] || fieldKey),
        type: isNumber ? 'number' : (LONG_KEYS.test(fieldKey) ? 'textarea' : 'text')
      };
      host.appendChild(buildField(field, value, function (next) {
        state.data.pages[key][fieldKey] = isNumber ? (Math.round(Number(next)) || 0) : next;
        markDirty();
      }));
    });
  }

  /** Массивы: features/extra — построчно; specs/faq/compatibility/advantages — таблицей */
  function renderArrayEditor(pageKey, fieldKey, list) {
    var card = el('div', 'array-editor field-wide');
    var title = el('h3', 'array-title', (FIELD_LABELS[fieldKey] || fieldKey) + ' (' + list.length + ')');
    card.appendChild(title);

    // Списки строк (features, extra и любые новые массивы текста) — построчный textarea
    var isStringList = LIST_FIELDS.indexOf(fieldKey) > -1 || list.every(function (x) { return typeof x === 'string'; });
    var mode = isStringList ? 'strings' : (ARRAY_MODE[fieldKey] || 'pairs');

    if (mode === 'strings') {
      var ta = document.createElement('textarea');
      ta.rows = Math.max(3, list.length);
      ta.value = list.join('\n');
      ta.setAttribute('aria-label', 'Список: ' + fieldKey + ', один элемент на строку');
      ta.addEventListener('input', function () {
        state.data.pages[pageKey][fieldKey] = ta.value.split('\n').map(function (s) { return s.trim(); }).filter(Boolean);
        markDirty();
      });
      card.appendChild(ta);
      card.appendChild(el('p', 'panel-note', 'Каждая строка — отдельный пункт списка.'));
      return card;
    }

    var columns = mode === 'objects' ? [['icon', 'Иконка'], ['title', 'Заголовок'], ['text', 'Текст']]
      : mode === 'pairs' ? [['label', 'Параметр'], ['value', 'Значение']]
      : [['group', 'Группа'], ['controls', 'Стойки/станки'], ['note', 'Примечание']];
    if (mode === 'objects') columns = [['title', 'Заголовок'], ['text', 'Текст'], ['icon', 'Иконка']];

    var table = el('table', 'admin-table inner-table');
    var thead = document.createElement('thead');
    var hrow = document.createElement('tr');
    columns.forEach(function (c) { hrow.appendChild(el('th', null, c[1])); });
    hrow.appendChild(el('th', 'col-del', ''));
    thead.appendChild(hrow);
    table.appendChild(thead);

    var tbody = document.createElement('tbody');
    list.forEach(function (item, index) {
      var tr = document.createElement('tr');
      columns.forEach(function (c) {
        var td = document.createElement('td');
        var input = document.createElement('input');
        input.type = 'text';
        input.value = item[c[0]] == null ? '' : item[c[0]];
        input.setAttribute('aria-label', c[1] + ' ' + (index + 1));
        input.addEventListener('input', function () {
          state.data.pages[pageKey][fieldKey][index][c[0]] = input.value;
          markDirty();
        });
        td.appendChild(input);
        tr.appendChild(td);
      });
      var delTd = document.createElement('td');
      delTd.className = 'col-del';
      var del = el('button', 'btn-icon', '×');
      del.type = 'button';
      del.title = 'Удалить пункт';
      del.setAttribute('aria-label', 'Удалить пункт ' + (index + 1));
      del.addEventListener('click', function () {
        state.data.pages[pageKey][fieldKey].splice(index, 1);
        markDirty();
        renderPageFields();
      });
      delTd.appendChild(del);
      tr.appendChild(delTd);
      tbody.appendChild(tr);
    });
    table.appendChild(tbody);
    card.appendChild(table);

    var add = el('button', 'btn btn-ghost btn-sm', '+ Добавить пункт');
    add.type = 'button';
    add.addEventListener('click', function () {
      var blank = {};
      columns.forEach(function (c) { blank[c[0]] = ''; });
      state.data.pages[pageKey][fieldKey].push(blank);
      markDirty();
      renderPageFields();
    });
    card.appendChild(add);
    return card;
  }

  /* ------------------------------ КАЛЬКУЛЯТОР ------------------------------- */

  function renderCalcTexts() {
    var host = $('#calc-text-fields');
    host.textContent = '';
    CALC_TEXT_FIELDS.forEach(function (field) {
      host.appendChild(buildField(field, getPath(state.data.calculator, field.path), function (next) {
        setPath(state.data.calculator, field.path, next);
        markDirty();
      }));
    });
  }

  function renderOptionTable(kind, bodySel) {
    var list = kind === 'base' ? state.data.calculator.baseOptions : state.data.calculator.modules;
    var host = $(bodySel);
    host.textContent = '';

    list.forEach(function (item, index) {
      var tr = document.createElement('tr');

      tr.appendChild(cellInput(item.id, 'text', 'id (латиницей)', function (v) {
        item.id = v; markDirty();
      }, true));

      tr.appendChild(cellInput(item.label, 'text', 'Название', function (v) { item.label = v; markDirty(); }));
      tr.appendChild(cellInput(item.hint || '', 'text', 'Подсказка', function (v) { item.hint = v; markDirty(); }));

      var priceTd = document.createElement('td');
      priceTd.className = 'col-num';
      var price = document.createElement('input');
      price.type = 'number';
      price.min = '0';
      price.step = '1000';
      price.value = item.price;
      price.setAttribute('aria-label', 'Цена: ' + item.label);
      price.addEventListener('input', function () {
        item.price = Math.max(0, Math.round(Number(price.value) || 0));
        markDirty();
      });
      priceTd.appendChild(price);
      tr.appendChild(priceTd);

      var activeTd = document.createElement('td');
      activeTd.className = 'col-check';
      var check = document.createElement('input');
      check.type = 'checkbox';
      check.checked = item.active !== false;
      check.setAttribute('aria-label', 'Активен: ' + item.label);
      check.addEventListener('change', function () { item.active = check.checked; markDirty(); });
      activeTd.appendChild(check);
      tr.appendChild(activeTd);

      var delTd = document.createElement('td');
      delTd.className = 'col-del';
      var del = el('button', 'btn-icon', '×');
      del.type = 'button';
      del.title = 'Удалить позицию';
      del.setAttribute('aria-label', 'Удалить: ' + item.label);
      del.addEventListener('click', function () {
        if (!confirm('Удалить «' + item.label + '»? Конфигурации клиентов, сохранившие этот id, будут пересчитаны без него.')) return;
        list.splice(index, 1);
        markDirty();
        renderCalculator();
      });
      delTd.appendChild(del);
      tr.appendChild(delTd);

      host.appendChild(tr);
    });
  }

  function cellInput(value, type, aria, onChange, mono) {
    var td = document.createElement('td');
    var input = document.createElement('input');
    input.type = type;
    input.value = value == null ? '' : value;
    if (mono) input.className = 'mono';
    input.setAttribute('aria-label', aria);
    input.addEventListener('input', function () { onChange(input.value); });
    td.appendChild(input);
    return td;
  }

  function renderCalculator() {
    renderCalcTexts();
    renderOptionTable('base', '#bases-body');
    renderOptionTable('module', '#modules-body');
  }

  function uid(prefix) {
    var base = prefix + '-' + Math.random().toString(36).slice(2, 6);
    var list = state.data.calculator.baseOptions.concat(state.data.calculator.modules);
    var taken = list.map(function (x) { return x.id; });
    var id = base, i = 1;
    while (taken.indexOf(id) > -1) id = base + '-' + (++i);
    return id;
  }

  /* -------------------------------- ИЗДЕЛИЯ --------------------------------- */

  function renderProducts() {
    var host = $('#products-body');
    host.textContent = '';
    var categories = state.data.filters || [];

    state.data.products.forEach(function (p, index) {
      var tr = document.createElement('tr');
      tr.appendChild(cellInput(p.id, 'text', 'id изделия', function (v) { p.id = v; markDirty(); }, true));
      tr.appendChild(cellInput(p.name, 'text', 'Название', function (v) { p.name = v; markDirty(); }));
      tr.appendChild(cellInput(p.short, 'text', 'Краткое описание', function (v) { p.short = v; markDirty(); }));

      var catTd = document.createElement('td');
      var sel = document.createElement('select');
      sel.setAttribute('aria-label', 'Категория: ' + p.name);
      categories.forEach(function (c) {
        if (c.id === 'all') return;
        var opt = document.createElement('option');
        opt.value = c.id;
        opt.textContent = c.label;
        if (p.category === c.id) opt.selected = true;
        sel.appendChild(opt);
      });
      sel.addEventListener('change', function () {
        p.category = sel.value;
        var found = categories.filter(function (c) { return c.id === sel.value; })[0];
        p.categoryLabel = found ? found.label : '';
        markDirty();
      });
      catTd.appendChild(sel);
      tr.appendChild(catTd);

      var priceTd = document.createElement('td');
      priceTd.className = 'col-num';
      var price = document.createElement('input');
      price.type = 'number';
      price.min = '0';
      price.step = '1000';
      price.value = p.price || 0;
      price.setAttribute('aria-label', 'Цена: ' + p.name);
      price.addEventListener('input', function () { p.price = Math.max(0, Math.round(Number(price.value) || 0)); markDirty(); });
      priceTd.appendChild(price);
      tr.appendChild(priceTd);

      tr.appendChild(cellInput(p.url, 'text', 'URL страницы', function (v) { p.url = v; markDirty(); }));
      tr.appendChild(cellInput(p.image, 'text', 'URL картинки', function (v) { p.image = v; markDirty(); }));

      var featTd = document.createElement('td');
      featTd.className = 'col-check';
      var feat = document.createElement('input');
      feat.type = 'checkbox';
      feat.checked = !!p.featured;
      feat.setAttribute('aria-label', 'Показывать на главной: ' + p.name);
      feat.addEventListener('change', function () { p.featured = feat.checked; markDirty(); });
      featTd.appendChild(feat);
      tr.appendChild(featTd);

      var delTd = document.createElement('td');
      delTd.className = 'col-del';
      var del = el('button', 'btn-icon', '×');
      del.type = 'button';
      del.title = 'Удалить изделие из каталога';
      del.setAttribute('aria-label', 'Удалить: ' + p.name);
      del.addEventListener('click', function () {
        if (!confirm('Убрать «' + p.name + '» из каталога? Страница изделия останется, но карточка исчезнет из списков.')) return;
        state.data.products.splice(index, 1);
        markDirty();
        renderProducts();
      });
      delTd.appendChild(del);
      tr.appendChild(delTd);

      host.appendChild(tr);
    });
  }

  /* -------------------------------- ЗАЯВКИ ---------------------------------- */

  var STATUS_LABELS = { new: 'Новая', in_progress: 'В работе', done: 'Завершена', archived: 'Архив' };

  function loadOrders() {
    var status = $('#orders-filter').value;
    return api('/api/admin/orders?status=' + encodeURIComponent(status)).then(function (res) {
      if (res.status !== 200 || !res.json.ok) {
        showAlert('#global-error', res.json.error || 'Не удалось загрузить заявки.', true);
        return;
      }
      renderOrders(res.json.orders || []);
    });
  }

  function renderOrders(orders) {
    var host = $('#orders-body');
    host.textContent = '';
    $('#orders-empty').hidden = orders.length > 0;
    $('#orders-table').hidden = orders.length === 0;

    var totalSum = 0;
    var newCount = 0;

    orders.forEach(function (o) {
      if (o.status === 'new') newCount++;
      totalSum += Number(o.total) || 0;

      var tr = document.createElement('tr');

      tr.appendChild(el('td', 'mono', o.id));

      var dateTd = document.createElement('td');
      var dt = new Date(o.createdAt);
      dateTd.textContent = isNaN(dt.getTime()) ? String(o.createdAt || '') :
        dt.toLocaleString('ru-RU', { day: '2-digit', month: '2-digit', year: '2-digit', hour: '2-digit', minute: '2-digit' });
      tr.appendChild(dateTd);

      var contactTd = document.createElement('td');
      contactTd.appendChild(el('div', 'order-name', o.name + (o.company ? ' · ' + o.company : '')));
      var telHref = 'tel:' + String(o.phone || '').replace(/[^\d+]/g, '');
      if (/^tel:[+\d]{6,}$/.test(telHref)) {
        var telLink = el('a', 'order-phone', o.phone);
        telLink.href = telHref;
        contactTd.appendChild(telLink);
      } else {
        contactTd.appendChild(el('div', 'order-phone', o.phone || ''));
      }
      if (o.email) {
        var mailHref = 'mailto:' + String(o.email).replace(/[^\w.@%+-]/g, '');
        if (/^mailto:.+@.+\./.test(mailHref)) {
          var mailLink = el('a', 'order-mail', o.email);
          mailLink.href = mailHref;
          contactTd.appendChild(mailLink);
        }
      }
      if (o.machine) contactTd.appendChild(el('div', 'order-machine', o.machine));
      tr.appendChild(contactTd);

      var cfgTd = document.createElement('td');
      var items = o.items || o.config || [];
      if (!items.length) cfgTd.appendChild(el('span', 'muted', 'без комплектации'));
      items.forEach(function (i) {
        cfgTd.appendChild(el('div', 'order-item', i.label + ' — ' + money(i.price) + ' ₽'));
      });
      tr.appendChild(cfgTd);

      tr.appendChild(el('td', 'col-num order-total', money(o.total)));

      var statusTd = document.createElement('td');
      var select = document.createElement('select');
      select.className = 'status-select status-' + o.status;
      select.setAttribute('aria-label', 'Статус заявки №' + o.id);
      Object.keys(STATUS_LABELS).forEach(function (code) {
        var opt = document.createElement('option');
        opt.value = code;
        opt.textContent = STATUS_LABELS[code];
        if (code === o.status) opt.selected = true;
        select.appendChild(opt);
      });
      select.addEventListener('change', function () {
        select.disabled = true;
        api('/api/admin/orders/' + encodeURIComponent(o.id) + '/status', {
          method: 'POST',
          body: JSON.stringify({ status: select.value })
        }).then(function (res) {
          select.disabled = false;
          if (res.json.ok) {
            select.className = 'status-select status-' + select.value;
            showAlert('#global-ok', 'Статус заявки №' + o.id + ' обновлён.', false);
            loadOrders();
          } else {
            showAlert('#global-error', res.json.error || 'Не удалось изменить статус.', true);
          }
        });
      });
      statusTd.appendChild(select);
      tr.appendChild(statusTd);

      host.appendChild(tr);
    });

    $('#orders-summary').textContent =
      'Показано: ' + orders.length + '. Сумма по ним: ' + money(totalSum) + ' ₽. Новых: ' + newCount + '.';
    var badge = $('#orders-badge');
    var allNew = newCount;
    if ($('#orders-filter').value !== 'all') {
      // при фильтре считаем новых по всем заявкам асинхронно ниже
      api('/api/admin/orders?status=new').then(function (res) {
        allNew = res.json.ok ? (res.json.orders || []).length : 0;
        badge.textContent = allNew;
        badge.hidden = allNew === 0;
      });
    } else {
      badge.textContent = allNew;
      badge.hidden = allNew === 0;
    }
  }

  function exportCsv() {
    var status = $('#orders-filter').value;
    // CSV защищён токеном в заголовке, поэтому выгружаем через fetch + blob
    fetch('/api/admin/orders/export.csv?status=' + encodeURIComponent(status), {
      headers: { Authorization: 'Bearer ' + getToken() }
    })
      .then(function (r) {
        if (!r.ok) throw new Error('csv');
        return r.blob();
      })
      .then(function (blob) {
        var url = URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = url;
        a.download = 'virshketech-orders.csv';
        document.body.appendChild(a);
        a.click();
        a.remove();
        setTimeout(function () { URL.revokeObjectURL(url); }, 2000);
      })
      .catch(function () { showAlert('#global-error', 'Не удалось выгрузить CSV.', true); });
  }

  /* ------------------------------- табы ------------------------------------ */

  function initTabs() {
    var tabs = Array.prototype.slice.call(document.querySelectorAll('.tab'));
    tabs.forEach(function (tab, index) {
      tab.addEventListener('click', function () { activate(tab, tabs); });
      tab.addEventListener('keydown', function (e) {
        if (e.key === 'ArrowRight' || e.key === 'ArrowLeft') {
          var dir = e.key === 'ArrowRight' ? 1 : -1;
          var next = tabs[(index + dir + tabs.length) % tabs.length];
          activate(next, tabs);
          next.focus();
          e.preventDefault();
        }
      });
    });
  }

  function activate(tab, tabs) {
    tabs.forEach(function (t) {
      var on = t === tab;
      t.classList.toggle('is-active', on);
      t.setAttribute('aria-selected', on ? 'true' : 'false');
      t.tabIndex = on ? 0 : -1;
      var panel = document.getElementById(t.getAttribute('aria-controls'));
      if (panel) panel.hidden = !on;
    });
    if (tab.dataset.tab === 'orders') loadOrders();
  }

  /* ------------------------------ сохранение --------------------------------- */

  function saveAll() {
    showAlert('#global-error', '', true);
    $('#save-btn').disabled = true;
    $('#save-state').textContent = 'Сохраняем…';

    api('/api/admin/save', { method: 'POST', body: JSON.stringify(buildPayload()) })
      .then(function (res) {
        $('#save-btn').disabled = false;
        if (res.json.ok) {
          state.dirty = false;
          state.baseline = JSON.parse(JSON.stringify(state.data));
          $('#save-state').textContent = 'Сохранено';
          showAlert('#global-ok', 'Изменения записаны в data.json. Обновите страницу сайта, чтобы увидеть результат.', false);
          return res.json;
        }
        var msgs = res.json.errors || [res.json.error || 'Ошибка сохранения.'];
        $('#save-state').textContent = 'Не сохранено';
        showAlert('#global-error', msgs.join(' '), true);
        throw new Error('save');
      })
      .catch(function (e) {
        if (e && e.message === 'save') return;
        $('#save-state').textContent = 'Ошибка';
        showAlert('#global-error', 'Сервер недоступен.', true);
      });
  }

  function reloadFromServer() {
    if (state.dirty && !confirm('Несохранённые изменения будут потеряны. Продолжить?')) return;
    loadData();
  }

  /* ------------------------- вход / выход / загрузка -------------------------- */

  function showLogin(message) {
    $('#login-screen').hidden = false;
    $('#admin-screen').hidden = true;
    $('#logout-btn').hidden = true;
    if (message) showAlert('#login-error', message, true);
    var pass = $('#login-pass');
    if (pass) { pass.value = ''; pass.focus(); }
  }

  function forceLogin(message) {
    setToken('');
    showLogin(message || 'Сессия администратора истекла. Войдите заново.');
  }

  function afterLogin() {
    $('#login-screen').hidden = true;
    $('#admin-screen').hidden = false;
    $('#logout-btn').hidden = false;
    loadData();
  }

  function loadData() {
    return api('/api/admin/data').then(function (res) {
      if (res.status !== 200 || !res.json.ok) {
        showAlert('#global-error', res.json.error || 'Не удалось загрузить данные.', true);
        return;
      }
      state.data = res.json.data;
      state.baseline = JSON.parse(JSON.stringify(res.json.data));
      state.dirty = false;
      $('#save-state').textContent = 'Всё сохранено';

      // Гарантируем наличие разделов, которые рендерит админка
      state.data.site = state.data.site || {};
      state.data.social = state.data.site.social = (state.data.site.social || {});
      state.data.pages = state.data.pages || {};
      state.data.products = state.data.products || [];
      state.data.filters = state.data.filters || [{ id: 'all', label: 'Все изделия' }];
      state.data.calculator = state.data.calculator || {};
      state.data.calculator.baseOptions = state.data.calculator.baseOptions || [];
      state.data.calculator.modules = state.data.calculator.modules || [];
      state.data.orders = state.data.orders || [];

      renderSiteFields();
      renderPageSelect();
      renderCalculator();
      renderProducts();
      loadOrders();
    });
  }

  function initEvents() {
    $('#login-form').addEventListener('submit', function (e) {
      e.preventDefault();
      var password = $('#login-pass').value;
      if (!password) { showAlert('#login-error', 'Введите пароль.', true); return; }
      $('#login-btn').disabled = true;
      fetch('/api/admin/login', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ password: password })
      })
        // Если тело не-JSON (например, прокси вернул HTML) — статус берём из ответа
        .then(function (r) {
          return r.json()
            .catch(function () { return { error: 'Сервер ответил некорректно.' }; })
            .then(function (j) { return { status: r.status, json: j }; });
        })
        .then(function (res) {
          $('#login-btn').disabled = false;
          if (res.status === 200 && res.json.ok) {
            setToken(res.json.token);
            showAlert('#login-error', '', true);
            afterLogin();
          } else {
            showAlert('#login-error', res.json.error || 'Неверный пароль.', true);
          }
        })
        .catch(function () {
          $('#login-btn').disabled = false;
          showAlert('#login-error', 'Сервер недоступен.', true);
        });
    });

    $('#logout-btn').addEventListener('click', function () {
      api('/api/admin/logout', { method: 'POST' }).finally(function () { forceLogin('Вы вышли из админки.'); });
    });

    $('#save-btn').addEventListener('click', saveAll);
    $('#reload-btn').addEventListener('click', reloadFromServer);

    $$('[data-add]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var isBase = btn.dataset.add === 'base';
        var item = { id: uid(isBase ? 'base' : 'mod'), label: 'Новая позиция', hint: '', price: 0, active: true };
        (isBase ? state.data.calculator.baseOptions : state.data.calculator.modules).push(item);
        markDirty();
        renderCalculator();
      });
    });

    $('#add-product').addEventListener('click', function () {
      var firstCat = (state.data.filters || []).filter(function (c) { return c.id !== 'all'; })[0];
      state.data.products.push({
        id: uid('prod'),
        name: 'Новое изделие',
        category: firstCat ? firstCat.id : 'module',
        categoryLabel: firstCat ? firstCat.label : 'Модуль автоматизации',
        price: 0,
        priceNote: 'цена по запросу',
        short: 'Краткое описание — отредактируйте.',
        image: '/assets/img/placeholder.svg',
        url: '/products.html',
        featured: false
      });
      markDirty();
      renderProducts();
    });

    $('#orders-filter').addEventListener('change', loadOrders);
    $('#refresh-orders').addEventListener('click', loadOrders);
    $('#export-csv').addEventListener('click', function (e) { e.preventDefault(); exportCsv(); });

    window.addEventListener('beforeunload', function (e) {
      if (state.dirty) { e.preventDefault(); e.returnValue = ''; }
    });

    // Ctrl/Cmd+S — сохранить (браузерное сохранение страницы нам не нужно)
    document.addEventListener('keydown', function (e) {
      if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') { e.preventDefault(); saveAll(); }
    });
  }

  function boot() {
    initTabs();
    initEvents();
    if (getToken()) {
      api('/api/admin/session').then(function (res) {
        if (res.status === 200 && res.json.ok) afterLogin(); else showLogin();
      });
    } else {
      showLogin();
    }
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
  else boot();
})();
