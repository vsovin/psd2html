<!-- chunks/pricing-teaser.tpl — тарифы на главной: бесплатный автоконверт / ручная вёрстка (Этап 2 CTA). -->
<section class="py-5" id="pricing">
  <div class="container">
    <h2 class="text-center mb-5">Тарифы</h2>
    <div class="row g-4 justify-content-center">
      <div class="col-md-5 col-lg-4">
        <div class="card h-100 shadow-sm border-primary">
          <div class="card-body">
            <h3 class="card-title h5">Автоконвертер</h3>
            <div class="display-6 fw-bold text-success mb-2">0 ₽</div>
            <ul class="list-unstyled small">
              <li>&#10003; До [[++psd2html.dailyLimitIp:default=`5`]] файлов в сутки</li>
              <li>&#10003; PSD до [[++psd2html.maxFileSizeMb:default=`30`]] МБ</li>
              <li>&#10003; HTML5 + CSS + изображения слоёв</li>
              <li>&#10003; Хранение результата [[++psd2html.fileTtlHours:default=`72`]] ч</li>
            </ul>
          </div>
          <div class="card-footer bg-transparent border-0 pb-3">
            <a href="#converter" class="btn btn-success w-100">Конвертировать бесплатно</a>
          </div>
        </div>
      </div>
      <div class="col-md-5 col-lg-4">
        <div class="card h-100 shadow-sm">
          <div class="card-body">
            <h3 class="card-title h5">Ручная вёрстка «под ключ»</h3>
            <div class="display-6 fw-bold text-primary mb-2">от [[++psd2html.pricePerScreen:default=`3500`]] ₽</div>
            <ul class="list-unstyled small">
              <li>&#10003; Pixel-perfect по макету</li>
              <li>&#10003; Адаптив и кроссбраузерность</li>
              <li>&#10003; Семантика, БЭМ/Bootstrap на выбор</li>
              <li>&#10003; Срок от 1 рабочего дня за экран</li>
            </ul>
          </div>
          <div class="card-footer bg-transparent border-0 pb-3">
            <!-- Кнопка активна с Этапа 2; на Этапе 1 ведёт на заглушку-анкету -->
            <a href="/zakaz-vertki/" class="btn btn-primary w-100 [[!++psd2html.ctaEnabled:not=``:then=``:else=`disabled`]]">Заказать вёрстку</a>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>
