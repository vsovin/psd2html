<!-- chunks/contact-form.tpl — форма обратной связи на FormIt (страница /kontakty/). -->
[[!FormIt?
    &hooks=`spam,email,FormItSaveForm`
    &emailTpl=`contactEmail`
    &emailTo=`[[++emailuser]]`
    &emailSubject=`Заявка с сайта PSD2HTML`
    &validate=`name:required:maxlength=`100`,email:required:email,phone:pregMatch=`/^[\d\s\+\-\(\)]{5,20}$/`,message:required:maxWords=`300``
    &errMsgs=`{"name":"Укажите имя","email":"Укажите корректный email","phone":"Телефон в неверном формате","message":"Напишите сообщение (до 300 слов)"}`
    &store=`1`
]]
<form action="[[~[[*id]]]" method="post" class="row g-3">
  <input type="hidden" name="nospam" value="">   <!-- honeypot для FormItSpam -->

  <div class="col-md-6">
    <label class="form-label" for="cf-name">Имя *</label>
    <input class="form-control [[!+fi.error.name]]" id="cf-name" name="name" value="[[!+fi.name]]" required maxlength="100">
    <div class="text-danger small mt-1">[[!+fi.error.name]]</div>
  </div>
  <div class="col-md-6">
    <label class="form-label" for="cf-email">Email *</label>
    <input class="form-control [[!+fi.error.email]]" id="cf-email" name="email" type="email" value="[[!+fi.email]]" required>
    <div class="text-danger small mt-1">[[!+fi.error.email]]</div>
  </div>
  <div class="col-md-6">
    <label class="form-label" for="cf-phone">Телефон</label>
    <input class="form-control [[!+fi.error.phone]]" id="cf-phone" name="phone" value="[[!+fi.phone]]">
    <div class="text-danger small mt-1">[[!+fi.error.phone]]</div>
  </div>
  <div class="col-12">
    <label class="form-label" for="cf-message">Сообщение *</label>
    <textarea class="form-control [[!+fi.error.message]]" id="cf-message" name="message" rows="4" required>[[!+fi.message]]</textarea>
    <div class="text-danger small mt-1">[[!+fi.error.message]]</div>
  </div>
  <div class="col-12 form-check ms-3">
    <input class="form-check-input" type="checkbox" id="cf-agree" name="agree" value="1" required>
    <label class="form-check-label small" for="cf-agree">Согласен с <a href="/politika-konfidentsialnosti/">политикой конфиденциальности</a></label>
  </div>
  <div class="col-12">
    <button class="btn btn-primary" type="submit" name="submit">Отправить</button>
  </div>

  [[!+fi.formit_success:eq=`1`:then=`<div class="alert alert-success col-12">Спасибо! Мы ответим на указанный email.</div>`]]
</form>

[[!FormItSuccessSnippet?
    &tpl=`@INLINE <input type="hidden" name="formit_success" value="1">`
    &useLastStep=`1`
]]
