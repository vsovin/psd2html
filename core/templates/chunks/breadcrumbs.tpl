<!-- chunks/breadcrumbs.tpl — pdoCrumbs + микроразметка BreadcrumbList -->
<nav aria-label="Хлебные крошки" class="container pt-3">
  <ol class="breadcrumb small mb-0" itemscope itemtype="https://schema.org/BreadcrumbList">
    [[!pdoCrumbs?
      &startLevel=`0`
      &showHome=`1`
      &homeName=`Главная`
      &toPlaceholder=`crumbs`
      &outerTpl=`@INLINE <ol class="breadcrumb small mb-0" itemscope itemtype="https://schema.org/BreadcrumbList">[[+wrapper]]</ol>`
      &rowTpl=`@INLINE <li class="breadcrumb-item" itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem"><a href="[[+uri]]" itemprop="item"><span itemprop="name">[[+pagetitle]]</span></a><meta itemprop="position" content="[[+idx]]"></li>`
      &hereTpl=`@INLINE <li class="breadcrumb-item active" aria-current="page" itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem"><span itemprop="name">[[+pagetitle]]</span><meta itemprop="position" content="[[+idx]]"></li>`
    ]]
  </ol>
</nav>
