<!-- chunks/how-it-works.tpl — блок «Как это работает», 4 шага (Задача 6, главная). -->
<section class="py-5" id="how">
  <div class="container">
    <h2 class="text-center mb-5">Как это работает</h2>
    <div class="row g-4 text-center">
      <div class="col-6 col-lg-3">
        <div class="display-6 text-primary">&#128228;</div>
        <h3 class="h6">1. Загрузите PSD</h3>
        <p class="small text-muted">Перетащите файл макета Photoshop — до [[++psd2html.maxFileSizeMb:default=`30`]] МБ.</p>
      </div>
      <div class="col-6 col-lg-3">
        <div class="display-6 text-primary">&#9881;</div>
        <h3 class="h6">2. Автоматика</h3>
        <p class="small text-muted">Сервер читает структуру слоёв, экспортитрует изображения и собирает HTML/CSS.</p>
      </div>
      <div class="col-6 col-lg-3">
        <div class="display-6 text-primary">&#128065;</div>
        <h3 class="h6">3. Предпросмотр</h3>
        <p class="small text-muted">Сразу видите результат в браузере до скачивания.</p>
      </div>
      <div class="col-6 col-lg-3">
        <div class="display-6 text-primary">&#128230;</div>
        <h3 class="h6">4. Скачайте ZIP</h3>
        <p class="small text-muted">index.html + style.css + assets/ — готово к доработке.</p>
      </div>
    </div>

    <!-- Пример результата «до/после» -->
    <div class="row g-4 mt-5 align-items-center">
      <div class="col-md-6">
        <h3 class="h5">Пример: до и после</h3>
        <p class="text-muted">Слева — скриншот PSD-макета, справа — HTML5-вёрстка, полученная автоматически. Тексты стали настоящим текстом, слои — классами с осмысленными именами.</p>
        <a href="/kak-eto-rabotaet/" class="btn btn-outline-primary btn-sm">Подробнее</a>
      </div>
      <div class="col-md-6">
        <div class="ratio ratio-4x3 bg-light rounded border">
          <img src="/assets/img/examples/before-after.svg" alt="Пример конвертации PSD в HTML: до и после" loading="lazy" class="img-fluid p-3">
        </div>
      </div>
    </div>
  </div>
</section>
