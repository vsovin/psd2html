/**
 * VirshkeTech — промо-сайт + мини-CMS.
 * Один Express-процесс: раздача статики из public/ и JSON-API поверх data.json.
 * Хранилище — файл (без БД). Запись атомарная: temp-file -> rename, с бэкапом.
 */

const fs = require('fs');
const path = require('path');
const http = require('http');
const crypto = require('crypto');
const express = require('express');
require('dotenv').config();

// nodemailer опционален: без SMTP-настроек сайт работает только с записью в data.json
let nodemailer = null;
try {
  nodemailer = require('nodemailer');
} catch (e) {
  console.warn('[server] nodemailer не установлен — письма отправляться не будут.');
}

/** Корень проекта: ищем data.json рядом с server.js, иначе в ./virshketech-site */
function findRoot() {
  const candidates = [__dirname, path.join(__dirname, 'virshketech-site')];
  const hit = candidates.find((dir) => fs.existsSync(path.join(dir, 'data.json')));
  return hit || __dirname;
}

const app = express();
const PORT = Number(process.env.PORT) || 3000;
const ROOT = findRoot();
const DATA_FILE = path.join(ROOT, 'data.json');
const PUBLIC_DIR = path.join(ROOT, 'public');

const ADMIN_PASSWORD = process.env.ADMIN_PASSWORD || 'virshke-admin';
const TOKEN_TTL_MS = 12 * 60 * 60 * 1000; // токен живёт 12 часов
const MAX_JSON_BYTES = 256 * 1024;        // разумный предел тела запроса
const MAX_ORDERS_KEPT = 500;              // защита от разрастания data.json

// ---------------------------------------------------------------------------
// Утилиты
// ---------------------------------------------------------------------------

const escapeHtml = (value) =>
  String(value == null ? '' : value)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');

const round = (n) => Math.round(Number(n) || 0);

/** Только https- или относительные URL картинок (запрет javascript:/data:) */
const isSafeUrl = (u) => {
  const s = String(u || '').trim();
  if (!s) return true; // пустой URL допустим — подставится placeholder
  if (s.startsWith('/')) return true;
  return /^https?:\/\//i.test(s);
};

/** Валидация телефона: принимаем цифры/+-()пробел, нормализуем к +7 XXX XXX-XX-XX */
function normalizePhone(raw) {
  let digits = String(raw || '').replace(/\D/g, '');
  if (!digits) return null;
  if (digits.length === 10) digits = '7' + digits;          // 9xx... -> 79xx...
  if (digits[0] === '8') digits = '7' + digits.slice(1);     // 8 -> 7
  if (digits.length !== 11 || digits[0] !== '7') return null;
  return `+${digits[0]} (${digits.slice(1, 4)}) ${digits.slice(4, 7)}-${digits.slice(7, 9)}-${digits.slice(9)}`;
}

const isValidEmail = (s) => /^[^\s@]+@[^\s@]+\.[a-zA-Zа-яА-Я]{2,}$/.test(String(s || '').trim());

/** Глубокое слияние: правки из админки не затирают разделы, которых нет в payload */
function deepMerge(target, patch) {
  if (!patch || typeof patch !== 'object' || Array.isArray(patch)) return patch;
  const out = { ...(target && typeof target === 'object' && !Array.isArray(target) ? target : {}) };
  for (const key of Object.keys(patch)) {
    const pv = patch[key];
    if (pv && typeof pv === 'object' && !Array.isArray(pv)) {
      out[key] = deepMerge(out[key], pv);
    } else {
      out[key] = pv;
    }
  }
  return out;
}

// ---------------------------------------------------------------------------
// Чтение / запись data.json
// ---------------------------------------------------------------------------

/** Скелет на случай повреждённого/отсутствующего data.json — сервер не должен падать */
const DEFAULT_DATA = {
  site: { phone: '', email: '', address: '', social: { telegram: '', whatsapp: '' } },
  pages: {},
  products: [],
  filters: [{ id: 'all', label: 'Все изделия' }],
  calculator: { baseOptions: [], modules: [] },
  orders: []
};

function readData() {
  try {
    const raw = fs.readFileSync(DATA_FILE, 'utf8');
    const parsed = JSON.parse(raw);
    // deepMerge сохраняет все разделы файла (site.workHours, pages.*, product.priceFrom и т.д.)
    return deepMerge(DEFAULT_DATA, parsed);
  } catch (e) {
    console.error('[data] не удалось прочитать data.json, беру дефолты:', e.message);
    return JSON.parse(JSON.stringify(DEFAULT_DATA));
  }
}

/** Атомарная запись: пишем temp-файл, бэкапим старый, делаем rename */
function writeData(data) {
  const json = JSON.stringify(data, null, 2);
  if (Buffer.byteLength(json, 'utf8') > 4 * 1024 * 1024) {
    throw new Error('data.json превышает 4 МБ — очистите заявки.');
  }
  const tmp = DATA_FILE + '.tmp';
  fs.writeFileSync(tmp, json, 'utf8');
  try {
    if (fs.existsSync(DATA_FILE)) fs.copyFileSync(DATA_FILE, DATA_FILE + '.bak');
  } catch (e) {
    console.warn('[data] бэкап не создан:', e.message);
  }
  fs.renameSync(tmp, DATA_FILE);
}

// ---------------------------------------------------------------------------
// Санитайзеры контента (граница «админка -> файл»)
// Работают по white-list только для калькулятора и products; site/pages
// сохраняются структурно, чтобы не потерять поля, которых нет в форме админки.
// ---------------------------------------------------------------------------

const cleanText = (v, max = 4000) => String(v == null ? '' : v).slice(0, max);
const cleanLine = (v, max = 300) => String(v == null ? '' : v).replace(/\s+/g, ' ').trim().slice(0, max);

/** Поля, которые должны оставаться ссылками; по ключу слова — запасной вариант */
const URL_KEYS = new Set(['image', 'page', 'heroImage', 'iconUrl', 'logo', 'href', 'link', 'url', 'ctaLink']);
const looksLikeUrlKey = (key) => /(^|_)(url|image|img|link|href|logo)$/i.test(key) || /(url|image|img|link|href|social)/i.test(key);

/** Опасные исполняющие схемы: их быть не должно ни в каком поле (в т.ч. в «схеме без ://») */
const DANGEROUS_SCHEME_RE = /^\s*(javascript|data|vbscript|file|blob):/i;

/** Признак «это URL с протоколом», а не обычный текст: tel:/mailto: тоже считаем ссылками */
const looksLikeUrlValue = (s) => /^(https?:|tel:|mailto:|\/\/)/i.test(String(s || '').trim());

/**
 * Схема ссылки недопустима, если не http(s) / mailto / tel / относительная.
 * Запрещаем javascript:, data:, vbscript: и прочую исполняющую схему.
 * Обычный текст (телефон «+7 …», адрес, ФИО) схемой не является — пропускаем как есть,
 * но опасную схему вида "javascript:alert(1)" отсекаем всегда.
 */
const isSafeScheme = (s) => {
  const t = String(s || '').trim();
  if (!t) return true;
  if (DANGEROUS_SCHEME_RE.test(t)) return false;
  if (!looksLikeUrlValue(t)) return true;
  return /^(https?:\/\/|mailto:|tel:)/i.test(t);
};

/**
 * Скаляр из payload: в полях-ссылках запрещаем опасные схемы, текст нормализуем.
 * XSS-защита на сайте строится на том, что клиент вставляет контент только через
 * textContent (см. script.js), а в href/src попадают лишь проверенные здесь URL.
 */
function sanitizeScalar(value, key, max = 4000) {
  const s = value == null ? '' : String(value);
  const isUrlField = URL_KEYS.has(key) || looksLikeUrlKey(key) || looksLikeUrlValue(s);
  if (isUrlField && !isSafeScheme(s)) return ''; // dangerous scheme -> drop
  if (isUrlField) return cleanLine(s, 500);
  return max <= 500 ? cleanLine(s, max) : cleanText(s, max);
}

/**
 * Слияние с сохранением полей, которые CMS не отдаёт (например, products[].url —
 * они не редактируются из админки и должны пережить любое сохранение).
 */
function mergeById(previous = [], incoming = []) {
  const prev = new Map((previous || []).map((x) => [String(x.id), x]));
  return (incoming || []).map((item) => ({ ...(prev.get(String(item.id)) || {}), ...item }));
}

function sanitizeSite(site) {
  if (!site || typeof site !== 'object') return {};
  const out = {};
  for (const k of Object.keys(site)) {
    const v = site[k];
    if (k === 'social' && v && typeof v === 'object') {
      out.social = {};
      for (const sk of Object.keys(v)) out.social[sk] = sanitizeScalar(v[sk], sk + '_url', 300);
      continue;
    }
    if (v && typeof v === 'object') continue; // site содержит только скаляры
    out[k] = sanitizeScalar(v, k, k === 'address' ? 300 : 200);
  }
  return out;
}

function sanitizePages(pages) {
  const walk = (node, key) => {
    if (Array.isArray(node)) return node.map((x) => walk(x, key));
    if (node && typeof node === 'object') {
      const out = {};
      for (const k of Object.keys(node)) out[k] = walk(node[k], k);
      return out;
    }
    if (typeof node === 'number' || typeof node === 'boolean') return node;
    return sanitizeScalar(node, key);
  };
  return walk(pages || {}, '');
}

function sanitizeFilters(filters) {
  const list = (filters || []).map((f) => ({
    id: cleanLine(f.id, 60).replace(/[^a-zA-Z0-9_-]/g, ''),
    label: cleanLine(f.label, 120)
  }));
  const withAll = list.filter((f) => f.id && f.label);
  if (!withAll.some((f) => f.id === 'all')) withAll.unshift({ id: 'all', label: 'Все изделия' });
  return withAll;
}

function sanitizeProduct(p) {
  const src = p || {};
  const out = {};
  for (const k of Object.keys(src)) {
    const v = src[k];
    if (typeof v === 'number') out[k] = round(v);
    else if (typeof v === 'boolean') out[k] = v;
    else if (Array.isArray(v)) out[k] = v.map((x) => (x && typeof x === 'object' ? sanitizePages(x) : sanitizeScalar(x, k, 400)));
    else if (v && typeof v === 'object') out[k] = sanitizePages(v);
    else out[k] = sanitizeScalar(v, k);
  }
  out.id = cleanLine(src.id, 60).replace(/[^a-zA-Z0-9_-]/g, '');
  out.name = cleanLine(src.name, 120);
  out.price = round(src.price);
  out.image = isSafeUrl(src.image) ? cleanLine(src.image, 400) : '';
  return out;
}

function sanitizeCalculator(calc) {
  const normItem = (it) => ({
    ...it,
    id: cleanLine(it.id, 60).replace(/[^a-zA-Z0-9_-]/g, ''),
    label: cleanLine(it.label, 160),
    hint: cleanLine(it.hint, 200),
    price: round(it.price),
    active: it.active !== false
  });
  const src = calc || {};
  const out = {};
  for (const k of Object.keys(src)) {
    if (k === 'baseOptions' || k === 'modules') continue;
    out[k] = typeof src[k] === 'string' ? cleanLine(src[k], 300) : src[k];
  }
  out.baseOptions = (src.baseOptions || []).map(normItem).filter((x) => x.id && x.label);
  out.modules = (src.modules || []).map(normItem).filter((x) => x.id && x.label);
  return out;
}

/** Полная валидация структуры, пришедшей из POST /api/admin/save */
function buildSanitizedContent(payload, current) {
  const body = payload || {};
  const out = {
    ...current, // сохраняем служебные разделы, которых нет в форме
    site: { ...current.site, ...sanitizeSite(body.site) },
    pages: sanitizePages(body.pages),
    products: (body.products || current.products || []).map(sanitizeProduct),
    filters: sanitizeFilters(body.filters || current.filters),
    calculator: sanitizeCalculator(body.calculator),
    orders: current.orders // заявки через save не перезаписываются — только через API
  };

  const problems = [];
  if (!out.site.phone) problems.push('Укажите телефон компании.');
  if (out.site.email && !isValidEmail(out.site.email)) problems.push('Некорректный e-mail компании.');
  if (!out.calculator.baseOptions.length) problems.push('В калькуляторе должна быть хотя бы одна база.');
  out.calculator.baseOptions.forEach((b) => {
    if (b.price <= 0) problems.push(`Цена базы «${b.label}» должна быть больше нуля.`);
  });
  out.calculator.modules.forEach((m) => {
    if (m.price <= 0) problems.push(`Цена модуля «${m.label}» должна быть больше нуля.`);
  });
  const ids = [...out.calculator.baseOptions, ...out.calculator.modules].map((x) => x.id);
  if (new Set(ids).size !== ids.length) problems.push('id баз и модулей должны быть уникальны.');
  out.products.forEach((p) => {
    if (!p.id) problems.push('У изделия пустой id.');
    if (p.price < 0) problems.push(`Отрицательная цена у «${p.name}».`);
  });

  return { content: out, problems };
}

// ---------------------------------------------------------------------------
// Расчёт стоимости конфигурации по ценам из data.json (источник истины — сервер)
// ---------------------------------------------------------------------------

function calcTotal(calculator, config) {
  const bases = calculator.baseOptions || [];
  const mods = calculator.modules || [];
  const items = [];
  let total = 0;

  const baseId = String((config && config.base) || '');
  const base = bases.find((b) => b.id === baseId && b.active);
  if (!base) return { error: 'База комплектации не выбрана или недоступна.', items: [], total: 0 };
  items.push({ id: base.id, label: base.label, price: base.price, type: 'base' });
  total += base.price;

  const wanted = Array.isArray(config && config.modules) ? config.modules : [];
  for (const id of wanted) {
    const m = mods.find((x) => x.id === id && x.active);
    if (!m) return { error: `Модуль «${id}» не найден или отключён.`, items: [], total: 0 };
    if (items.some((i) => i.id === m.id)) continue; // дубликаты игнорируем
    items.push({ id: m.id, label: m.label, price: m.price, type: 'module' });
    total += m.price;
  }
  return { items, total };
}

// ---------------------------------------------------------------------------
// Авторизация админки: случайный токен в памяти процесса
// ---------------------------------------------------------------------------

const adminTokens = new Map(); // token -> expireAt

function issueToken() {
  const token = crypto.randomBytes(32).toString('hex');
  adminTokens.set(token, Date.now() + TOKEN_TTL_MS);
  for (const [t, exp] of adminTokens) if (exp < Date.now()) adminTokens.delete(t);
  return token;
}

function requireAdmin(req, res, next) {
  const header = req.get('authorization') || '';
  const token = header.startsWith('Bearer ') ? header.slice(7).trim() : '';
  const exp = adminTokens.get(token);
  if (!token || !exp || exp < Date.now()) {
    adminTokens.delete(token);
    return res.status(401).json({ ok: false, error: 'Сессия администратора недействительна. Войдите заново.' });
  }
  adminTokens.set(token, exp); // скользящее истечение
  req.adminToken = token;
  return next();
}

// Простейшая защита от перебора пароля: 8 попыток с одного IP за 15 минут
const loginAttempts = new Map();
function allowLoginAttempt(ip) {
  const now = Date.now();
  const arr = (loginAttempts.get(ip) || []).filter((t) => now - t < 15 * 60 * 1000);
  if (arr.length >= 8) {
    loginAttempts.set(ip, arr);
    return false;
  }
  arr.push(now);
  loginAttempts.set(ip, arr);
  return true;
}

// ---------------------------------------------------------------------------
// Отправка письма (опционально)
// ---------------------------------------------------------------------------

function smtpConfigured() {
  return Boolean(nodemailer && process.env.SMTP_HOST && process.env.SMTP_USER && process.env.ORDER_EMAIL);
}

async function sendOrderMail(order) {
  if (!smtpConfigured()) return false;
  const lines = [
    `Заявка №${order.id} — ${order.total.toLocaleString('ru-RU')} ₽`,
    `Имя: ${order.name}`,
    order.company ? `Компания: ${order.company}` : '',
    `Телефон: ${order.phone}`,
    order.email ? `E-mail: ${order.email}` : '',
    order.machine ? `Модель станка: ${order.machine}` : '',
    '',
    'Комплектация:',
    ...order.items.map((i) => `— ${i.label}: ${i.price.toLocaleString('ru-RU')} ₽`)
  ].filter(Boolean);

  const transporter = nodemailer.createTransport({
    host: process.env.SMTP_HOST,
    port: Number(process.env.SMTP_PORT) || 465,
    secure: (Number(process.env.SMTP_PORT) || 465) === 465,
    auth: { user: process.env.SMTP_USER, pass: process.env.SMTP_PASS }
  });

  await transporter.sendMail({
    from: process.env.MAIL_FROM || process.env.SMTP_USER,
    to: process.env.ORDER_EMAIL,
    subject: `VirshkeTech: новая заявка №${order.id} (${order.name})`,
    text: lines.join('\n')
  });
  return true;
}

// ---------------------------------------------------------------------------
// HTTP-клиент для webhook-заявки (Telegram/CRM) — без внешних зависимостей
// ---------------------------------------------------------------------------

function postJson(url, body, timeoutMs = 5000) {
  return new Promise((resolve, reject) => {
    let target;
    try {
      target = new URL(url);
    } catch (e) {
      return reject(new Error('bad url'));
    }
    const lib = target.protocol === 'https:' ? require('https') : http;
    const payload = JSON.stringify(body);
    const req = lib.request(
      {
        method: 'POST',
        hostname: target.hostname,
        port: target.port || (target.protocol === 'https:' ? 443 : 80),
        path: target.pathname + target.search,
        headers: { 'content-type': 'application/json', 'content-length': Buffer.byteLength(payload) },
        timeout: timeoutMs
      },
      (res) => {
        res.resume();
        resolve(res.statusCode);
      }
    );
    req.on('timeout', () => req.destroy(new Error('timeout')));
    req.on('error', reject);
    req.end(payload);
  });
}

// ---------------------------------------------------------------------------
// Middleware
// ---------------------------------------------------------------------------

app.disable('x-powered-by');
app.use(express.json({ limit: MAX_JSON_BYTES }));

// data.json и служебные файлы — наружу не отдаём (файлы лежат вне public/, но
// на случай symlink или запуска из корня проекта закрываем путь явно)
app.use((req, res, next) => {
  if (/^\/(data\.json(\.bak|\.tmp)?|\.env(\.example)?|server\.js|package(-lock)?\.json)/i.test(req.path)) {
    return res.status(404).end();
  }
  next();
});

// Статика: HTML не кэшируем, чтобы после обновления страницы были видны правки CMS
app.use(
  express.static(PUBLIC_DIR, {
    extensions: ['html'],
    setHeaders: (res, filePath) => {
      if (/\.html$/i.test(filePath)) res.setHeader('Cache-Control', 'no-cache');
    }
  })
);

// ---------------------------------------------------------------------------
// API: публичные эндпоинты
// ---------------------------------------------------------------------------

app.get('/api/health', (req, res) => res.json({ ok: true, mail: smtpConfigured() }));

// Полный контент сайта. Контентная часть — публичная, заявки не отдаём.
app.get('/api/data', (req, res) => {
  const data = readData();
  res.setHeader('Cache-Control', 'no-store');
  res.json({
    ok: true,
    site: data.site,
    pages: data.pages,
    products: data.products,
    filters: data.filters,
    calculator: data.calculator
  });
});

// Заявка с формы. Валидация на сервере, сумма считается по ценам из data.json.
app.post('/api/order', async (req, res) => {
  const body = req.body || {};
  const errors = [];

  const name = cleanLine(body.name, 120);
  if (name.length < 2) errors.push('Укажите имя (минимум 2 символа).');

  const company = cleanLine(body.company, 160);
  const machine = cleanText(body.machine, 1000);

  const phone = normalizePhone(body.phone);
  if (!phone) errors.push('Укажите корректный номер телефона.');

  const email = cleanLine(body.email, 120);
  if (email && !isValidEmail(email)) errors.push('Некорректный e-mail.');

  if (!body.consent) errors.push('Требуется согласие на обработку персональных данных.');

  const { items, total, error } = calcTotal(readData().calculator, {
    base: body.base,
    modules: body.modules
  });
  if (error) errors.push(error);

  if (errors.length) return res.status(400).json({ ok: false, errors });

  const data = readData();
  const lastId = data.orders.reduce((mx, o) => Math.max(mx, Number(o.id) || 0), 0);
  const order = {
    id: lastId + 1,
    name,
    company,
    phone,
    email,
    machine,
    base: String(body.base || '').slice(0, 60),
    items,
    total,
    status: 'new',
    createdAt: new Date().toISOString(),
    source: cleanLine(body.source, 120) || 'site'
  };

  data.orders.push(order);
  if (data.orders.length > MAX_ORDERS_KEPT) {
    data.orders = data.orders.slice(-MAX_ORDERS_KEPT);
    console.warn('[orders] включено ограничение: сохранены последние', MAX_ORDERS_KEPT, 'заявок');
  }

  try {
    writeData(data);
  } catch (e) {
    console.error('[orders] запись не удалась:', e.message);
    return res.status(500).json({ ok: false, errors: ['Не удалось сохранить заявку. Повторите позже.'] });
  }

  // Уведомления — best effort: ошибка письма не должна ломать приём заявки
  sendOrderMail(order)
    .then((sent) => sent && console.log(`[mail] заявка #${order.id} отправлена на почту`))
    .catch((e) => console.error('[mail] ошибка отправки письма:', e.message));

  const webhook = process.env.WEBHOOK_URL;
  if (webhook) {
    postJson(webhook, { order: { id: order.id, name: order.name, phone: order.phone, total: order.total } }).catch(
      (e) => console.warn('[webhook] не отправлен:', e.message)
    );
  }

  return res.json({ ok: true, id: order.id, total: order.total });
});

// ---------------------------------------------------------------------------
// API: админка
// ---------------------------------------------------------------------------

app.post('/api/admin/login', (req, res) => {
  const ip = req.ip || req.socket.remoteAddress || 'unknown';
  if (!allowLoginAttempt(ip)) {
    return res.status(429).json({ ok: false, error: 'Слишком много попыток. Подождите 15 минут.' });
  }
  const password = String((req.body && req.body.password) || '');
  const expected = Buffer.from(ADMIN_PASSWORD);
  const given = Buffer.from(password);
  // сравнение равной длины через хэш — против timing-атаки
  const same =
    expected.length === given.length
      ? crypto.timingSafeEqual(crypto.createHash('sha256').update(given).digest(), crypto.createHash('sha256').update(expected).digest())
      : false;
  if (!same) return res.status(401).json({ ok: false, error: 'Неверный пароль.' });
  return res.json({ ok: true, token: issueToken(), expiresIn: TOKEN_TTL_MS });
});

app.post('/api/admin/logout', requireAdmin, (req, res) => {
  adminTokens.delete(req.adminToken);
  res.json({ ok: true });
});

// Проверка живости токена (страница админки восстанавливает сессию)
app.get('/api/admin/session', requireAdmin, (req, res) => res.json({ ok: true }));

// Сырые данные для админки — вместе с заявками
app.get('/api/admin/data', requireAdmin, (req, res) => {
  res.setHeader('Cache-Control', 'no-store');
  res.json({ ok: true, data: readData() });
});

app.post('/api/admin/save', requireAdmin, (req, res) => {
  const current = readData();
  const { content, problems } = buildSanitizedContent(req.body || {}, current);
  if (problems.length) return res.status(400).json({ ok: false, errors: problems });
  try {
    writeData(content);
  } catch (e) {
    return res.status(500).json({ ok: false, error: 'Ошибка записи data.json: ' + e.message });
  }
  return res.json({ ok: true });
});

app.get('/api/admin/orders', requireAdmin, (req, res) => {
  const status = cleanLine(req.query.status, 20);
  const orders = readData().orders;
  const list = status && status !== 'all' ? orders.filter((o) => o.status === status) : orders;
  res.setHeader('Cache-Control', 'no-store');
  res.json({ ok: true, orders: list.slice().reverse() });
});

app.post('/api/admin/orders/:id/status', requireAdmin, (req, res) => {
  const allowed = ['new', 'in_progress', 'done', 'archived'];
  const next = cleanLine((req.body && req.body.status) || '', 20);
  if (!allowed.includes(next)) return res.status(400).json({ ok: false, error: 'Недопустимый статус.' });
  const id = Number(req.params.id);
  const data = readData();
  const order = data.orders.find((o) => Number(o.id) === id);
  if (!order) return res.status(404).json({ ok: false, error: 'Заявка не найдена.' });
  order.status = next;
  order.updatedAt = new Date().toISOString();
  try {
    writeData(data);
  } catch (e) {
    return res.status(500).json({ ok: false, error: 'Ошибка записи: ' + e.message });
  }
  return res.json({ ok: true, order });
});

// Экспорт заявок в CSV (Excel-friendly: BOM + ; как разделитель)
app.get('/api/admin/orders/export.csv', requireAdmin, (req, res) => {
  const status = cleanLine(req.query.status, 20);
  const all = readData().orders;
  const orders = status && status !== 'all' ? all.filter((o) => o.status === status) : all;

  const head = ['ID', 'Дата', 'Статус', 'Имя', 'Компания', 'Телефон', 'E-mail', 'Станок', 'Комплектация', 'Сумма, руб'];
  const cell = (v) => `"${String(v == null ? '' : v).replace(/"/g, '""')}"`;
  const rows = orders.map((o) =>
    [
      o.id,
      o.createdAt,
      o.status,
      o.name,
      o.company,
      o.phone,
      o.email,
      o.machine,
      (o.items || []).map((i) => `${i.label} — ${i.price} ₽`).join('; '),
      o.total
    ]
      .map(cell)
      .join(';')
  );

  res.setHeader('Content-Type', 'text/csv; charset=utf-8');
  res.setHeader('Content-Disposition', `attachment; filename="virshketech-orders-${Date.now()}.csv"`);
  res.send('\ufeff' + [head.map(cell).join(';'), ...rows].join('\r\n') + '\r\n');
});

// ---------------------------------------------------------------------------
// Финальные обработчики
// ---------------------------------------------------------------------------

app.use('/api', (req, res) => res.status(404).json({ ok: false, error: 'Метод не найден.' }));

app.use((req, res) => {
  if (req.path.startsWith('/api/')) return res.status(404).json({ ok: false, error: 'Не найдено.' });
  res.status(404).sendFile(path.join(PUBLIC_DIR, 'index.html'));
});

app.use((err, req, res, next) => {
  console.error('[error]', err.message);
  const status = err.type === 'entity.too.large' ? 413 : err.status || 500;
  const msg = status === 413 ? 'Слишком большой запрос.' : 'Внутренняя ошибка сервера.';
  if (req.path.startsWith('/api/')) return res.status(status).json({ ok: false, errors: [msg] });
  return res.status(status).send(msg);
});

if (require.main === module) {
  app.listen(PORT, () => {
    console.log(`VirshkeTech site: http://localhost:${PORT}`);
    console.log(`Админка: http://localhost:${PORT}/admin.html`);
    console.log(smtpConfigured() ? 'SMTP: настроен' : 'SMTP: не настроен (заявки пишутся только в data.json)');
  });
}

module.exports = app;
