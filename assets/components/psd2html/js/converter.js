/**
 * PSD2HTML — клиент конвертера (Задача 2, JS).
 *
 * Жизненный цикл:
 *  1. init → GET /api/csrf.php (токен);
 *  2. выбор/перетаскивание файла → локальная валидация (расширение, размер) до отправки;
 *  3. XMLHttpRequest.upload.onprogress → прогресс ЗАГРУЗКИ (0–50%);
 *  4. после создания задачи — поллинг /api/status.php каждые 2с → прогресс ОБРАБОТКИ (50–99%);
 *  5. done → показ превью + кнопок «Скачать ZIP» / «Открыть предпросмотр»; error → сообщение;
 *  6. лимит времени поллинга — 5 мин, затем предлагать повторить/CTA.
 *
 * Никакого innerHTML из ответа сервера — только textContent и фиксированные URL с token-regex.
 */

(function () {
  'use strict';

  var MAX_MB = parseInt(window.PSD2HTML && window.PSD2HTML.maxFileSizeMb || '30', 10);
  var API = (window.PSD2HTML && window.PSD2HTML.apiPath) || '/api';
  var POLL_INTERVAL_MS = 2000;
  var POLL_TIMEOUT_MS = 5 * 60 * 1000;

  var dropZone, fileInput, progressWrap, progressBar, progressLabel,
      resultWrap, previewImg, btnDownload, btnOpen, alertBox, ctaSlot;

  var csrf = null;
  var pollTimer = null;
  var pollDeadline = 0;

  // ---------- утилиты ---------------------------------------------------------
  function $(sel) { return document.querySelector(sel); }

  function setAlert(type, text) {
    if (!alertBox) { return; }
    alertBox.className = 'alert alert-' + type;           // type из белого списка вызывающих
    alertBox.textContent = text;                          // textContent — XSS-safe
    alertBox.style.display = '';
  }
  function hideAlert() { if (alertBox) { alertBox.style.display = 'none'; } }

  function setProgress(pct, label) {
    progressWrap.classList.add('is-active');
    progressBar.style.width = Math.min(100, Math.max(0, pct)) + '%';
    progressBar.setAttribute('aria-valuenow', String(Math.round(pct)));
    if (label !== undefined) { progressLabel.textContent = label; }
  }
  function finishProgress() {
    progressWrap.classList.remove('is-active');
    progressBar.style.width = '0%';
  }
  function uiBusy(busy) {
    dropZone.classList.toggle('is-disabled', busy);
    if (busy) { hideAlert(); }
  }

  // ---------- CSRF ------------------------------------------------------------
  function fetchCsrf(cb) {
    fetch(API + '/csrf.php', { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (d) { csrf = d.ok ? d.csrf : null; cb(); })
      .catch(function () { cb(); });
  }

  // ---------- локальная валидация до отправки ----------------------------------
  function validateLocal(file) {
    if (!file) { return 'Файл не выбран.'; }
    if (!/\.psd$/i.test(file.name)) { return 'Можно загружать только файлы Photoshop (.psd).'; }
    if (file.size > MAX_MB * 1048576) { return 'Файл слишком большой: максимум ' + MAX_MB + ' МБ.'; }
    if (file.size === 0) { return 'Файл пустой.'; }
    return null;
  }

  // ---------- загрузка ----------------------------------------------------------
  function upload(file) {
    uiBusy(true);
    setProgress(0, 'Загрузка файла…');

    var fd = new FormData();
    fd.append('psd', file, file.name);
    fd.append('csrf', csrf || '');

    var xhr = new XMLHttpRequest();
    xhr.open('POST', API + '/upload.php', true);
    xhr.withCredentials = true;

    // Прогресс загрузки занимает шкалу 0–50%, дальше — очередь/обработка
    xhr.upload.onprogress = function (e) {
      if (e.lengthComputable) {
        setProgress((e.loaded / e.total) * 50, 'Загрузка файла… ' + Math.round(e.loaded / 1048576) + '/' + Math.round(e.total / 1048576) + ' МБ');
      }
    };
    xhr.onerror = function () {
      uiBusy(false); finishProgress();
      setAlert('danger', 'Не удалось связаться с сервером. Проверьте соединение и попробуйте ещё раз.');
    };
    xhr.onload = function () {
      var d = {};
      try { d = JSON.parse(xhr.responseText); } catch (e) { /* невалидный ответ */ }
      if (xhr.status === 201 && d.ok) {
        startPolling(d.task_uid);
      } else {
        uiBusy(false); finishProgress();
        var msg = (d.error && d.error.message) || 'Ошибка загрузки. Попробуйте ещё раз.';
        setAlert('danger', msg);
        if (d.error && d.error.code === 'limit') { showCta(); }   // лимит → подталкиваем к ручной вёрстке
      }
    };
    xhr.send(fd);
  }

  // ---------- поллинг статуса -----------------------------------------------------
  function startPolling(taskUid) {
    pollDeadline = Date.now() + POLL_TIMEOUT_MS;
    setProgress(55, 'Поставлено в очередь…');
    pollTimer = setInterval(function () { pollOnce(taskUid); }, POLL_INTERVAL_MS);
    pollOnce(taskUid);
  }

  function stopPolling() { if (pollTimer) { clearInterval(pollTimer); pollTimer = null; } }

  var lastPct = 55;
  function pollOnce(taskUid) {
    if (Date.now() > pollDeadline) {
      stopPolling(); uiBusy(false); finishProgress();
      setAlert('warning', 'Обработка затянулась. Задача может завершиться позже — обновите страницу через пару минут.');
      showCta();
      return;
    }
    fetch(API + '/status.php?task=' + encodeURIComponent(taskUid), { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (!d.ok) { throw new Error('bad'); }
        switch (d.status) {
          case 'queued':
            setProgress(Math.min(lastPct + 2, 65),
              d.queue_position ? ('В очереди: позиция ' + d.queue_position) : 'В очереди…');
            break;
          case 'processing':
            lastPct = Math.min(lastPct + 4, 92);
            setProgress(lastPct, 'Идёт обработка макета…');
            break;
          case 'done':
            stopPolling();
            setProgress(100, 'Готово!');
            setTimeout(showResult, 350, d.result);
            break;
          case 'error':
            stopPolling(); uiBusy(false); finishProgress();
            setAlert('danger', (d.error && d.error.message) || 'Ошибка обработки.');
            showCta();
            break;
        }
      })
      .catch(function () { /* временная ошибка сети — следующий тик */ });
  }

  // ---------- результат -------------------------------------------------------------
  function showResult(res) {
    uiBusy(false);
    finishProgress();
    // Финальная проверка формата токена перед подстановкой в URL (защита от инъекции в href/src)
    if (!res || !/^[0-9a-f]{16}$/.test(res.token)) { return; }
    previewImg.src = res.preview_url;
    previewImg.alt = 'Предпросмотр: ' + res.filename;
    btnDownload.href = res.download_url;
    btnOpen.href = res.page_url;
    var meta = $(' [data-p2h-meta]');
    if (meta) {
      meta.textContent = 'Слоёв обработано: ' + res.layers_used + ' из ' + res.layers_total + '. Файл доступен 72 часа.';
    }
    resultWrap.classList.add('is-active');
    resultWrap.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    showCta();                                   // слот CTA Этап 2 под результатом
    if (window.gtag) { window.gtag('event', 'conversion_done'); }  // опциональная аналитика
  }

  // ---------- CTA Этап 2 (пока заглушка) ----------------------------------------------
  function showCta() {
    if (!ctaSlot) { return; }
    if (window.PSD2HTML && PSD2HTML.ctaEnabled) {
      ctaSlot.classList.remove('is-hidden');     // включается на Этапе 2
    }
  }

  // ---------- инициализация -------------------------------------------------------------
  function init() {
    dropZone = $('#psd-drop');
    fileInput = $('#psd-file');
    progressWrap = $('#psd-progress');
    progressBar = $('#psd-progress-bar');
    progressLabel = $('#psd-progress-label');
    resultWrap = $('#psd-result');
    previewImg = $('#psd-preview-img');
    btnDownload = $('#psd-download');
    btnOpen = $('#psd-open');
    alertBox = $('#psd-alert');
    ctaSlot = $('#cta-manual-slot');

    if (!dropZone) { return; }

    fetchCsrf(function () {});

    // Клик по зоне → системный выбор файла
    dropZone.addEventListener('click', function () { fileInput.click(); });
    fileInput.addEventListener('change', function () {
      var err = validateLocal(fileInput.files[0]);
      if (err) { setAlert('danger', err); return; }
      upload(fileInput.files[0]);
    });

    // Drag-n-drop
    ['dragenter', 'dragover'].forEach(function (ev) {
      dropZone.addEventListener(ev, function (e) {
        e.preventDefault(); e.stopPropagation();
        dropZone.classList.add('is-dragover');
      });
    });
    ['dragleave', 'drop'].forEach(function (ev) {
      dropZone.addEventListener(ev, function (e) {
        e.preventDefault(); e.stopPropagation();
        dropZone.classList.remove('is-dragover');
      });
    });
    dropZone.addEventListener('drop', function (e) {
      var file = e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files[0];
      var err = validateLocal(file);
      if (err) { setAlert('danger', err); return; }
      upload(file);
    });

    // Кнопка «Конвертировать ещё один»
    var again = $('#psd-again');
    if (again) {
      again.addEventListener('click', function () {
        resultWrap.classList.remove('is-active');
        fileInput.value = '';
        hideAlert();
        lastPct = 55;
      });
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
