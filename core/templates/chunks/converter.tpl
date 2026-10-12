<!-- chunks/converter.tpl — виджет автoконвертера (Задача 2). Шаблон home, первый экран. -->
<section class="py-4 py-lg-5" id="converter">
  <div class="container">
    <h1 class="h2 text-center mb-2">Онлайн-конвертер PSD в HTML5</h1>
    <p class="text-center text-muted mb-4">[[*intro]]</p>

    <!-- Зона drag-n-drop + выбор файла -->
    <div class="psd-drop" id="psd-drop" role="button" tabindex="0"
         aria-label="Перетащите файл PSD сюда или нажмите для выбора">
      <div class="psd-drop__icon" aria-hidden="true">&#128444;</div>
      <div class="fw-semibold mt-2">Перетащите .psd сюда или нажмите, чтобы выбрать файл</div>
      <div class="psd-drop__hint mt-1">Бесплатно, до [[++psd2html.maxFileSizeMb:default=`30`]] МБ, результат — ZIP с index.html, style.css и изображениями</div>
      <input type="file" id="psd-file" accept=".psd,image/vnd.adobe.photoshop" hidden>
    </div>

    <!-- Прогресс -->
    <div class="psd-progress mt-4" id="psd-progress" aria-live="polite">
      <div class="d-flex align-items-center gap-3">
        <div class="progress flex-grow-1" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
          <div class="progress-bar progress-bar-striped" id="psd-progress-bar" style="width:0%"></div>
        </div>
        <span class="badge text-bg-info psd-status-badge" id="psd-progress-label">Подготовка…</span>
      </div>
    </div>

    <!-- Сообщения об ошибках -->
    <div class="alert mt-3" id="psd-alert" role="alert" style="display:none"></div>

    <!-- Результат -->
    <div class="psd-result mt-4" id="psd-result">
      <div class="row g-4 align-items-start">
        <div class="col-lg-7">
          <img id="psd-preview-img" class="psd-result__preview img-fluid" alt="Предпросмотр результата" loading="lazy">
        </div>
        <div class="col-lg-5">
          <h2 class="h5">Конвертация завершена</h2>
          <p class="small text-muted" data-p2h-meta></p>
          <div class="d-grid gap-2">
            <a class="btn btn-success btn-lg" id="psd-download" href="#" rel="nofollow">Скачать ZIP</a>
            <a class="btn btn-outline-primary" id="psd-open" href="#" target="_blank" rel="noopener nofollow">Открыть предпросмотр</a>
            <button class="btn btn-link" id="psd-again" type="button">Конвертировать ещё один макет</button>
          </div>
        </div>
      </div>

      <!-- Слот CTA «Заказать ручную вёрстку» (Этап 2; по умолчанию скрыт) -->
      [[!cta-manual]]
    </div>
  </div>
</section>
