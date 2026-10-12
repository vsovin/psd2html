<!-- chunks/cta-manual.tpl — слот CTA «Заказать ручную вёрстку этого макета» (Этап 2).
     На Этапе 1 скрыт классом is-hidden; включается системной настройкой psd2html.ctaEnabled.
     data-conversion-token — контекст: макет уже загружен, повторная загрузка не потребуется. -->
<div class="cta-manual is-hidden mt-4" id="cta-manual-slot"
     data-conversion-token="[[+token:default=``]]">
  <div class="row align-items-center g-3">
    <div class="col-md-9">
      <h3 class="h6 mb-1">Не устраивает автовёрстка?</h3>
      <p class="small mb-0">Закажите профессиональную ручную вёрстку этого макета: pixel-perfect, адаптив, кроссбраузерность. От [[++psd2html.pricePerScreen:default=`3500`]] ₽ за экран.</p>
    </div>
    <div class="col-md-3 text-md-end">
      <a class="btn btn-warning" href="/zakaz-vertki/?src=[[+token:default=``]]" rel="nofollow">Заказать ручную вёрстку</a>
    </div>
  </div>
</div>
