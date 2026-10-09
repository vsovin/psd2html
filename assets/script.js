/* VirshkeTech — общий скрипт сайта (IIFE, без глобальных переменных) */
(function () {
  'use strict';

  var STORAGE_KEY = 'vt_config';

  var BASES = {
    pro:   { id: 'pro',   label: 'База: для профессиональных ЧПУ', price: 370000 },
    lite:  { id: 'lite',  label: 'База: для ЧПУ начального уровня', price: 340000 },
    manual:{ id: 'manual',label: 'База: для ручного станка',        price: 370000 }
  };

  var MODULES = {
    chuck:     { id: 'chuck',     label: 'Модуль автоматизации токарного патрона', price: 120000, svg: 'm-chuck' },
    vise:      { id: 'vise',      label: 'Автозажим тисков',                       price: 95000,  svg: 'm-vise' },
    tailstock: { id: 'tailstock', label: 'Автоматическая задняя бабка',            price: 140000, svg: 'm-tailstock' },
    robot:     { id: 'robot',     label: 'Робот для подачи заготовок',             price: 350000, svg: 'm-robot' }
  };

  function fmt(n) {
    return n.toLocaleString('ru-RU') + ' ₽';
  }

  function readConfig() {
    try {
      return JSON.parse(localStorage.getItem(STORAGE_KEY)) || null;
    } catch (e) { return null; }
  }

  function writeConfig(cfg) {
    try { localStorage.setItem(STORAGE_KEY, JSON.stringify(cfg)); } catch (e) {}
  }

  /* ================= КАЛЬКУЛЯТОР ================= */
  function initCalculator(root) {
    var baseInputs = root.querySelectorAll('input[name="calc-base"]');
    var modInputs  = root.querySelectorAll('input[name="calc-mod"]');
    var summaryBox = root.querySelector('[data-role="summary-lines"]');
    var totalBox   = root.querySelector('[data-role="summary-total"]');
    var orderBtn   = root.querySelector('[data-role="order-btn"]');
    var schemeWrap = root.querySelector('[data-role="scheme"]');

    // inline SVG схемы нужен для подсветки модулей. fetch работает по http(s);
    // при file:// оставляем <img>, подсветка в этом случае недоступна (graceful degradation).
    if (schemeWrap && schemeWrap.dataset.src && window.location.protocol !== 'file:') {
      fetch(schemeWrap.dataset.src)
        .then(function (r) { return r.text(); })
        .then(function (txt) { schemeWrap.innerHTML = txt; recalc(); })
        .catch(function () {});
    }

    function selectedBase() {
      for (var i = 0; i < baseInputs.length; i++) {
        if (baseInputs[i].checked) return BASES[baseInputs[i].value];
      }
      return null;
    }

    function selectedMods() {
      var out = [];
      for (var i = 0; i < modInputs.length; i++) {
        if (modInputs[i].checked) out.push(MODULES[modInputs[i].value]);
      }
      return out;
    }

    function highlightSvg(mods) {
      if (!schemeWrap) return;
      Object.keys(MODULES).forEach(function (k) {
        var g = schemeWrap.querySelector('#' + MODULES[k].svg);
        if (g) g.classList.toggle('on', mods.indexOf(MODULES[k]) !== -1);
      });
    }

    function markOptions() {
      var all = root.querySelectorAll('.opt');
      for (var i = 0; i < all.length; i++) {
        var input = all[i].querySelector('input');
        all[i].classList.toggle('checked', input && input.checked);
      }
    }

    function recalc() {
      var base = selectedBase();
      var mods = selectedMods();
      var lines = '';
      var total = 0;

      if (base) {
        lines += '<div class="sum-line"><span>' + base.label + '</span><b>' + fmt(base.price) + '</b></div>';
        total += base.price;
      }
      mods.forEach(function (m) {
        lines += '<div class="sum-line"><span>' + m.label + '</span><b>+' + fmt(m.price) + '</b></div>';
        total += m.price;
      });

      summaryBox.innerHTML = lines || '<p class="calc-empty">Выберите базу — итог появится здесь.</p>';
      totalBox.textContent = fmt(total);
      highlightSvg(mods);
      markOptions();

      var cfg = { items: [], total: total };
      if (base) cfg.items.push({ label: base.label, price: base.price });
      mods.forEach(function (m) { cfg.items.push({ label: m.label, price: m.price }); });
      writeConfig(cfg);
    }

    for (var i = 0; i < baseInputs.length; i++) baseInputs[i].addEventListener('change', recalc);
    for (var j = 0; j < modInputs.length; j++) modInputs[j].addEventListener('change', recalc);

    if (orderBtn) {
      orderBtn.addEventListener('click', function (e) {
        e.preventDefault();
        // конфиг уже в localStorage; дублируем в query для наглядности URL
        var cfg = readConfig();
        var q = cfg && cfg.items.length ? '?cfg=' + encodeURIComponent(JSON.stringify(cfg)) : '';
        window.location.href = orderBtn.dataset.href + q;
      });
    }

    // предвыбор базы: ?base=pro|lite|manual или data-default-base у корня
    var params = new URLSearchParams(window.location.search);
    var wantBase = params.get('base') || root.getAttribute('data-default-base');
    if (wantBase && BASES[wantBase]) {
      var target = root.querySelector('input[name="calc-base"][value="' + wantBase + '"]');
      if (target) target.checked = true;
    }

    // восстановление ранее выбранных модулей
    var saved = readConfig();
    if (saved && saved.items) {
      saved.items.forEach(function (it) {
        Object.keys(MODULES).forEach(function (k) {
          if (MODULES[k].label === it.label) {
            var inp = root.querySelector('input[name="calc-mod"][value="' + k + '"]');
            if (inp) inp.checked = true;
          }
        });
      });
    }

    recalc();
  }

  /* ================= МАСКА ТЕЛЕФОНА ================= */
  function phoneMask(input) {
    input.addEventListener('input', function () {
      var digits = input.value.replace(/\D/g, '');
      if (digits[0] === '8') digits = '7' + digits.slice(1);
      if (digits === '') { input.value = ''; return; }
      if (digits[0] !== '7') digits = '7' + digits;
      digits = digits.slice(0, 11);

      var out = '+7';
      if (digits.length > 1) out += ' (' + digits.slice(1, 4);
      if (digits.length >= 4) out += ')';
      if (digits.length > 4) out += ' ' + digits.slice(4, 7);
      if (digits.length > 7) out += '-' + digits.slice(7, 9);
      if (digits.length > 9) out += '-' + digits.slice(9, 11);
      input.value = out;
    });
  }

  /* ================= ФОРМА ЗАКАЗА ================= */
  function getOrderConfig() {
    var params = new URLSearchParams(window.location.search);
    var fromUrl = params.get('cfg');
    if (fromUrl) {
      try { return JSON.parse(fromUrl); } catch (e) {}
    }
    return readConfig();
  }

  function renderConfigStrip(root) {
    var strip = root.querySelector('[data-role="config-strip"]');
    var hidden = root.querySelector('input[name="config"]');
    if (!strip) return;
    var cfg = getOrderConfig();

    if (!cfg || !cfg.items || !cfg.items.length) {
      strip.innerHTML = '<strong>Конфигурация не выбрана.</strong> ' +
        '<a href="' + root.dataset.calcPath + '">Открыть калькулятор</a>';
      if (hidden) hidden.value = '';
      return;
    }
    var lis = cfg.items.map(function (i) {
      return '<li>' + i.label + ' — ' + fmt(i.price) + '</li>';
    }).join('');
    strip.innerHTML = '<strong>' + cfg.items.length + ' поз. на сумму ' + fmt(cfg.total) + '</strong>' +
      '<ul>' + lis + '</ul>' +
      '<a href="' + root.dataset.calcPath + '">Изменить конфигурацию</a>';
    if (hidden) hidden.value = JSON.stringify(cfg);
  }

  function validateField(field) {
    var input = field.querySelector('input, textarea');
    if (!input) return true;
    var ok = true;
    var val = input.value.trim();

    if (input.required && !val) ok = false;
    if (ok && input.type === 'tel' && val) ok = val.replace(/\D/g, '').length === 11;
    if (ok && input.type === 'email' && val) ok = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(val);
    if (ok && input.type === 'checkbox') ok = input.checked;

    field.classList.toggle('invalid', !ok);
    return ok;
  }

  function initOrderForm(root) {
    renderConfigStrip(root);

    var tel = root.querySelector('input[type="tel"]');
    if (tel) phoneMask(tel);

    var form = root.querySelector('form[data-order-form]');
    if (!form) return;

    form.querySelectorAll('.field').forEach(function (f) {
      var input = f.querySelector('input, textarea');
      if (!input) return;
      input.addEventListener('blur', function () { validateField(f); });
      input.addEventListener('input', function () {
        if (f.classList.contains('invalid')) validateField(f);
      });
    });

    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var fields = form.querySelectorAll('.field');
      var allOk = true;
      fields.forEach(function (f) {
        if (!validateField(f)) allOk = false;
      });
      if (!allOk) {
        var firstBad = form.querySelector('.field.invalid input, .field.invalid textarea');
        if (firstBad) firstBad.focus();
        return;
      }

      var payload = {
        name: form.querySelector('[name="name"]').value.trim(),
        company: form.querySelector('[name="company"]').value.trim(),
        phone: form.querySelector('[name="phone"]').value.trim(),
        email: form.querySelector('[name="email"]').value.trim(),
        machine: form.querySelector('[name="machine"]').value.trim(),
        config: form.querySelector('[name="config"]').value,
        ts: new Date().toISOString()
      };

      var submitBtn = form.querySelector('button[type="submit"]');
      submitBtn.disabled = true;
      submitBtn.textContent = 'Отправляем…';

      // Заглушка API: при открытии с file:// fetch на /api/order недоступен —
      // считаем отправку успешной и редиректим на /thanks/
      var thanksUrl = root.dataset.thanksPath;
      function done() { window.location.href = thanksUrl; }

      fetch('/api/order', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      }).then(function (r) {
        if (!r.ok) throw new Error('bad status');
        return r.json().catch(function () { return {}; });
      }).then(done).catch(function () {
        // dev-режим (file:// или отсутствие бэкенда): имитируем успех
        setTimeout(done, 300);
      });
    });
  }

  /* ================= ИНИЦИАЛИЗАЦИЯ ================= */
  document.addEventListener('DOMContentLoaded', function () {
    var calc = document.querySelector('[data-calculator]');
    if (calc) initCalculator(calc);

    var orderRoot = document.querySelector('[data-order-root]');
    if (orderRoot) initOrderForm(orderRoot);

    // активная пилюля: резолвим href относительно текущей страницы и сравниваем пути
    var here = new URL(window.location.href).pathname.replace(/\/index\.html$/, '/');
    document.querySelectorAll('.aud-switch a').forEach(function (a) {
      var target = new URL(a.href, window.location.href).pathname.replace(/\/index\.html$/, '/');
      if (target === here) a.classList.add('active');
    });
  });
})();
