/* ============================================================
   VirshkeTech — общий JS: калькулятор, localStorage, маска
   телефона, фильтр каталога, активная пилюля, тень header.
   Vanilla JS, без зависимостей (кроме Bootstrap для модалки).
   ============================================================ */

/* ------------------------------------------------------------
   CONFIG — цены и названия. Менять только здесь.
   key — технический идентификатор, используется в localStorage.
   scheme — id элемента SVG-схемы, подсвечиваемого при выборе.
   ------------------------------------------------------------ */
const CONFIG = {
  currency: "RUB",
  locale: "ru-RU",
  storageKey: "virshke_config",
  bases: [
    { key: "pro",  name: "ТФО-160 для профессиональных ЧПУ (Fanuc, Siemens, Mazak)", price: 370000 },
    { key: "lite", name: "ТФО-160 для ЧПУ начального уровня (Mach3, DDCS, LinuxCNC)", price: 340000, checked: true },
    { key: "manual", name: "ТФО-160 для ручного станка", price: 370000 }
  ],
  modules: [
    { key: "chuck",   name: "Модуль автоматизации токарного патрона", price: 120000, scheme: "m-chuck" },
    { key: "vise",    name: "Автозажим тисков",                      price: 95000,  scheme: "m-vise" },
    { key: "tailstock", name: "Автоматическая задняя бабка",          price: 140000, scheme: "m-tail" },
    { key: "robot",   name: "Робот для подачи заготовок",             price: 350000, scheme: "m-robot" }
  ]
};

const fmtPrice = (n) =>
  new Intl.NumberFormat(CONFIG.locale, { style: "currency", currency: CONFIG.currency, maximumFractionDigits: 0 }).format(n);

/* ------------------------------------------------------------
   Header: тень при скролле + активная пилюля переключателя
   ------------------------------------------------------------ */
function initHeader() {
  const header = document.querySelector(".vt-header");
  if (header) {
    const onScroll = () => header.classList.toggle("vt-scrolled", window.scrollY > 8);
    onScroll();
    window.addEventListener("scroll", onScroll, { passive: true });
  }

  const here = location.pathname.split("/").pop() || "index.html";
  document.querySelectorAll(".vt-aud-switch .nav-link").forEach((link) => {
    const target = link.getAttribute("href").split("/").pop();
    if (target === here) {
      link.classList.add("active");
      link.setAttribute("aria-current", "page");
    }
  });
}

/* ------------------------------------------------------------
   Калькулятор (только на calculator.html)
   Конфигурация пишется в localStorage под ключом virshke_config:
   { base: "lite", modules: ["chuck"], total: 460000, items: [...] }
   ------------------------------------------------------------ */
function initCalculator() {
  const form = document.getElementById("calc-form");
  if (!form) return;

  const summaryEl = document.getElementById("calc-lines");
  const totalEl = document.getElementById("calc-total");
  const orderBtn = document.getElementById("calc-order-btn");

  const readState = () => {
    const base = form.querySelector('input[name="base"]:checked');
    const mods = [...form.querySelectorAll('input[name="module"]:checked')];
    return { base, mods };
  };

  const recalc = () => {
    const { base, mods } = readState();
    const items = [];
    let total = 0;

    if (base) {
      const b = CONFIG.bases.find((x) => x.key === base.value);
      items.push({ type: "base", key: b.key, name: b.name, price: b.price });
      total += b.price;
    }

    mods.forEach((input) => {
      const m = CONFIG.modules.find((x) => x.key === input.value);
      items.push({ type: "module", key: m.key, name: m.name, price: m.price });
      total += m.price;
    });

    /* Подсветка карточек-опций */
    form.querySelectorAll(".vt-calc-option").forEach((card) => card.classList.remove("selected"));
    if (base) base.closest(".vt-calc-option")?.classList.add("selected");
    mods.forEach((m) => m.closest(".vt-calc-option")?.classList.add("selected"));

    /* Подсветка модулей на SVG-схеме (inline-SVG с id="vt-scheme") */
    document.querySelectorAll("#vt-scheme [data-module]").forEach((el) => {
      el.classList.toggle("on", mods.some((m) => m.value === el.dataset.module));
    });

    /* Список выбранных позиций */
    if (summaryEl) {
      summaryEl.innerHTML = items.length
        ? items
            .map(
              (i) =>
                `<li class="list-group-item d-flex justify-content-between align-items-center">${i.name}<span class="fw-semibold text-vt-brand">${fmtPrice(i.price)}</span></li>`
            )
            .join("")
        : '<li class="list-group-item text-vt-muted">Ничего не выбрано</li>';
    }

    if (totalEl) totalEl.textContent = fmtPrice(total);

    if (orderBtn) orderBtn.disabled = !base;

    /* Сохраняем конфигурацию для страницы заказа */
    const data = {
      base: base ? base.value : null,
      modules: mods.map((m) => m.value),
      items,
      total
    };
    try {
      localStorage.setItem(CONFIG.storageKey, JSON.stringify(data));
    } catch (e) {
      /* localStorage может быть недоступен — игнорируем */
    }
  };

  form.addEventListener("change", recalc);
  recalc();
}

/* ------------------------------------------------------------
   Форма заказа: сводка из localStorage + маска телефона + отправка
   ------------------------------------------------------------ */
function loadConfig() {
  try {
    return JSON.parse(localStorage.getItem(CONFIG.storageKey) || "null");
  } catch (e) {
    return null;
  }
}

function initOrder() {
  const form = document.getElementById("order-form");
  const box = document.getElementById("config-summary");
  if (!form && !box) return;

  const cfg = loadConfig();

  /* Сводка выбранной комплектации над формой */
  if (box && cfg && cfg.items && cfg.items.length) {
    box.hidden = false;
    box.innerHTML = `
      <h3 class="h5 mb-2">Ваша комплектация</h3>
      <ul class="list-unstyled mb-2">
        ${cfg.items.map((i) => `<li>${i.name} — <span class="fw-semibold">${fmtPrice(i.price)}</span></li>`).join("")}
      </ul>
      <p class="mb-0 fw-bold">Итого: ${fmtPrice(cfg.total)}</p>`;
  }

  if (!form) return;

  /* Переносим конфигурацию в скрытые поля */
  const hiddenCfg = form.querySelector('input[name="config"]');
  const hiddenTotal = form.querySelector('input[name="total"]');
  if (hiddenCfg) hiddenCfg.value = cfg ? JSON.stringify(cfg) : "";
  if (hiddenTotal) hiddenTotal.value = cfg ? String(cfg.total) : "";

  /* Маска телефона +7 (___) ___-__-__ */
  const phone = form.querySelector('input[name="phone"]');
  if (phone) {
    const MASK = "+7 (XXX) XXX-XX-XX";
    const applyMask = (value) => {
      const digits = value.replace(/\D/g, "").replace(/^7/, "").slice(0, 10);
      let out = "+7 (";
      if (digits.length > 0) out += digits.slice(0, 3);
      if (digits.length >= 3) out += ") ";
      if (digits.length > 3) out += digits.slice(3, 6);
      if (digits.length >= 6) out += "-";
      if (digits.length > 6) out += digits.slice(6, 8);
      if (digits.length >= 8) out += "-";
      if (digits.length > 8) out += digits.slice(8, 10);
      return out;
    };
    phone.addEventListener("input", () => {
      const caretEnd = phone.selectionStart >= phone.value.length;
      phone.value = applyMask(phone.value);
      if (caretEnd) phone.setSelectionRange(phone.value.length, phone.value.length);
    });
    phone.addEventListener("focus", () => {
      if (!phone.value || phone.value === "+7 (") phone.value = "+7 (";
    });
  }

  /* Валидация Bootstrap + «отправка» */
  form.addEventListener("submit", (event) => {
    event.preventDefault();
    event.stopPropagation();

    if (!form.checkValidity()) {
      form.classList.add("needs-validation");
      return;
    }

    const payload = Object.fromEntries(new FormData(form).entries());
    sendOrder(payload);
  });
}

/*
 * Реальной отправки нет (сайт статический).
 * При подключении бэкенда заменить console.log на, например:
 *   fetch("/api/order", { method: "POST", headers: {"Content-Type": "application/json"}, body: JSON.stringify(config) })
 *     .then(r => r.ok ? showThanksModal() : alert("Ошибка отправки"));
 */
function sendOrder(config) {
  console.log("VirshkeTech · заявка:", config);
  showThanksModal();
}

function showThanksModal() {
  const el = document.getElementById("thanks-modal");
  if (!el) return;
  const modal = new bootstrap.Modal(el);
  el.addEventListener("shown.bs.modal", () => {
    setTimeout(() => {
      modal.hide();
      location.href = "thanks.html";
    }, 2000);
  });
  modal.show();
}

/* ------------------------------------------------------------
   Мини-форма «Задать вопрос» на contacts.html — та же логика
   ------------------------------------------------------------ */
function initQuestionForm() {
  const form = document.getElementById("question-form");
  if (!form) return;
  form.addEventListener("submit", (event) => {
    event.preventDefault();
    event.stopPropagation();
    if (!form.checkValidity()) {
      form.classList.add("needs-validation");
      return;
    }
    sendOrder(Object.fromEntries(new FormData(form).entries()));
  });
}

/* ------------------------------------------------------------
   Фильтр каталога (products.html): кнопки data-filter по data-cat
   ------------------------------------------------------------ */
function initCatalogFilter() {
  const wrap = document.getElementById("catalog");
  if (!wrap) return;
  const buttons = document.querySelectorAll(".vt-filter-btn");
  const cards = wrap.querySelectorAll("[data-cat]");

  buttons.forEach((btn) => {
    btn.addEventListener("click", () => {
      buttons.forEach((b) => b.classList.remove("active"));
      btn.classList.add("active");
      const f = btn.dataset.filter;
      cards.forEach((card) => {
        card.hidden = f !== "all" && !card.dataset.cat.split(" ").includes(f);
      });
    });
  });
}

/* ------------------------------------------------------------ */
document.addEventListener("DOMContentLoaded", () => {
  initHeader();
  initCalculator();
  initOrder();
  initQuestionForm();
  initCatalogFilter();
});
