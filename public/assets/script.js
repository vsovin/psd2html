/**
 * VirshkeTech — клиентский JS сайта (vanilla, без сборки).
 *
 * Ответственности:
 *  1) Загрузка контента из GET /api/data и биндинг в DOM по data-bind / data-bind-href / data-bind-src.
 *     Выводим ТОЛЬКО через textContent / setAttribute с проверкой схемы URL -> XSS исключён.
 *  2) Рендер динамических блоков: преимущества, карточки изделий, фильтр каталога, FAQ, спецификации.
 *  3) Калькулятор комплектации: живой пересчёт, сохранение выбора, переход в заявку.
 *  4) Форма заявки: восстановление конфигурации, маска телефона, валидация, POST /api/order.
 */
(function () {
  'use strict';

  var STORAGE_KEY = 'vt_config';
  var DATA = null; // кэш ответа /api/data для всех модулей страницы

  /* --------------------------------------------------------------------- */
  /* 0. Мелкие утилиты                                                      */
  /* --------------------------------------------------------------------- */

  function $(sel, root) { return (root || document).querySelector(sel); }
  function $$(sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); }

  function el(tag, className, text) {
    var node = document.createElement(tag);
    if (className) node.className = className;
    if (text != null) node.textContent = text; // textContent, а не innerHTML
    return node;
  }

  /** Число -> «370 000 ₽» с неразрывными пробелами в разрядах */
  function money(value, currency) {
    var n = Math.round(Number(value) || 0);
    var s = String(n).replace(/\B(?=(\d{3})+(?!\d))/g, '\u00A0');
    return s + ' ' + (currency || (DATA && DATA.calculator && DATA.calculator.currency) || '₽');
  }

  /** Разрешаем только относительные http(s)-ссылки (запрет javascript:, data:) */
  function safeUrl(url) {
    var s = String(url || '').trim();
    if (!s) return '';
    if (s.charAt(0) === '/') return s;
    return /^https?:\/\//i.test(s) ? s : '';
  }

  function getPath(obj, dotted) {
    return dotted.split('.').reduce(function (acc, key) {
      return acc == null ? undefined : acc[key];
    }, obj);
  }

  function readStorage() {
    try { return JSON.parse(localStorage.getItem(STORAGE_KEY) || 'null'); } catch (e) { return null; }
  }
  function writeStorage(cfg) {
    try { localStorage.setItem(STORAGE_KEY, JSON.stringify(cfg)); } catch (e) { /* private mode */ }
  }

  /* --------------------------------------------------------------------- */
  /* 1. Биндинг статичного контента                                         */
  /* --------------------------------------------------------------------- */

  function bindStatic(data) {
    $$('[data-bind]').forEach(function (node) {
      var value = getPath(data, node.getAttribute('data-bind'));
      if (typeof value === 'number') node.textContent = money(value);
      else if (value != null && String(value).trim() !== '') node.textContent = String(value);
    });

    $$('[data-bind-href]').forEach(function (node) {
      var spec = node.getAttribute('data-bind-href'); // "site.phoneRaw" или "tel:site.phoneRaw"
      var prefix = '';
      var path = spec;
      var colon = spec.indexOf(':');
      if (colon > -1 && /^[a-z]+$/i.test(spec.slice(0, colon))) {
        prefix = spec.slice(0, colon + 1);
        path = spec.slice(colon + 1);
      }
      var raw = getPath(data, path);
      if (raw == null || String(raw).trim() === '') return;
      var href = String(raw).trim();
      if (prefix === 'tel:') href = 'tel:' + href.replace(/[^\d+]/g, '');
      else if (prefix) href = prefix + href;
      else {
        var ok = safeUrl(href);
        if (!ok) return;
        href = ok;
      }
      node.setAttribute('href', href);
    });

    $$('[data-bind-src]').forEach(function (node) {
      var url = safeUrl(getPath(data, node.getAttribute('data-bind-src')));
      if (url) node.setAttribute('src', url);
    });

    $$('[data-bind-alt]').forEach(function (node) {
      var text = getPath(data, node.getAttribute('data-bind-alt'));
      if (text != null) node.setAttribute('alt', String(text));
    });

    document.title = (getPath(data, 'pages.' + (document.body.dataset.page || 'home') + '.seoTitle') ||
      document.title);
  }

  /* --------------------------------------------------------------------- */
  /* 2. Динамические блоки главной / каталога / карточек                    */
  /* --------------------------------------------------------------------- */

  function renderAdvantages(data) {
    var host = $('#advantages-grid');
    if (!host) return;
    var list = getPath(data, 'pages.home.advantages') || [];
    host.textContent = '';
    list.forEach(function (item) {
      var card = el('article', 'feature-card');
      card.appendChild(el('span', 'feature-icon', item.icon || '•'));
      card.appendChild(el('h3', 'feature-title', item.title || ''));
      card.appendChild(el('p', 'feature-text', item.text || ''));
      host.appendChild(card);
    });
  }

  /** Карточка изделия для сетки каталога/главной */
  function productCard(p, data) {
    var card = el('article', 'product-card');
    var link = el('a', 'product-media');
    link.href = safeUrl(p.url) || '/products.html';
    link.setAttribute('aria-label', p.name);
    var img = el('img');
    img.src = safeUrl(p.image) || '/assets/img/placeholder.svg';
    img.alt = p.name;
    img.loading = 'lazy';
    link.appendChild(img);
    card.appendChild(link);

    var body = el('div', 'product-body');
    body.appendChild(el('span', 'product-cat', p.categoryLabel || ''));
    body.appendChild(el('h3', 'product-name', p.name));
    body.appendChild(el('p', 'product-short', p.short || ''));

    var priceText = p.price > 0
      ? (p.priceNote ? 'от ' + money(p.price) : money(p.price))
      : (p.priceNote || 'цена по запросу');
    body.appendChild(el('p', 'product-price', priceText));

    var actions = el('div', 'product-actions');
    var more = el('a', 'btn btn-ghost btn-sm', 'Подробнее');
    more.href = safeUrl(p.url) || '/products.html';
    actions.appendChild(more);
    var order = el('a', 'btn btn-primary btn-sm', 'Заказать');
    order.href = '/order.html?product=' + encodeURIComponent(p.id);
    actions.appendChild(order);
    body.appendChild(actions);

    card.appendChild(body);
    return card;
  }

  function renderHomeProducts(data) {
    var host = $('#home-products');
    if (!host) return;
    var list = (data.products || []).filter(function (p) { return p.featured; }).slice(0, 4);
    host.textContent = '';
    list.forEach(function (p) { host.appendChild(productCard(p, data)); });
  }

  /** Превью баз/модулей в тизере калькулятора на главной */
  function renderCalcTeaser(data) {
    var host = $('#teaser-options');
    if (!host) return;
    var calc = data.calculator || {};
    host.textContent = '';
    (calc.baseOptions || []).filter(function (b) { return b.active !== false; }).forEach(function (b) {
      var li = el('li', 'teaser-item');
      li.appendChild(el('span', 'teaser-label', b.label));
      li.appendChild(el('span', 'teaser-price', money(b.price)));
      host.appendChild(li);
    });
    (calc.modules || []).filter(function (m) { return m.active !== false; }).forEach(function (m) {
      var li = el('li', 'teaser-item teaser-item-mod');
      li.appendChild(el('span', 'teaser-label', '+ ' + m.label));
      li.appendChild(el('span', 'teaser-price', money(m.price)));
      host.appendChild(li);
    });
    var demo = $('#teaser-demo-total');
    if (demo) {
      var base = (calc.baseOptions || [])[0];
      var mod = (calc.modules || [])[0];
      demo.textContent = base ? money(base.price + (mod ? mod.price : 0)) : '—';
    }
  }

  /* ---------------------------- Каталог ---------------------------------- */

  function renderCatalog(data) {
    var host = $('#catalog-grid');
    var filterHost = $('#catalog-filters');
    if (!host || !filterHost) return;

    var products = data.products || [];
    var filters = data.filters || [{ id: 'all', label: 'Все изделия' }];

    filterHost.textContent = '';
    filters.forEach(function (f, index) {
      var btn = el('button', 'chip' + (index === 0 ? ' is-active' : ''), f.label);
      btn.type = 'button';
      btn.dataset.filter = f.id;
      btn.setAttribute('aria-pressed', index === 0 ? 'true' : 'false');
      btn.addEventListener('click', function () {
        $$('.chip', filterHost).forEach(function (c) {
          c.classList.remove('is-active');
          c.setAttribute('aria-pressed', 'false');
        });
        btn.classList.add('is-active');
        btn.setAttribute('aria-pressed', 'true');
        drawGrid(f.id);
      });
      filterHost.appendChild(btn);
    });

    function drawGrid(filterId) {
      var list = filterId && filterId !== 'all'
        ? products.filter(function (p) { return p.category === filterId; })
        : products;
      host.textContent = '';
      if (!list.length) {
        host.appendChild(el('p', 'empty-note', 'В этой категории пока нет изделий.'));
        return;
      }
      list.forEach(function (p) { host.appendChild(productCard(p, data)); });
    }

    drawGrid('all');
  }

  /* ------------------------ Страницы изделий ----------------------------- */

  function renderProductPage(data) {
    var pageKey = document.body.dataset.product; // tfo160 | chuck | tailstock | robot
    var page = pageKey && data.pages ? data.pages[pageKey] : null;
    if (!page) return;

    var specsHost = $('#specs-table');
    if (specsHost) {
      specsHost.textContent = '';
      (page.specs || []).forEach(function (row) {
        var tr = document.createElement('tr');
        var th = document.createElement('th');
        th.scope = 'row';
        th.textContent = row.label;
        var td = document.createElement('td');
        td.textContent = row.value;
        tr.appendChild(th);
        tr.appendChild(td);
        specsHost.appendChild(tr);
      });
    }

    var featHost = $('#features-list');
    if (featHost) {
      featHost.textContent = '';
      (page.features || []).forEach(function (text) {
        var li = el('li', null, text);
        featHost.appendChild(li);
      });
    }

    var compatHost = $('#compat-list');
    if (compatHost) {
      compatHost.textContent = '';
      (page.compatibility || []).forEach(function (c) {
        var card = el('article', 'compat-card');
        card.appendChild(el('h3', 'compat-title', c.group));
        card.appendChild(el('p', 'compat-controls', c.controls));
        card.appendChild(el('p', 'compat-note', c.note));
        compatHost.appendChild(card);
      });
    }

    var docsHost = $('#docs-list');
    if (docsHost) {
      var docs = page.docs || [];
      if (!docs.length) {
        var wrap = docsHost.closest('.section');
        if (wrap) wrap.hidden = true;
      } else {
        docsHost.textContent = '';
        docs.forEach(function (d) {
          var a = el('a', 'doc-link', d.label);
          var url = safeUrl(d.url);
          if (url) { a.href = url; a.target = '_blank'; a.rel = 'noopener noreferrer'; }
          else { a.href = '#'; a.setAttribute('aria-disabled', 'true'); a.title = 'Файл ещё не загружен'; }
          docsHost.appendChild(a);
        });
      }
    }

    var faqHost = $('#faq-list');
    if (faqHost) {
      var faq = page.faq || [];
      if (!faq.length) {
        var sec = faqHost.closest('.section');
        if (sec) sec.hidden = true;
      } else {
        faqHost.textContent = '';
        faq.forEach(function (item) {
          var det = el('details', 'faq-item');
          var sum = el('summary', 'faq-q', item.q);
          det.appendChild(sum);
          det.appendChild(el('p', 'faq-a', item.a));
          faqHost.appendChild(det);
        });
      }
    }

    // Кнопка «Добавить в комплектацию» ведёт в калькулятор с предвыбранным модулем
    var addBtn = $('#add-to-calc');
    if (addBtn) {
      var moduleId = document.body.dataset.moduleId;
      if (moduleId) addBtn.href = '/calculator.html?add=' + encodeURIComponent(moduleId);
    }

    // Блок «Другие изделия»: показываем всё, кроме текущего изделия
    var otherHost = $('#other-products');
    if (otherHost && pageKey) {
      otherHost.textContent = '';
      (data.products || [])
        .filter(function (p) { return p.id !== pageKey; })
        .slice(0, 4)
        .forEach(function (p) { otherHost.appendChild(productCard(p, data)); });
    }
  }

  /* --------------------------------------------------------------------- */
  /* 3. Калькулятор                                                         */
  /* --------------------------------------------------------------------- */

  function initCalculator(data) {
    var form = $('#calc-form');
    if (!form) return;

    var calc = data.calculator || {};
    var bases = (calc.baseOptions || []).filter(function (b) { return b.active !== false; });
    var modules = (calc.modules || []).filter(function (m) { return m.active !== false; });

    var baseHost = $('#base-options');
    var moduleHost = $('#module-options');
    var summaryHost = $('#summary-list');
    var totalHost = $('#total-value');
    var errorHost = $('#base-error');
    var currency = calc.currency || '₽';

    // Предвыбор модуля из ссылки вида /calculator.html?add=chuck
    var presetModule = new URLSearchParams(location.search).get('add');

    function optionRow(item, type, checked) {
      var id = 'opt-' + type + '-' + item.id;
      var row = el('label', 'option-row' + (checked ? ' is-checked' : ''));
      row.setAttribute('for', id);

      var input = document.createElement(type === 'base' ? 'input' : 'input');
      input.type = type === 'base' ? 'radio' : 'checkbox';
      input.name = type === 'base' ? 'calc-base' : 'calc-module';
      input.id = id;
      input.value = item.id;
      input.checked = !!checked;

      var box = el('span', 'option-box');
      box.setAttribute('aria-hidden', 'true');

      var text = el('span', 'option-text');
      text.appendChild(el('span', 'option-label', item.label));
      if (item.hint) text.appendChild(el('span', 'option-hint', item.hint));

      var price = el('span', 'option-price', (type === 'module' ? '+' : '') + money(item.price, currency));

      row.appendChild(input);
      row.appendChild(box);
      row.appendChild(text);
      row.appendChild(price);

      input.addEventListener('change', update);
      return row;
    }

    baseHost.textContent = '';
    bases.forEach(function (b, i) {
      baseHost.appendChild(optionRow(b, 'base', i === 0 && !readStorage()));
    });

    moduleHost.textContent = '';
    modules.forEach(function (m) {
      moduleHost.appendChild(optionRow(m, 'module', m.id === presetModule));
    });

    function currentSelection() {
      var baseInput = $('input[name="calc-base"]:checked', form);
      var moduleInputs = $$('input[name="calc-module"]:checked', form);
      return {
        base: baseInput ? baseInput.value : '',
        modules: moduleInputs.map(function (i) { return i.value; })
      };
    }

    function itemsOf(selection) {
      var items = [];
      var base = bases.filter(function (b) { return b.id === selection.base; })[0];
      if (base) items.push({ id: base.id, label: base.label, price: base.price, type: 'base' });
      selection.modules.forEach(function (mid) {
        var m = modules.filter(function (x) { return x.id === mid; })[0];
        if (m) items.push({ id: m.id, label: m.label, price: m.price, type: 'module' });
      });
      return items;
    }

    function update() {
      var selection = currentSelection();
      var items = itemsOf(selection);
      var total = items.reduce(function (sum, i) { return sum + i.price; }, 0);

      $$('.option-row', form).forEach(function (row) {
        var input = $('input', row);
        if (input) row.classList.toggle('is-checked', input.checked);
      });

      summaryHost.textContent = '';
      if (!items.length) {
        summaryHost.appendChild(el('li', 'summary-empty', 'Пока ничего не выбрано — отметьте базу слева.'));
      } else {
        items.forEach(function (i) {
          var li = el('li', 'summary-item');
          li.appendChild(el('span', 'summary-label', i.label));
          li.appendChild(el('span', 'summary-price', money(i.price, currency)));
          summaryHost.appendChild(li);
        });
      }

      totalHost.textContent = money(total, currency);
      errorHost.hidden = Boolean(selection.base);

      writeStorage({ base: selection.base, modules: selection.modules, savedAt: Date.now() });
    }

    // Восстановление последнего выбора (пользователь вернулся на страницу)
    var stored = readStorage();
    if (stored && stored.base) {
      var restoreBase = $('input[value="' + CSS.escape(stored.base) + '"]', baseHost);
      if (restoreBase) restoreBase.checked = true;
      (stored.modules || []).forEach(function (mid) {
        var input = $('input[value="' + CSS.escape(mid) + '"]', moduleHost);
        if (input) input.checked = true;
      });
    }

    update();

    $('#to-order-btn').addEventListener('click', function () {
      var selection = currentSelection();
      if (!selection.base) {
        errorHost.hidden = false;
        var firstBase = $('input[name="calc-base"]', form);
        if (firstBase) firstBase.focus();
        return;
      }
      writeStorage({ base: selection.base, modules: selection.modules, savedAt: Date.now() });
      // Дублируем конфиг в query — переживает открытые табы и ручную печать URL
      var cfg = selection.base + (selection.modules.length ? '+' + selection.modules.join(',') : '');
      location.href = '/order.html?cfg=' + encodeURIComponent(cfg);
    });

    var resetBtn = $('#calc-reset-btn');
    if (resetBtn) {
      resetBtn.addEventListener('click', function () {
        $$('input[type="radio"], input[type="checkbox"]', form).forEach(function (i) { i.checked = false; });
        update();
      });
    }
  }

  /* --------------------------------------------------------------------- */
  /* 4. Форма заявки                                                        */
  /* --------------------------------------------------------------------- */

  var PHONE_DIGITS_MAX = 11; // 7 + 10 цифр

  /** Формат +7 (999) 999-99-99 из произвольного ввода */
  function formatPhone(value) {
    var digits = String(value).replace(/\D/g, '');
    if (!digits) return '';
    if (digits[0] === '8') digits = '7' + digits.slice(1);
    if (digits[0] !== '7') digits = '7' + digits;
    digits = digits.slice(0, PHONE_DIGITS_MAX);
    var out = '+7';
    if (digits.length > 1) out += ' (' + digits.slice(1, 4);
    if (digits.length >= 4) out += ') ' + digits.slice(4, 7);
    if (digits.length >= 7) out += '-' + digits.slice(7, 9);
    if (digits.length >= 9) out += '-' + digits.slice(9, 11);
    return out;
  }

  function phoneDigits(value) { return String(value).replace(/\D/g, ''); }

  function parseCfgParam(cfg) {
    // формат: "<base>+<mod1>,<mod2>"
    var parts = String(cfg || '').split('+');
    return {
      base: parts[0] || '',
      modules: parts[1] ? parts[1].split(',').filter(Boolean) : []
    };
  }

  function initOrderForm(data) {
    var form = $('#order-form');
    if (!form) return;

    var calc = data.calculator || {};
    var bases = (calc.baseOptions || []).filter(function (b) { return b.active !== false; });
    var modules = (calc.modules || []).filter(function (m) { return m.active !== false; });
    var currency = calc.currency || '₽';

    var boxEl = $('#config-box');
    var emptyEl = $('#config-empty');
    var listEl = $('#order-config-list');
    var totalEl = $('#order-total');
    var errEl = $('#form-error');
    var submitBtn = $('#submit-btn');

    var state = { base: '', modules: [] };

    function applyConfig(next) {
      state = { base: next.base || '', modules: (next.modules || []).slice() };
      render();
    }

    function selectedItems() {
      var items = [];
      var base = bases.filter(function (b) { return b.id === state.base; })[0];
      if (base) items.push({ id: base.id, label: base.label, price: base.price, type: 'base' });
      state.modules.forEach(function (mid) {
        var m = modules.filter(function (x) { return x.id === mid; })[0];
        if (m) items.push({ id: m.id, label: m.label, price: m.price, type: 'module' });
      });
      return items;
    }

    function render() {
      var items = selectedItems();
      var total = items.reduce(function (s, i) { return s + i.price; }, 0);

      listEl.textContent = '';
      items.forEach(function (i) {
        var li = el('li', 'summary-item');
        li.appendChild(el('span', 'summary-label', i.label));
        li.appendChild(el('span', 'summary-price', money(i.price, currency)));
        listEl.appendChild(li);
      });
      totalEl.textContent = money(total, currency);

      var hasConfig = items.length > 0;
      boxEl.hidden = !hasConfig;
      if (emptyEl) emptyEl.hidden = hasConfig;

      // Скрытые поля: config — человекочитаемый JSON, base/modules — для серверной перепроверки
      $('#f-config').value = hasConfig ? JSON.stringify(items) : '';
      $('#f-total').value = hasConfig ? String(total) : '0';
      $('#f-base').value = hasConfig ? state.base : '';
      $('#f-modules').value = hasConfig ? state.modules.join(',') : '';
      $('#f-source').value = location.search.indexOf('cfg=') > -1 ? 'calculator' : 'order-page';
    }

    // Источник конфигурации: ?cfg= > ?product= > localStorage
    var params = new URLSearchParams(location.search);
    var cfgParam = params.get('cfg');
    var productParam = params.get('product');
    if (cfgParam) {
      applyConfig(parseCfgParam(cfgParam));
    } else if (productParam) {
      // «Заказать» из карточки каталога: добавляем соответствующий модуль, если он есть в прайсе
      var matched = modules.filter(function (m) { return m.id === productParam; })[0];
      var stored = readStorage() || { base: bases.length ? bases[0].id : '', modules: [] };
      var mods = (stored.modules || []).slice();
      if (matched && mods.indexOf(matched.id) === -1) mods.push(matched.id);
      applyConfig({ base: stored.base, modules: mods });
    } else {
      var saved = readStorage();
      if (saved) applyConfig(saved); else render();
    }

    var clearBtn = $('#clear-config');
    if (clearBtn) {
      clearBtn.addEventListener('click', function () {
        writeStorage({ base: '', modules: [], savedAt: Date.now() });
        applyConfig({ base: '', modules: [] });
      });
    }

    /* ---------- маска телефона ---------- */
    var phoneInput = $('#f-phone');
    phoneInput.addEventListener('input', function () {
      var posDigits = phoneDigits(phoneInput.value.slice(0, phoneInput.selectionStart)).length;
      var formatted = formatPhone(phoneInput.value);
      phoneInput.value = formatted;
      // ставим курсор после того же количества цифр
      var count = 0, idx = 0;
      for (; idx < formatted.length; idx++) {
        if (/\d/.test(formatted[idx])) count++;
        if (count === posDigits) { idx++; break; }
      }
      if (posDigits === 0) idx = formatted.length;
      try { phoneInput.setSelectionRange(idx, idx); } catch (e) { /* ignore */ }
    });

    /* ---------- валидация ---------- */
    function setError(fieldId, message) {
      var holder = $('#e-' + fieldId);
      var input = $('#f-' + fieldId);
      if (holder) holder.textContent = message || '';
      if (input) {
        if (message) input.setAttribute('aria-invalid', 'true');
        else input.removeAttribute('aria-invalid');
      }
    }

    function validate() {
      var errors = [];
      var name = $('#f-name').value.trim();
      if (name.length < 2) { errors.push('name'); setError('name', 'Укажите имя (минимум 2 символа).'); }
      else setError('name', '');

      var digits = phoneDigits(phoneInput.value);
      if (digits.length !== PHONE_DIGITS_MAX) {
        errors.push('phone');
        setError('phone', 'Телефон должен содержать 11 цифр: +7 (___) ___-__-__.');
      } else setError('phone', '');

      var email = $('#f-email').value.trim();
      if (email && !/^[^\s@]+@[^\s@]+\.[a-zA-Zа-яА-Я]{2,}$/.test(email)) {
        errors.push('email');
        setError('email', 'Проверьте адрес e-mail.');
      } else setError('email', '');

      if (!$('#f-consent').checked) {
        errors.push('consent');
        setError('consent', 'Без согласия на обработку данных заявку отправить нельзя.');
      } else setError('consent', '');

      return errors;
    }

    function showErrors(list, serverErrors) {
      var messages = serverErrors && serverErrors.length ? serverErrors : ['Проверьте выделенные поля формы.'];
      errEl.textContent = messages.join(' ');
      errEl.hidden = false;
      if (list && list.length) {
        var first = $('#f-' + list[0]);
        if (first) first.focus();
      }
    }

    /* ---------- отправка ---------- */
    form.addEventListener('submit', function (event) {
      event.preventDefault();
      errEl.hidden = true;

      var clientErrors = validate();
      if (clientErrors.length) { showErrors(clientErrors); return; }

      var payload = {
        name: $('#f-name').value.trim(),
        company: $('#f-company').value.trim(),
        phone: phoneInput.value,
        email: $('#f-email').value.trim(),
        machine: $('#f-machine').value.trim(),
        base: state.base,
        modules: state.modules,
        consent: true,
        source: $('#f-source').value
      };

      submitBtn.disabled = true;
      submitBtn.textContent = 'Отправляем…';

      fetch('/api/order', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      })
        .then(function (response) {
          return response.json().catch(function () { return { ok: false, errors: ['Сервер не ответил корректно.'] }; })
            .then(function (json) { return { status: response.status, json: json }; });
        })
        .then(function (result) {
          if (result.status === 200 && result.json.ok) {
            writeStorage({ base: '', modules: [], savedAt: Date.now() }); // заявка отправлена — конфиг чистим
            location.href = '/thanks.html?id=' + encodeURIComponent(result.json.id || '');
            return;
          }
          var msgs = result.json.errors || (result.json.error ? [result.json.error] : []);
          if (result.status === 400) {
            // Серверные ошибки сопоставляем с полями по ключевым словам
            var fieldMap = [
              ['имя', 'name'], ['телефон', 'phone'], ['e-mail', 'email'],
              ['согласие', 'consent'], ['комплектация', 'consent']
            ];
            var badFields = [];
            msgs.forEach(function (m) {
              var lower = String(m).toLowerCase();
              fieldMap.forEach(function (pair) {
                if (lower.indexOf(pair[0]) > -1 && badFields.indexOf(pair[1]) === -1) {
                  badFields.push(pair[1]);
                  setError(pair[1], m);
                }
              });
            });
            showErrors(badFields, msgs);
          } else {
            showErrors([], msgs);
          }
          submitBtn.disabled = false;
          submitBtn.textContent = 'Отправить заявку';
        })
        .catch(function () {
          showErrors([], ['Не удалось связаться с сервером. Проверьте подключение или позвоните нам.']);
          submitBtn.disabled = false;
          submitBtn.textContent = 'Отправить заявку';
        });
    });
  }

  /* --------------------------------------------------------------------- */
  /* 5. Прочее: реквизиты, меню, страница «Спасибо»                          */
  /* --------------------------------------------------------------------- */

  /** Реквизиты на странице контактов: pages.contacts.requisites = [{label, value}] */
  function renderRequisites(data) {
    var host = $('#requisites-list');
    if (!host) return;
    var list = getPath(data, 'pages.contacts.requisites') || [];
    host.textContent = '';
    if (!list.length) {
      host.appendChild(el('p', 'empty-note', '[РЕКВИЗИТЫ] — данные ещё не внесены.'));
      return;
    }
    list.forEach(function (row) {
      var item = el('div', 'req-row');
      item.appendChild(el('span', 'req-label', row.label || ''));
      item.appendChild(el('span', 'req-value', row.value || ''));
      host.appendChild(item);
    });
  }

  /* --------------------------------------------------------------------- */

  function initBurger() {
    var burger = $('.burger');
    var nav = $('#main-nav');
    if (!burger || !nav) return;
    burger.addEventListener('click', function () {
      var open = nav.classList.toggle('is-open');
      burger.setAttribute('aria-expanded', open ? 'true' : 'false');
      burger.setAttribute('aria-label', open ? 'Закрыть меню' : 'Открыть меню');
    });
    document.addEventListener('click', function (e) {
      if (!nav.contains(e.target) && !burger.contains(e.target) && nav.classList.contains('is-open')) {
        nav.classList.remove('is-open');
        burger.setAttribute('aria-expanded', 'false');
      }
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && nav.classList.contains('is-open')) {
        nav.classList.remove('is-open');
        burger.setAttribute('aria-expanded', 'false');
        burger.focus();
      }
    });
  }

  function initThanks(data) {
    var block = $('#order-id-block');
    if (!block) return;
    var id = new URLSearchParams(location.search).get('id');
    if (id) {
      $('#order-id').textContent = '#' + id;
      block.hidden = false;
    }
    var list = $('#thanks-extra');
    if (list) {
      list.textContent = '';
      ((data.pages.thanks && data.pages.thanks.extra) || []).forEach(function (text) {
        list.appendChild(el('li', null, text));
      });
    }
  }

  /* --------------------------------------------------------------------- */
  /* Boot                                                                   */
  /* --------------------------------------------------------------------- */

  function boot() {
    initBurger();

    fetch('/api/data', { headers: { Accept: 'application/json' } })
      .then(function (r) { return r.ok ? r.json() : Promise.reject(new Error('status ' + r.status)); })
      .then(function (payload) {
        DATA = payload;
        bindStatic(DATA);
        renderAdvantages(DATA);
        renderHomeProducts(DATA);
        renderCalcTeaser(DATA);
        renderCatalog(DATA);
        renderProductPage(DATA);
        renderRequisites(DATA);
        initCalculator(DATA);
        initOrderForm(DATA);
        initThanks(DATA);
      })
      .catch(function (e) {
        // Без сервера статичная разметка остаётся с заглушками — сайт не пустой
        console.warn('[content] /api/data недоступен:', e.message);
        var notice = $('.content-offline');
        if (notice) notice.hidden = false;
        var form = $('#order-form');
        if (form) {
          var err = $('#form-error');
          if (err) { err.textContent = 'Сервер цен недоступен — отправьте заявку звонком.'; err.hidden = false; }
        }
      });
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
  else boot();
})();
