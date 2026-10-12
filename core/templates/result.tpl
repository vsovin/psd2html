<!-- templates/result.tpl — Template «result» (static): страница предпросмотра результата.
     Ресурс /result/ с плейсхолдером-сегментом; данные подставляет сниппет psd2htmlResult
     (читает modx_psd2html_history по токену из URL). Ресурс: hidemenu=1, cacheable=0, noindex. -->

[[!psd2htmlResult? &token=`[[+segment]]`]]

<section class="container py-4">
  <h1 class="mb-3">Результат конвертации: [[+p2h.filename]]</h1>
  <p class="text-muted small">Слоёв обработано: [[+p2h.layers_used]] из [[+p2h.layers_total]]. Страница доступна до [[+p2h.expires]].</p>

  <div class="row g-4">
    <div class="col-lg-8">
      <img class="psd-result__preview img-fluid" src="/api/preview.php?token=[[+p2h.token]]" alt="Предпросмотр вёрстки [[+p2h.filename]]" loading="lazy">
    </div>
    <div class="col-lg-4">
      <div class="d-grid gap-2">
        <a class="btn btn-success btn-lg" href="/api/download.php?token=[[+p2h.token]]" rel="nofollow">Скачать ZIP</a>
        <a class="btn btn-outline-primary" href="/" >Конвертировать другой макет</a>
      </div>
    </div>
  </div>

  <!-- Слот CTA Этап 2: контекст передаётся токеном — повторная загрузка не потребуется -->
  [[!cta-manual? &token=`[[+p2h.token]]`]]
</section>
