"""Генератор HTML-страниц сайта (временный инструмент сборки, не часть сайта)."""
import os

tpl = '''<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{title}</title>
  <meta name="description" content="{desc}">
  <link rel="stylesheet" href="{r}assets/style.css">
</head>
<body>
<header class="site-header">
  <div class="container header-inner">
    <a class="logo" href="{r}index.html">Virshke<span>Tech</span>.com</a>
    <nav class="aud-switch" aria-label="Переключатель аудитории">
      <a class="pill" href="{r}cnc-pro/">У меня стойка Fanuc/Siemens</a>
      <a class="pill" href="{r}cnc-lite/">У меня Mach3/DDCS</a>
      <a class="pill" href="{r}manual/">У меня ручной станок</a>
    </nav>
    <a class="header-phone" href="tel:+70000000000" aria-label="Телефон для связи">+7 (000) 000-00-00</a>
  </div>
</header>
<main>
{content}
</main>
<footer class="site-footer">
  <div class="container footer-inner">
    <a class="logo" href="{r}index.html">Virshke<span>Tech</span>.com</a>
    <div>
      <a href="tel:+70000000000">+7 (000) 000-00-00</a> &middot;
      <a href="mailto:info@virshketech.com">info@virshketech.com</a>
    </div>
    <div>&copy; 2026 VirshkeTech. Все права защищены.</div>
  </div>
</footer>
<script src="{r}assets/script.js"></script>
</body>
</html>
'''

def calc_markup(nav, default_base='', res=''):
    return f'''<section class="section section-alt" id="calculator">
  <div class="container">
    <h2 class="section-title">Калькулятор комплектации</h2>
    <p class="section-lead">Выберите базу под ваш станок и дополнительные модули — итог пересчитается мгновенно, а схема покажет, куда встанет каждый модуль.</p>

    <div class="calc-grid" data-calculator data-default-base="{default_base}">
      <div class="calc-panel">
        <h3 id="calc-base-h">База (выберите одну)</h3>
        <fieldset class="opt-group" aria-labelledby="calc-base-h">
          <legend class="vis-hidden">Базовое решение ТФО-160</legend>
          <label class="opt"><input type="radio" name="calc-base" value="pro">
            <span class="opt-name">Для профессиональных ЧПУ</span><span class="opt-price">370 000 &#8381;</span></label>
          <label class="opt"><input type="radio" name="calc-base" value="lite">
            <span class="opt-name">Для ЧПУ начального уровня</span><span class="opt-price">340 000 &#8381;</span></label>
          <label class="opt"><input type="radio" name="calc-base" value="manual">
            <span class="opt-name">Для ручного станка</span><span class="opt-price">370 000 &#8381;</span></label>
        </fieldset>

        <h3 class="calc-subhead" id="calc-mod-h">Модули (можно несколько)</h3>
        <fieldset class="opt-group" aria-labelledby="calc-mod-h">
          <legend class="vis-hidden">Дополнительные модули</legend>
          <label class="opt"><input type="checkbox" name="calc-mod" value="chuck">
            <span class="opt-name">Модуль автоматизации токарного патрона</span><span class="opt-price">+120 000 &#8381;</span></label>
          <label class="opt"><input type="checkbox" name="calc-mod" value="vise">
            <span class="opt-name">Автозажим тисков</span><span class="opt-price">+95 000 &#8381;</span></label>
          <label class="opt"><input type="checkbox" name="calc-mod" value="tailstock">
            <span class="opt-name">Автоматическая задняя бабка</span><span class="opt-price">+140 000 &#8381;</span></label>
          <label class="opt"><input type="checkbox" name="calc-mod" value="robot">
            <span class="opt-name">Робот для подачи заготовок</span><span class="opt-price">+350 000 &#8381;</span></label>
        </fieldset>
      </div>

      <div class="calc-side">
        <div class="calc-panel calc-summary">
          <h3>Ваша комплектация</h3>
          <div data-role="summary-lines"><p class="calc-empty">Выберите базу — итог появится здесь.</p></div>
          <div class="sum-total"><span>Итого:</span><span data-role="summary-total">0 &#8381;</span></div>
          <p style="margin-top:18px">
            <a class="btn btn-primary btn-block" href="{nav}order/" data-role="order-btn" data-href="{nav}order/">Оформить заказ</a>
          </p>
        </div>
        <figure class="scheme-box">
          <div data-role="scheme" data-src="{res}assets/scheme.svg" role="img" aria-label="Схема станка: выбранные модули подсвечиваются оранжевым">
            <img src="{res}assets/scheme.svg" alt="Схема токарного станка с осью ТФО-160 и модулями">
          </div>
          <figcaption>Выбранные модули подсвечиваются оранжевым прямо на схеме.</figcaption>
        </figure>
      </div>
    </div>
  </div>
</section>'''

def order_form():
    return '''<form data-order-form novalidate>
      <div class="config-strip" data-role="config-strip"></div>
      <div class="field">
        <label for="f-name">Имя <span class="req">*</span></label>
        <input id="f-name" type="text" name="name" required autocomplete="name" placeholder="Иван Петров">
        <p class="err-msg" role="alert">Укажите имя.</p>
      </div>
      <div class="field">
        <label for="f-company">Компания</label>
        <input id="f-company" type="text" name="company" autocomplete="organization" placeholder="ООО «Станкоремонт»">
        <p class="err-msg" role="alert"></p>
      </div>
      <div class="field">
        <label for="f-phone">Телефон <span class="req">*</span></label>
        <input id="f-phone" type="tel" name="phone" required inputmode="tel" autocomplete="tel" placeholder="+7 (___) ___-__-__">
        <p class="err-msg" role="alert">Введите телефон полностью: +7 (XXX) XXX-XX-XX.</p>
      </div>
      <div class="field">
        <label for="f-email">E-mail</label>
        <input id="f-email" type="email" name="email" autocomplete="email" placeholder="you@company.ru">
        <p class="err-msg" role="alert">Проверьте формат e-mail.</p>
      </div>
      <div class="field">
        <label for="f-machine">Модель станка и стойка ЧПУ</label>
        <textarea id="f-machine" name="machine" placeholder="Например: DMG CTX 310, стойка Fanuc 0i-TF"></textarea>
        <p class="err-msg" role="alert"></p>
      </div>
      <input type="hidden" name="config" value="">
      <div class="field">
        <label class="consent"><input type="checkbox" name="consent" required>
          Согласен(на) на обработку персональных данных <span class="req">*</span></label>
        <p class="err-msg" role="alert">Без согласия мы не сможем обработать заявку.</p>
      </div>
      <button class="btn btn-primary btn-block" type="submit">Отправить заявку</button>
      <p class="form-note">Ответим в рабочее время в течение 2 часов. Заявка уходит на demo-адрес /api/order (заглушка).</p>
    </form>'''

pages = {}

# ---------------- ГЛАВНАЯ ----------------
r = ''  # корневые страницы: все пути относительно /
home = f'''<section class="hero">
  <div class="container hero-grid">
    <div>
      <h1>ТФО-160 — универсальная четвёртая ось</h1>
      <p class="sub">Подойдёт к любому станку. На какой станок будем ставить?</p>
      <div class="cta-row">
        <a class="btn btn-primary" href="#fork">Подобрать под мой станок</a>
        <a class="btn btn-outline" href="#calculator">Рассчитать комплектацию</a>
      </div>
    </div>
    <figure class="hero-scheme">
      <img src="{r}assets/scheme.svg" alt="Схема токарного станка с установленной осью ТФО-160">
    </figure>
  </div>
</section>

<section class="section" id="fork">
  <div class="container">
    <h2 class="section-title">На какой станок будем ставить?</h2>
    <p class="section-lead">ТФО-160 работает с тремя категориями станков. Функционал и способ подключения зависят от вашей стойки — выберите свой случай.</p>
    <div class="cards-3">
      <article class="aud-card">
        <span class="aud-tag">Профессиональные ЧПУ</span>
        <h3>Fanuc, Siemens, GSK, Mazak, Okuma, Haas</h3>
        <p class="brands">Ограниченный функционал, подключение блоком киоск или к импульсным драйверам.</p>
        <ul class="feat-list">
          <li>Поворот на заданный угол</li>
          <li>Нарезание зубчатых колёс</li>
          <li>Нарезание резьбы без передачи параметров</li>
        </ul>
        <p class="price-line">~370 000 ₽<small>Стоимость решения</small></p>
        <a class="card-link" href="cnc-pro/">Перейти</a>
      </article>
      <article class="aud-card featured">
        <span class="aud-tag">ЧПУ начального уровня</span>
        <h3>DDCS, Mach3, LinuxCNC, Inctra, Pumotix</h3>
        <p class="brands">Полный функционал, подключение реле / StepDir / ModBus.</p>
        <ul class="feat-list">
          <li>Все функции оси в управляющей программе</li>
          <li>Полноценная 4-я координированная ось</li>
          <li>Автоматические циклы</li>
        </ul>
        <p class="price-line">~340 000 ₽<small>Стоимость решения</small></p>
        <a class="card-link" href="cnc-lite/">Перейти</a>
      </article>
      <article class="aud-card">
        <span class="aud-tag">Ручные станки</span>
        <h3>Токарные и фрезерные станки без ЧПУ</h3>
        <p class="brands">Полный функционал с ручным управлением; при установке моторов — автоматические циклы.</p>
        <ul class="feat-list">
          <li>Ручное позиционирование оси</li>
          <li>Высокая точность деления</li>
          <li>Моторизация — по желанию</li>
        </ul>
        <p class="price-line">~370 000 ₽<small>Стоимость решения</small></p>
        <a class="card-link" href="manual/">Перейти</a>
      </article>
    </div>
  </div>
</section>

<section class="section">
  <div class="container">
    <h2 class="section-title">Основные технические характеристики ТФО-160</h2>
    <p class="section-lead">Полный паспорт предоставляется по запросу.</p>
    <table class="spec-table">
      <tbody>
        <tr><th scope="row">Тип</th><td>Универсальная токарно-фрезерная четвёртая ось</td></tr>
        <tr><th scope="row">Диаметр обрабатываемой заготовки</th><td>до 160 мм</td></tr>
        <tr><th scope="row">Расстояние между центрами</th><td class="tbd">уточняется</td></tr>
        <tr><th scope="row">Деление</th><td>круговое, произвольный угол</td></tr>
        <tr><th scope="row">Точность позиционирования</th><td class="tbd">уточняется</td></tr>
        <tr><th scope="row">Привод</th><td class="tbd">уточняется (серво-/шаговый двигатель)</td></tr>
        <tr><th scope="row">Варианты подключения</th><td>Блок киоск, импульсные драйверы, реле, StepDir, ModBus</td></tr>
        <tr><th scope="row">Совместимость со стойками</th><td>Fanuc, Siemens, GSK, Mazak, Okuma, Haas, DDCS, Mach3, LinuxCNC, Inctra, Pumotix</td></tr>
        <tr><th scope="row">Габариты / масса</th><td class="tbd">уточняется</td></tr>
        <tr><th scope="row">Гарантия</th><td class="tbd">уточняется</td></tr>
      </tbody>
    </table>
  </div>
</section>

{calc_markup('', res=r)}

<section class="section" id="order">
  <div class="container">
    <h2 class="section-title">Форма заказа</h2>
    <p class="section-lead">Заполните форму — подберём решение под ваш станок и пришлём коммерческое предложение.</p>
    <div class="form-card" data-order-root data-calc-path="#calculator" data-thanks-path="thanks/">
      {order_form()}
    </div>
  </div>
</section>'''

pages['/workspace/index.html'] = tpl.format(
    title='ТФО-160 — универсальная четвёртая ось | VirshkeTech',
    desc='Токарно-фрезерная ось ТФО-160: установка на профессиональные ЧПУ (Fanuc, Siemens), ЧПУ начального уровня (Mach3, DDCS) и ручные станки.',
    p='', r='', content=home)

# ---------------- /calculator/ ----------------
r = '../'
pages['/workspace/calculator/index.html'] = tpl.format(
    title='Калькулятор комплектации ТФО-160 | VirshkeTech',
    desc='Рассчитайте комплектацию оси ТФО-160: база для вашего станка плюс модули автоматизации.',
    p='', r='../', content=f'''<nav class="breadcrumbs container" aria-label="Хлебные крошки"><a href="{r}index.html">Главная</a> / Калькулятор</nav>
<section class="section">
  <div class="container">
    <h1 class="page-title">Калькулятор комплектации ТФО-160</h1>
    <p class="section-lead">Пришли с конкретной страницы? База уже выбрана. Меняйте состав — итог и схема обновляются сразу.</p>
  </div>
</section>
{calc_markup('../', res='../')}''')

# ---------------- /order/ ----------------
pages['/workspace/order/index.html'] = tpl.format(
    title='Заказ ТФО-160 | VirshkeTech',
    desc='Форма заявки на токарно-фрезерную ось ТФО-160 с выбранной комплектацией.',
    p='', r='../', content=f'''<nav class="breadcrumbs container" aria-label="Хлебные крошки"><a href="{r}index.html">Главная</a> / Заказ</nav>
<section class="section">
  <div class="container">
    <h1 class="page-title">Оформить заказ</h1>
    <p class="section-lead">Ваши контакты и конфигурация из калькулятора — в одной заявке.</p>
    <div class="form-card" data-order-root data-calc-path="../calculator/" data-thanks-path="../thanks/">
      {order_form()}
    </div>
  </div>
</section>''')

# ---------------- /thanks/ ----------------
pages['/workspace/thanks/index.html'] = tpl.format(
    title='Спасибо за заявку | VirshkeTech',
    desc='Заявка отправлена.',
    p='', r='../', content='''<section class="thanks-wrap">
  <div class="container">
    <div class="thanks-card">
      <div class="big" aria-hidden="true">&#9989;</div>
      <h1>Спасибо за заявку!</h1>
      <p class="muted">Мы получили вашу конфигурацию и свяжемся с вами в течение 2 рабочих часов.</p>
      <p style="margin-top:24px"><a class="btn btn-primary" href="../index.html">Вернуться на главную</a></p>
    </div>
  </div>
</section>''')

# ---------------- A/B/C ----------------
def aud_page(h1, sub, feats_html, connect_title, connect_items, price_str, examples, base_key, calc_label):
    ex_html = '\n'.join(f'''      <article class="example-card">
        <h4>{t}</h4>
        <p>{d}</p>
      </article>''' for t, d in examples)
    items = '\n        '.join(f'<li>{i}</li>' for i in connect_items)
    return f'''<nav class="breadcrumbs container" aria-label="Хлебные крошки"><a href="../index.html">Главная</a> / {calc_label}</nav>
<section class="hero">
  <div class="container hero-grid">
    <div>
      <h1>{h1}</h1>
      <p class="sub">{sub}</p>
      <div class="cta-row">
        <a class="btn btn-primary" href="../calculator/?base={base_key}">Рассчитать комплектацию</a>
        <a class="btn btn-outline" href="../order/">Оставить заявку</a>
      </div>
      <div class="price-badge">Стоимость решения: ~{price_str} ₽</div>
    </div>
    <figure class="hero-scheme">
      <img src="../assets/scheme.svg" alt="Схема станка с осью ТФО-160">
    </figure>
  </div>
</section>

<section class="section">
  <div class="container">
    <h2 class="section-title">Что доступно с ТФО-160</h2>
    <div class="info-grid">
      <div class="info-block">
        <h3>Функции оси</h3>
        <ul class="feat-list">{feats_html}</ul>
      </div>
      <div class="info-block">
        <h3>{connect_title}</h3>
        <ul>
        {items}
        </ul>
      </div>
    </div>
  </div>
</section>

<section class="section section-alt">
  <div class="container">
    <h2 class="section-title">Примеры подключения</h2>
    <p class="section-lead">Кейсы установки на станки этой категории (демонстрационные описания).</p>
{ex_html}
  </div>
</section>

<section class="section">
  <div class="container">
    <h2 class="section-title">Соберите свою комплектацию</h2>
    <p class="section-lead">База «{calc_label}» будет выбрана автоматически — добавьте нужные модули.</p>
    <a class="btn btn-primary" href="../calculator/?base={base_key}">Открыть калькулятор</a>
  </div>
</section>'''

pages['/workspace/cnc-pro/index.html'] = tpl.format(
    title='ТФО-160 для профессиональных ЧПУ (Fanuc, Siemens) | VirshkeTech',
    desc='Четвёртая ось ТФО-160 для стоек Fanuc, Siemens, GSK, Mazak, Okuma, Haas. Подключение блоком киоск или к импульсным драйверам.',
    p='../', r='../', content=aud_page(
        'ТФО-160 для профессиональных ЧПУ',
        'Fanuc, Siemens, GSK, Mazak, Okuma, Haas — ось встраивается в существующую стойку без вмешательства в её программную часть.',
        '<li>Поворот на заданный угол</li>\n          <li>Нарезание зубчатых колёс</li>\n          <li>Нарезание резьбы без передачи параметров</li>\n          <li class="no">Без полноценной 4-й координированной оси — функционал ограничен</li>',
        'Метод подключения',
        ['Через блок киоск — операторский пульт управления осью',
         'Либо подключение к импульсным драйверам стойки',
         'Без изменения прошивки и параметров ЧПУ'],
        '370 000',
        [('Fanuc 0i-MF, вертикальный обрабатывающий центр', 'Ось подключена блоком киоск: оператор задаёт угол на пульте, станок выполняет деление и фрезеровку пазов.'),
         ('Siemens 828D, токарный станок', 'Подключение к импульсным выходам, нарезание зубчатых колёс.'),
         ('Haas Mini Mill', 'Блок киоск, поворотные операции без перепрограммирования стойки.')],
        'pro', 'Для профессиональных ЧПУ'))

pages['/workspace/cnc-lite/index.html'] = tpl.format(
    title='ТФО-160 для ЧПУ начального уровня (Mach3, DDCS) | VirshkeTech',
    desc='Полноценная четвёртая ось ТФО-160 для Mach3, DDCS, LinuxCNC, Inctra, Pumotix. Подключение реле / StepDir / ModBus.',
    p='../', r='../', content=aud_page(
        'ТФО-160 для ЧПУ начального уровня',
        'DDCS, Mach3, LinuxCNC, Inctra, Pumotix — полный функционал: ТФО-160 становится настоящей четвёртой координированной осью.',
        '<li>Полный функционал оси в управляющей программе</li>\n          <li>Поворот на заданный угол из G-кода</li>\n          <li>Интерполяционная 4-осевая обработка</li>\n          <li>Автоматические циклы</li>',
        'Метод подключения',
        ['StepDir — напрямую к контроллеру ЧПУ как шаговая ось',
         'Релейное подключение для станков с дискретными входами',
         'ModBus — для стоек с промышленной шиной',
         'Ось видна управляющей программе как координата A'],
        '340 000',
        [('Mach3 + самодельный фрезерный стол', 'Подключение по StepDir, добавлена координата A, полная 4-осевая интерполяция.'),
         ('LinuxCNC на токарном 16К20', 'ModBus-подключение: автоматическое деление и нарезание резьбы на оси.'),
         ('DDCS-стойка на учебном станке', 'Релейное подключение, циклы запускаются с пульта.')],
        'lite', 'Для ЧПУ начального уровня'))

pages['/workspace/manual/index.html'] = tpl.format(
    title='ТФО-160 для ручных станков | VirshkeTech',
    desc='Четвёртая ось ТФО-160 для ручных токарных и фрезерных станков: ручное управление, моторизация и автоматические циклы.',
    p='../', r='../', content=aud_page(
        'ТФО-160 для ручных станков',
        'Нет ЧПУ — не проблема. Ось работает в ручном режиме с высокой точностью деления, а при установке моторов доступны автоматические циклы.',
        '<li>Полный функционал с ручным управлением</li>\n          <li>Точное круговое деление, отсчёт угла</li>\n          <li>Нарезание зубчатых колёс и резьбы вручную</li>\n          <li>Автоматические циклы при установке моторов</li>',
        'Метод подключения',
        ['Механическая установка на станину или стол станка',
         'Ручной привод с фиксацией и отсчётом угла',
         'Моторы — опционально, переводят ось в режим автоциклов',
         'Стойка ЧПУ не требуется'],
        '370 000',
        [('Токарно-винторезный 1М63', 'Изготовление червячных колёс малыми сериями в ручном режиме.'),
         ('Фрезерный 6Р13 без ЧПУ', 'Делительная оснастка; повторяемость цикла выдерживается отсчётом угла.'),
         ('Учебный станок ТВ-16', 'Работа в ручном режиме; позже установлены моторы — включены автоциклы.')],
        'manual', 'Для ручного станка'))

for path, html in pages.items():
    os.makedirs(os.path.dirname(path), exist_ok=True)
    with open(path, 'w', encoding='utf-8') as f:
        f.write(html)
    print('written', path, len(html))
