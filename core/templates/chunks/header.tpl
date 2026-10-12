<!-- chunks/header.tpl — шапка с навигацией (pdoMenu). base-шаблон. -->
<header class="site-header border-bottom bg-white sticky-top">
  <div class="container py-2 d-flex align-items-center justify-content-between gap-3">
    <a class="navbar-brand fw-bold text-decoration-none" href="/">
      PSD<span class="text-primary">2</span>HTML
    </a>
    <nav aria-label="Основная навигация">
      [[!pdoMenu?
          &startLevel=`0`
          &parents=`[[++site_start]]`
          &depth=`1`
          &hereClass=`active`
          &outerTpl=`@INLINE <ul class="nav gap-1 flex-row justify-content-end">[[+wrapper]]</ul>`
          &rowTpl=`@INLINE <li class="nav-item"><a class="nav-link px-2 [[+classes]]" href="[[+link]]">[[+menuname]]</a></li>`
      ]]
    </nav>
    <!-- Кнопка ЛК появится на Этапе 2 -->
    <a class="btn btn-sm btn-outline-secondary d-none" href="/lk/" rel="nofollow">Личный кабинет</a>
  </div>
</header>
