/* VirshkeTech — скрипт темы WordPress (IIFE, без глобальных переменных).
 * Данные калькулятора и endpoint — из window.VirshkeTech (wp_localize_script). */
(function () {
  'use strict';

  var D = window.VirshkeTech || {};
  var STORAGE_KEY = 'vt_config';

  function fmt(n) {
    return (Number(n) || 0).toLocaleString('ru-RU') + ' ₽';
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

    // Модули берём из data-атрибутов разметки (PHP рендерит их из CPT calc_module),
    // поэтому добавление/выключение модулей в админке не требует правки JS.
    function baseOf(input) {
      var price = parseInt(D.basePrices ? D.basePrices[input.value] : input.dataset.price, 10) || 0;
      var labelEl = input.closest('.opt').querySelector('.opt-name');
      return { id: input.value, label: 'База: ' + labelEl.textContent.trim(), price: price };
    }

    function modOf(input) {
      return {
        id: input.value,
        label: input.dataset.label || input.closest('.opt').querySelector('.opt-name').textContent.trim(),
        price: parseInt(input.dataset.price, 10) || 0,
        svg: input.dataset.svg || ''
      };
    }

    // inline SVG схемы нужен для подсветки модулей (fetch работает по http(s)).
    if (schemeWrap && schemeWrap.dataset.src) {
      fetch(schemeWrap.dataset.src)
        .then(function (r) { return r.text(); })
        .then(function (txt) { schemeWrap.innerHTML = txt; recalc(); })
        .catch(function () {});
    }

    function selectedBase() {
      for (var i = 0; i < baseInputs.length; i++) {
        if (baseInputs[i].checked) return baseOf(baseInputs[i]);
      }
      return null;
    }

    function selectedMods() {
      var out = [];
      for (var i = 0; i < modInputs.length; i++) {
        if (modInputs[i].checked) out.push(modOf(modInputs[i]));
      }
      return out;
    }

    function highlightSvg(mods) {
      if (!schemeWrap || !schemeWrap.querySelector('svg')) return;
      mods.forEach(function (m) {
        var g = m.svg ? schemeWrap.querySelector('#' + m.svg) : null;
        if (g) g.classList.add('on');
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

      // перед подсветкой сбрасываем прежние состояния
      if (schemeWrap && schemeWrap.querySelector('svg')) {
        schemeWrap.querySelectorAll('g.on').forEach(function (g) { g.classList.remove('on'); });
      }
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
        var cfg = readConfig();
        var q = cfg && cfg.items.length ? '?cfg=' + encodeURIComponent(JSON.stringify(cfg)) : '';
        window.location.href = orderBtn.dataset.href + q;
      });
    }

    // предвыбор базы: ?base=pro|lite|manual или data-default-base у корня
    var params = new URLSearchParams(window.location.search);
    var wantBase = params.get('base') || root.getAttribute('data-default-base');
    if (wantBase) {
      var target = root.querySelector('input[name="calc-base"][value="' + CSS.escape(wantBase) + '"]');
      if (target) target.checked = true;
    }

    // восстановление ранее выбранных модулей (по label из сохранённого конфига)
    var saved = readConfig();
    if (saved && saved.items) {
      saved.items.forEach(function (it) {
        for (var k = 0; k < modInputs.length; k++) {
          if (modOf(modInputs[k]).label === it.label) modInputs[k].checked = true;
        }
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
        '<a href="' + (root.dataset.calcPath || '/') + '">Открыть калькулятор</a>';
      if (hidden) hidden.value = '';
      return;
    }
    var lis = cfg.items.map(function (i) {
      return '<li>' + i.label + ' — ' + fmt(i.price) + '</li>';
    }).join('');
    strip.innerHTML = '<strong>' + cfg.items.length + ' поз. на сумму ' + fmt(cfg.total) + '</strong>' +
      '<ul>' + lis + '</ul>' +
      '<a href="' + (root.dataset.calcPath || '/') + '">Изменить конфигурацию</a>';
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

  function sendOrder(payload) {
    // Основной путь — REST темы; при сетевой ошибке REST — admin-ajax.
    var restUrl = (D.restUrl || '') + '/order';
    return fetch(restUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': D.nonce || '' },
      body: JSON.stringify(payload)
    }).then(function (r) {
      if (!r.ok) throw new Error('REST ' + r.status);
      return r.json();
    }).catch(function () {
      var body = new URLSearchParams();
      Object.keys(payload).forEach(function (k) { body.append(k, payload[k]); });
      body.append('action', 'vtk_submit_order');
      return fetch(D.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body })
        .then(function (r) { return r.json(); })
        .then(function (j) {
          if (!j || !j.success) throw new Error((j && j.data && j.data.message) || 'Ошибка отправки');
          return j.data;
        });
    });
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
        consent: form.querySelector('[name="consent"]').checked ? '1' : '',
        vtk_nonce: (form.querySelector('[name="vtk_nonce"]') || {}).value || D.nonce || '',
        ts: new Date().toISOString()
      };

      var submitBtn = form.querySelector('button[type="submit"]');
      var status = root.querySelector('[data-role="form-status"]') || form.querySelector('.form-note');
      submitBtn.disabled = true;
      submitBtn.textContent = 'Отправляем…';

      sendOrder(payload).then(function (data) {
        var url = (data && data.redirectTo) || root.dataset.thanksPath || '';
        if (url) { window.location.href = url; return; }
        if (status) status.textContent = 'Заявка отправлена.';
      }).catch(function (err) {
        submitBtn.disabled = false;
        submitBtn.textContent = 'Отправить заявку';
        if (status) status.textContent = 'Не удалось отправить заявку. Позвоните нам или повторите позже.';
      });
    });
  }

  /* ================= ИНИЦИАЛИЗАЦИЯ ================= */
  document.addEventListener('DOMContentLoaded', function () {
    var calc = document.querySelector('[data-calculator]');
    if (calc) initCalculator(calc);

    var orderRoot = document.querySelector('[data-order-root]');
    if (orderRoot) initOrderForm(orderRoot);

    // активная пилюля переключателя аудитории — по совпадению пути ссылки с текущим
    var here = new URL(window.location.href).pathname.replace(/\/$/, '');
    document.querySelectorAll('.aud-switch a').forEach(function (a) {
      var target = new URL(a.href, window.location.href).pathname.replace(/\/$/, '');
      if (target === here) a.classList.add('active');
    });
  });
})();
