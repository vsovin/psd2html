<!-- chunks/faq-block.tpl — FAQ с микроразметкой schema.org/FAQPage (Задача 5, Задача 6).
     Используется на главной и на странице /faq/. Вопросы-ответы — из TV или прямого HTML ниже. -->
<section class="py-5 bg-light" id="faq">
  <div class="container">
    <h2 class="text-center mb-4">Частые вопросы</h2>
    <div itemscope itemtype="https://schema.org/FAQPage">
      <div class="accordion" id="faqAccordion">

        <div class="accordion-item" itemscope itemprop="mainEntity" itemtype="https://schema.org/Question">
          <h3 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq1" itemprop="name">Это правда бесплатно?</button></h3>
          <div id="faq1" class="accordion-collapse collapse" data-bs-parent="#faqAccordion" itemscope itemprop="acceptedAnswer" itemtype="https://schema.org/Answer">
            <div class="accordion-body" itemprop="text">Да. Автокoнвертация PSD в HTML5 бесплатна с лимитом [[++psd2html.dailyLimitIp:default=`5`]] файлов в сутки до [[++psd2html.maxFileSizeMb:default=`30`]] МБ.</div>
          </div>
        </div>

        <div class="accordion-item" itemscope itemprop="mainEntity" itemtype="https://schema.org/Question">
          <h3 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq2" itemprop="name">Что я получу на выходе?</button></h3>
          <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion" itemscope itemprop="acceptedAnswer" itemtype="https://schema.org/Answer">
            <div class="accordion-body" itemprop="text">ZIP-архив: index.html (семантический HTML5), style.css и папка assets с изображениями слоёв. Макет фиксирует геометрию PSD; для адаптивности используйте ручную вёрстку.</div>
          </div>
        </div>

        <div class="accordion-item" itemscope itemprop="mainEntity" itemtype="https://schema.org/Question">
          <h3 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq3" itemprop="name">Куда попадают мои файлы?</button></h3>
          <div id="faq3" class="accordion-collapse collapse" data-bs-parent="#faqAccordion" itemscope itemprop="acceptedAnswer" itemtype="https://schema.org/Answer">
            <div class="accordion-body" itemprop="text">Файлы хранятся во временном защищённом хранилище вне публичной директории и автоматически удаляются через [[++psd2html.fileTtlHours:default=`72`]] часа. Мы не передаём их третьим лицам.</div>
          </div>
        </div>

        <div class="accordion-item" itemscope itemprop="mainEntity" itemtype="https://schema.org/Question">
          <h3 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq4" itemprop="name">Почему вёрстка не адаптивная?</button></h3>
          <div id="faq4" class="accordion-collapse collapse" data-bs-parent="#faqAccordion" itemscope itemprop="acceptedAnswer" itemtype="https://schema.org/Answer">
            <div class="accordion-body" itemprop="text">Автоконвертер воспроизводит макет «как в Photoshop». Адаптивность, flex/grid и чистая структура — задача ручной вёрстки: закажите её, когда результат автoконверта вас не устроит.</div>
          </div>
        </div>

      </div>
    </div>
  </div>
</section>
