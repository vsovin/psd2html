<!-- templates/article.tpl — Template «article» (static): пост блога. -->

[[!breadcrumbs]]

<article class="container py-4" itemscope itemtype="https://schema.org/Article">
  <h1 class="mb-3" itemprop="headline">[[*pagetitle]]</h1>
  <div class="text-muted small mb-4">
    <time datetime="[[+publishedon:date=`Y-m-dH:i`]]" itemprop="datePublished">[[+publishedon:date=`%d.%m.%Y`]]</time>
  </div>
  [[*og_image:notempty=`<img class="img-fluid rounded mb-4" src="[[*og_image]]" alt="[[*pagetitle]]" itemprop="image" loading="lazy">`]]
  <div itemprop="articleBody">
    [[*content]]
  </div>
</article>

<section class="container mt-5">
  <h2 class="h5">Читайте также</h2>
  [[!pdoResources? &parents=`[[*parent]]` &exclude=`[[*id]]` &limit=`3` &tpl=`blogTeaser` &sortdir=`DESC`]]
</section>
