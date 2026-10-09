// UI-прогон админки и клиентских страниц в jsdom против живого сервера
import { JSDOM, VirtualConsole } from 'jsdom';

const BASE = 'http://localhost:3000';
let pass = 0, fail = 0;
const ok = (name, cond, extra = '') => {
  if (cond) { pass++; console.log('PASS', name); }
  else { fail++; console.log('FAIL', name, extra); }
};

async function loadPage(path, { storage = {}, clearStorage = false } = {}) {
  const res = await fetch(BASE + path);
  const html = await res.text();
  const errors = [];
  const vc = new VirtualConsole();
  const msgs = [];
  vc.on('jsdomError', (e) => {
    msgs.push(String(e.message));
    vc._allMessages = msgs;
    // jsdom не умеет навигацию по location.href — это ожидаемо и не является ошибкой кода
    if (!/Not implemented: navigation/i.test(String(e.message))) errors.push(String(e.message));
  });
  const dom = new JSDOM(html, {
    url: BASE + path,
    runScripts: 'dangerously',
    resources: 'usable',
    pretendToBeVisual: true,
    virtualConsole: vc,
    beforeParse(window) {
      // jsdom не предоставляет fetch — подставляем нативный fetch Node 20
      window.fetch = (url, opts) => fetch(new URL(url, BASE), opts);
      window.Request = globalThis.Request;
      window.Response = globalThis.Response;
      window.Headers = globalThis.Headers;
      window.FormData = globalThis.FormData;
    },
  });
  if (clearStorage) dom.window.localStorage.clear();
  for (const [k, v] of Object.entries(storage)) dom.window.localStorage.setItem(k, v);
  dom.window.addEventListener('error', (e) => errors.push(String(e.message)));
  return { dom, window: dom.window, document: dom.window.document, errors, vc };
}

const wait = (ms) => new Promise((r) => setTimeout(r, ms));

function vcMessages(vc) {
  // собираем все ранее полученные сообщения консоли для проверки навигации
  return vc._allMessages ? vc._allMessages.join('\n') : '';
}

// ---------- ADMIN ----------
{
  const { document, window, errors } = await loadPage('/admin.html');
  await wait(1500);
  ok('admin: без токена показан экран логина', !document.getElementById('login-screen').hidden && document.getElementById('admin-screen').hidden);

  document.getElementById('login-pass').value = 'wrong';
  document.getElementById('login-form').dispatchEvent(new window.Event('submit', { bubbles: true, cancelable: true }));
  await wait(800);
  const le = document.getElementById('login-error');
  ok('admin: неверный пароль -> сообщение об ошибке', !le.hidden && /парол/i.test(le.textContent), le.textContent);

  document.getElementById('login-pass').value = 'test-password-123';
  document.getElementById('login-form').dispatchEvent(new window.Event('submit', { bubbles: true, cancelable: true }));
  await wait(1500);
  ok('admin: верный пароль -> admin-screen открыт', document.getElementById('login-screen').hidden && !document.getElementById('admin-screen').hidden);
  ok('admin: токен сохранён в localStorage', /^.{32,}$/.test(window.localStorage.getItem('vt_admin_token') || ''));

  document.getElementById('t-calc').click();
  await wait(400);
  ok('admin: вкладка Калькулятор активна', !document.getElementById('tab-calc').hidden);
  const basesRows = document.querySelectorAll('#bases-body tr').length;
  const modRows = document.querySelectorAll('#modules-body tr').length;
  ok(`admin: таблицы баз (${basesRows}) и модулей (${modRows}) отрисованы`, basesRows >= 3 && modRows >= 4);

  const priceVals = [...document.querySelectorAll('#bases-body input[type="number"]')].map((i) => Number(i.value));
  ok('admin: цены баз = 370000/340000/370000', JSON.stringify(priceVals.slice().sort()) === JSON.stringify([340000, 370000, 370000]), JSON.stringify(priceVals));

  // jsdom: confirm/alert не реализованы — разрешаем все подтверждения
  window.confirm = () => true;
  window.alert = () => {};

  const addBtn = [...document.querySelectorAll('#tab-calc button')].find((b) => /добавить модуль/i.test(b.textContent));
  ok('admin: кнопка «Добавить модуль» найдена', !!addBtn);
  if (addBtn) { addBtn.click(); await wait(200); }
  ok('admin: после добавления строк модулей больше', document.querySelectorAll('#modules-body tr').length === modRows + 1,
    `было ${modRows}, стало ${document.querySelectorAll('#modules-body tr').length}`);

  const delBtn = [...document.querySelectorAll('#modules-body .btn-icon')].pop();
  if (delBtn) { delBtn.click(); await wait(200); }
  ok('admin: удаление строки работает', document.querySelectorAll('#modules-body tr').length === modRows,
    `стало ${document.querySelectorAll('#modules-body tr').length}`);

  document.getElementById('t-orders').click();
  await wait(1200);
  const ordersRows = document.querySelectorAll('#orders-body tr').length;
  ok(`admin: список заявок отрисован (${ordersRows} строк)`, ordersRows >= 2);
  const statusSel = document.querySelector('#orders-body select');
  ok('admin: у заявки есть селект статуса', !!statusSel);

  const rawHtml = document.getElementById('tab-orders').innerHTML;
  ok('admin: вывод заявок не содержит сырых <script>', !/<script/i.test(rawHtml));

  document.getElementById('save-btn').click();
  await wait(1200);
  const gok = document.getElementById('global-ok');
  ok('admin: «Сохранить всё» -> успех', !gok.hidden || /сохран/i.test(document.getElementById('save-state').textContent), gok.textContent);

  ok('admin: JS-ошибок при работе нет', errors.length === 0, errors.join('; '));
}

// ---------- CALCULATOR ----------
{
  const { document, window, errors, vc } = await loadPage('/calculator.html');
  await wait(1500);
  const totalEl = document.querySelector('#total-value');
  ok('calc: элемент суммы существует', !!totalEl);
  // реальные селекторы: input[name="calc-base"], input[name="calc-module"] (см. script.js optionRow)
  const basePro = document.querySelector('input[name="calc-base"][value="pro"]');
  const modChuck = document.querySelector('input[name="calc-module"][value="chuck"]');
  ok('calc: рендер баз и модулей из data.json', !!basePro && !!modChuck);
  if (basePro) basePro.click();
  if (modChuck) modChuck.click();
  await wait(300);
  const txt = totalEl ? totalEl.textContent : '';
  const digits = txt.replace(/\D/g, '');
  ok('calc: пересчёт pro+chuck = 490000', digits === '490000', txt);
  const cfg = JSON.parse(window.localStorage.getItem('vt_config') || 'null');
  ok('calc: конфиг записан в localStorage', cfg && cfg.base === 'pro' && (cfg.modules || []).includes('chuck'), JSON.stringify(cfg));
  ok('calc: список выбранных позиций отрисован', /патрон/i.test(document.getElementById('summary-list').textContent));
  // «Оформить заказ» ведёт на order.html через location.href — jsdom логирует
  // «Not implemented: navigation (except hash changes)<URL>»: извлекаем URL из лога
  document.getElementById('to-order-btn').click();
  await wait(200);
  const navLog = vcMessages(vc);
  ok('calc: кнопка «Оформить заказ» -> order.html?cfg=pro+chuck', /navigation.*\/order\.html\?cfg=pro%2Bchuck/.test(navLog), navLog.slice(0, 200));
  ok('calc: JS-ошибок нет', errors.length === 0, errors.join('; ') + ' | ' + navLog.slice(0, 200));
}

// ---------- ORDER ----------
{
  // очищаем localStorage до загрузки: иначе страница восстановит прошлый выбор вместо ?cfg=
  const { document, window, errors } = await loadPage('/order.html?cfg=lite%2Brobot', { clearStorage: true });
  await wait(1500);
  const hiddenCfg = [...document.querySelectorAll('input[type="hidden"]')];
  const names = hiddenCfg.map((h) => h.name || h.id).join(',');
  ok('order: скрытые поля config/total/base/modules присутствуют', ['f-config', 'f-total', 'f-base', 'f-modules'].every((id) => document.getElementById(id)), names);
  ok('order: config заполнен из query (robot)', JSON.parse(document.getElementById('f-config').value || '[]').some((i) => i.id === 'robot'), document.getElementById('f-config').value);
  ok('order: серверная сумма lite+robot = 690000', document.getElementById('f-total').value === '690000', document.getElementById('f-total').value);
  const summary = document.body.textContent;
  ok('order: конфигурация отображается (Робот)', /робот/i.test(summary));
  const form = document.querySelector('form');
  form.dispatchEvent(new window.Event('submit', { bubbles: true, cancelable: true }));
  await wait(400);
  ok('order: пустая форма блокируется клиентской валидацией', !window.location.href.includes('thanks'));
  ok('order: показаны сообщения валидации', /имя/i.test(document.getElementById('e-name')?.textContent || ''), document.getElementById('e-name')?.textContent);
  ok('order: JS-ошибок нет', errors.length === 0, errors.join('; '));
}

// ---------- INDEX content binding ----------
{
  const { document, errors } = await loadPage('/index.html');
  await wait(1500);
  const bound = [...document.querySelectorAll('[data-bind]')];
  const filled = bound.filter((el) => el.textContent.trim().length > 0);
  ok(`index: data-bind заполнены (${filled.length}/${bound.length})`, bound.length > 0 && filled.length === bound.length);
  ok('index: телефон из data.json в DOM', /\+7/.test(document.body.textContent));
  ok('index: JS-ошибок нет', errors.length === 0, errors.join('; '));
}

console.log(`\nRESULT: ${pass} passed, ${fail} failed`);
process.exit(fail ? 1 : 0);
