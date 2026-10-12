<!-- templates/inner.tpl — Template «inner» (static): типовая внутренняя страница. -->

[[!breadcrumbs]]

<article class="container py-4">
  <h1 class="mb-3">[[*pagetitle]]</h1>
  [[*intro:notempty=`<p class="lead text-muted">[[*intro]]</p>`]]
  <div class="content">
    [[*content]]
  </div>
</article>
