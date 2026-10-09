/* Временный тест: jsdom-проверка калькулятора, формы и пилюль. Не часть сайта. */
const { JSDOM } = require('jsdom');
const fs = require('fs');

const script = fs.readFileSync('assets/script.js', 'utf8');
let pass = 0, fail = 0;
function t(name, cond) { cond ? (pass++, console.log('PASS', name)) : (fail++, console.log('FAIL', name)); }

function load(file, url) {
  const dom = new JSDOM(fs.readFileSync(file, 'utf8'), { url, runScripts: 'outside-only', pretendToBeVisual: true });
  const { window } = dom;
  const fetchCalls = [];
  window.fetch = (u, o) => { fetchCalls.push({ u, o }); return Promise.reject(new Error('offline')); };
  window.eval(script);
  window.document.dispatchEvent(new window.Event('DOMContentLoaded', { bubbles: true }));
  return { window, doc: window.document, fetchCalls };
}

// ---------- 1. Калькулятор на главной ----------
{
  const { window, doc } = load('index.html', 'http://localhost/index.html');
  const calc = doc.querySelector('[data-calculator]');
  const fire = el => el.dispatchEvent(new window.Event('change', { bubbles: true }));

  const basePro = calc.querySelector('input[value="pro"]');
  basePro.checked = true; fire(basePro);
  t('calc: база pro -> 370 000 ₽',
    doc.querySelector('[data-role="summary-total"]').textContent.replace(/\s/g,'') === '370000₽');

  const chuck = calc.querySelector('input[value="chuck"]');
  const robot = calc.querySelector('input[value="robot"]');
  chuck.checked = true; fire(chuck);
  robot.checked = true; fire(robot);
  t('calc: +патрон+робот -> 840 000 ₽',
    doc.querySelector('[data-role="summary-total"]').textContent.replace(/\s/g,'') === '840000₽');
  t('calc: список позиций = 3', doc.querySelectorAll('[data-role="summary-lines"] .sum-line').length === 3);

  const saved = JSON.parse(window.localStorage.getItem('vt_config'));
  t('calc: конфиг в localStorage', saved.total === 840000 && saved.items.length === 3);

  const btn = calc.querySelector('[data-role="order-btn"]');
  btn.dispatchEvent(new window.MouseEvent('click', { bubbles: true, cancelable: true }));
  t('calc: клик по кнопке не бросает ошибок', true);
}

// ---------- 2. /calculator/?base=lite ----------
{
  const { doc } = load('calculator/index.html', 'http://localhost/calculator/index.html?base=lite');
  const lite = doc.querySelector('input[name="calc-base"][value="lite"]');
  t('calc page: ?base=lite выбран по умолчанию', lite.checked === true);
  t('calc page: итог 340 000 ₽',
    doc.querySelector('[data-role="summary-total"]').textContent.replace(/\s/g,'') === '340000₽');
}

// ---------- 3. Форма заказа ----------
{
  const cfg = { items: [{ label: 'База: для ручного станка', price: 370000 }, { label: 'Автозажим тисков', price: 95000 }], total: 465000 };
  const url = 'http://localhost/order/?cfg=' + encodeURIComponent(JSON.stringify(cfg));
  const { window, doc, fetchCalls } = load('order/index.html', url);

  const strip = doc.querySelector('[data-role="config-strip"]');
  t('order: сводка «2 поз. на сумму 465 000 ₽»',
    /2 поз\. на сумму\s*465\s*000 ₽/.test(strip.textContent.replace(/\s+/g,' ').trim()));
  t('order: hidden config заполнен',
    JSON.parse(doc.querySelector('input[name="config"]').value).total === 465000);

  const form = doc.querySelector('form[data-order-form]');
  const submit = () => form.dispatchEvent(new window.Event('submit', { bubbles: true, cancelable: true }));
  const fireInput = el => el.dispatchEvent(new window.Event('input', { bubbles: true }));
  const fireBlur  = el => el.dispatchEvent(new window.Event('blur', { bubbles: true }));

  submit();
  t('order: пустая форма не отправляется', fetchCalls.length === 0 && doc.querySelectorAll('.field.invalid').length >= 3);

  const phone = form.querySelector('[name="phone"]');
  phone.value = '9991234567'; fireInput(phone);
  t('order: маска +7 (999) 123-45-67', phone.value === '+7 (999) 123-45-67');

  form.querySelector('[name="name"]').value = 'Иван';
  const email = form.querySelector('[name="email"]');
  email.value = 'bad@@mail'; fireBlur(email);
  t('order: некорректный email помечен', email.closest('.field').classList.contains('invalid'));
  email.value = 'a@b.ru'; fireInput(email);
  t('order: корректный email принят', !email.closest('.field').classList.contains('invalid'));

  submit();
  t('order: без согласия не отправляет', fetchCalls.length === 0);

  form.querySelector('[name="consent"]').checked = true;
  submit();
  t('order: fetch на /api/order вызван', fetchCalls.length === 1 && fetchCalls[0].u === '/api/order');
  const body = JSON.parse(fetchCalls[0].o.body);
  t('order: payload содержит телефон и конфиг', body.phone === '+7 (999) 123-45-67' && /465000/.test(body.config));
}

// ---------- 4. Пилюли ----------
const pages = ['index.html','cnc-pro/index.html','cnc-lite/index.html','manual/index.html','calculator/index.html','order/index.html','thanks/index.html'];
for (const p of pages) {
  const { doc } = load(p, 'http://localhost/' + p);
  t('pill-nav на ' + p, doc.querySelectorAll('.aud-switch a.pill').length === 3);
}
{
  const { doc } = load('cnc-pro/index.html', 'http://localhost/cnc-pro/');
  const active = doc.querySelectorAll('.aud-switch a.active');
  t('cnc-pro: активная пилюля одна и верная',
    active.length === 1 && active[0].getAttribute('href') === '../cnc-pro/');
}
{
  const { doc } = load('index.html', 'http://localhost/');
  t('home: ни одна пилюля не активна', doc.querySelectorAll('.aud-switch a.active').length === 0);
}

console.log(`\n${pass} passed, ${fail} failed`);
process.exit(fail ? 1 : 0);
