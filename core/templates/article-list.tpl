<!-- templates/article-list.tpl — Template «article-list» (static): контейнер /blog/. -->

[[!breadcrumbs]]

<section class="container py-4">
  <h1 class="mb-4">[[*pagetitle]]</h1>

  [[!pdoResources?
    &parents=`[[*id]]`
    &depth=`1`
    &tpl=`blogTeaser`
    &limit=`9`
    &sortdir=`DESC`
    &sortby=`publishedon`
    &toPlaceholder=`posts`
  ]]

  <div class="row g-4">
    [[+posts]]
  </div>

  <nav aria-label="Пагинация" class="mt-5">
    [[!pageNav? &pageName=`p` &limits=`9`]]
  </nav>
</section>

<!-- чанк blogTeaser (создать в manager или как static core/templates/chunks/blogTeaser.tpl) -->
