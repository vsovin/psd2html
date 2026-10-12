<!-- chunks/footer.tpl — подвал + Bootstrap JS (defer) + инициализация PSD2HTML. -->
<footer class="site-footer border-top py-4 mt-5 bg-light">
  <div class="container">
    <div class="row g-3 align-items-center">
      <div class="col-md-6">
        <div class="fw-bold mb-1">PSD2HTML</div>
        <div class="small text-muted">&copy; [[!date? &date=`%Y`]] — конвертер PSD в HTML5. Все права защищены.</div>
      </div>
      <div class="col-md-6 text-md-end">
        <nav aria-label="Дополнительная навигация" class="d-inline-flex flex-wrap gap-3 small">
          <a href="/o-servise/" class="text-decoration-none">О сервисе</a>
          <a href="/tarify/" class="text-decoration-none">Тарифы</a>
          <a href="/faq/" class="text-decoration-none">FAQ</a>
          <a href="/kontakty/" class="text-decoration-none">Контакты</a>
          <a href="/politika-konfidentsialnosti/" class="text-decoration-none">Политика конфиденциальности</a>
          <a href="/polzovatelskoe-soglashenie/" class="text-decoration-none">Пользовательское соглашение</a>
        </nav>
      </div>
    </div>
  </div>
</footer>

<!-- scripts.tpl вынесен отдельно; здесь только inline-подключение, если чанк не используется -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous" defer></script>
<script>window.PSD2HTML = {
    maxFileSizeMb: '[[++psd2html.maxFileSizeMb:default=`30`]]',
    apiPath: '/api',
    ctaEnabled: [[++psd2html.ctaEnabled:default=`0`]] ? true : false
};</script>
<script src="/assets/components/psd2html/js/converter.js" defer></script>
