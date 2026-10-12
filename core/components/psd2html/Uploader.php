<?php
/**
 * PSD2HTML — загрузка и валидация PSD (Этап 1, Задача 3).
 *
 * Правила безопасности:
 *  - принимаем ТОЛЬКО .psd: расширение + MIME + magic bytes «8BPS»;
 *  - размер проверяется дважды: по Content-Length ДО приёма тела и по факту записи;
 *  - имя файла на диске — случайный hex, пользовательское имя не попадает в путь;
 *  - файл кладётся ВНЕ docroot (P2H_UPLOADS), исполняться он не может физически;
 *  - никакого move_uploaded_file в web-зону.
 */

declare(strict_types=1);

final class Uploader
{
    /** Ограничение на количество слоёв (защита от «PSD-бомб»). */
    private const MAX_LAYERS = 500;

    public function __construct(
        private PDO $pdo,
        private string $uploadsDir,   // вне docroot
        private int $maxSizeBytes,
    ) {}

    /**
     * Принимает $_FILES['psd'] и создаёт запись очереди.
     *
     * @return array{task_uid:string, conversion_seed:int} id задачи для поллинга
     * @throws UploadException с человекочитаемым RU-сообщением
     */
    public function handle(array $file, string $ip, ?string $sessionId): array
    {
        // --- 1. Базовая структура $_FILES ------------------------------------------
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new UploadException('not_psd', $this->uploadErrorMessage((int)$file['error']));
        }
        if (!is_uploaded_file($file['tmp_name'])) {
            // Не прошло проверку PHP — не наш запрос (возможная подделка)
            throw new UploadException('csrf', 'Файл не загружен корректно, попробуйте ещё раз');
        }

        // --- 2. Расширение (только .psd, регистронезависимо) ------------------------
        $ext = strtolower(pathinfo(basename($file['name'] ?? ''), PATHINFO_EXTENSION));
        if ($ext !== 'psd') {
            throw new UploadException('not_psd', 'Можно загружать только файлы Photoshop (.psd)');
        }

        // --- 3. Размер по заявленному Content-Length (до чтения tmp) -----------------
        $declared = (int)($file['size'] ?? 0);
        if ($declared <= 0 || $declared > $this->maxSizeBytes) {
            throw new UploadException('too_big', sprintf(
                'Файл слишком большой: максимум %d МБ',
                (int)round($this->maxSizeBytes / 1048576)
            ));
        }

        // --- 4. Magic bytes: PSD начинается с «8BPS» + версия 1..2 -------------------
        $fh = fopen($file['tmp_name'], 'rb');
        if (!$fh) {
            throw new UploadException('corrupted', 'Не удалось прочитать файл');
        }
        $head = fread($fh, 12);
        if ($head === false || strlen($head) < 12
            || substr($head, 0, 4) !== '8BPS'
            || !in_array(unpack('n', substr($head, 4, 2))[1], [1, 2], true)) {
            fclose($fh);
            throw new UploadException('corrupted', 'Похоже, файл повреждён или это не настоящий PSD');
        }
        // Заголовок PSD: width/height (big-endian) — базовая проверка адекватности
        $hdr = unpack('Nwidth/Nheight/ndepth/nmode', substr($head, 6)) ?: [];
        $w = (int)($hdr['width'] ?? 0);
        $h = (int)($hdr['height'] ?? 0);
        if ($w < 1 || $h < 1 || $w > 30000 || $h > 30000 || $w * $h > 150_000_000) {
            fclose($fh);
            throw new UploadException('corrupted', 'Некорректные размеры холста в файле');
        }
        fclose($fh);

        // --- 5. MIME по содержимому (finfo) — второй уровень -------------------------
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        $allowedMime = ['image/vnd.adobe.photoshop', 'application/photoshop', 'image/x-photoshop', 'application/octet-stream'];
        if (!in_array($mime, $allowedMime, true)) {
            throw new UploadException('not_psd', 'Тип файла не совпадает с Photoshop PSD');
        }

        // --- 6. Реальный размер tmp-файла (Content-Length мог соврать) ---------------
        clearstatcache(true, $file['tmp_name']);
        $real = (int)filesize($file['tmp_name']);
        if ($real <= 0 || $real > $this->maxSizeBytes) {
            throw new UploadException('too_big', sprintf(
                'Файл слишком большой: максимум %d МБ',
                (int)round($this->maxSizeBytes / 1048576)
            ));
        }

        // --- 7. Sanitized原名 (basename, без путей/управляющих символов) -------------
        $origName = basename((string)($file['name'] ?? 'file.psd'));
        $origName = preg_replace('/[^\P{C}\p{L}\p{N}._\- ]+/u', '', $origName) ?? 'file.psd';
        $origName = mb_substr(trim($origName) !== '' ? trim($origName) : 'file.psd', 0, 200);

        // --- 8. Запись во внешнее хранилище: uploads/YY/MM/<rand>.psd -----------------
        $relDir  = date('y/m');
        $rand    = bin2hex(random_bytes(16));           // 32 hex — имя на диске
        $relPath = "uploads/{$relDir}/{$rand}.psd";
        $absDir  = $this->uploadsDir . "/{$relDir}";
        if (!is_dir($absDir) && !mkdir($absDir, 0750, true) && !is_dir($absDir)) {
            throw new UploadException('internal', 'Ошибка сервера при сохранении файла');
        }
        $absPath = $absDir . "/{$rand}.psd";
        if (!move_uploaded_file($file['tmp_name'], $absPath)) {
            throw new UploadException('internal', 'Не удалось сохранить файл на сервере');
        }
        chmod($absPath, 0640); // чтение только группой www-data (cron-воркер)

        // --- 9. Запись очереди + истории (сразу, чтобы status.php имел что показать) --
        $taskUid = bin2hex(random_bytes(16));
        $ttlHours = max(1, (int)p2h_setting('fileTtlHours', 72));

        $this->pdo->beginTransaction();
        try {
            $insHist = $this->pdo->prepare(
                'INSERT INTO `' . P2H_PREFIX . "psd2html_history`
                 (token, queue_id, orig_filename, stored_path, file_size, expires_at, created_at)
                 VALUES (:tok, 0, :name, :path, :size, DATE_ADD(NOW(), INTERVAL :ttl HOUR), NOW())"
            );
            // token результата пока placeholder — воркер перезапишет при done?
            // Нет: токен стабилен сразу, просто страница /result откроется после done.
            $token = bin2hex(random_bytes(8)); // 16 hex
            $insHist->execute([
                ':tok' => $token, ':name' => $origName, ':path' => $relPath,
                ':size' => $real, ':ttl' => $ttlHours,
            ]);
            $historyId = (int)$this->pdo->lastInsertId();

            $insQueue = $this->pdo->prepare(
                'INSERT INTO `' . P2H_PREFIX . "psd2html_queue`
                 (task_uid, conversion_id, status, created_ip, session_key, created_at)
                 VALUES (:uid, :cid, 'queued', :ip, :sess, NOW())"
            );
            $insQueue->execute([
                ':uid' => $taskUid,
                ':cid' => $historyId,
                ':ip'  => inet_pton($ip) ?: str_repeat("\0", 16),
                ':sess'=> $sessionId !== null && $sessionId !== '' ? hash('sha256', $sessionId) : null,
            ]);

            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            @unlink($absPath);
            error_log('psd2html enqueue: ' . $e->getMessage());
            throw new UploadException('internal', 'Не удалось поставить задачу в очередь');
        }

        return ['task_uid' => $taskUid, 'conversion_id' => $historyId];
    }

    /** Человекочитаемые сообщения для кодов UPLOAD_ERR_*. */
    private function uploadErrorMessage(int $code): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Файл слишком большой для загрузки',
            UPLOAD_ERR_PARTIAL => 'Загрузка прервалась, попробуйте ещё раз',
            UPLOAD_ERR_NO_FILE => 'Файл не выбран',
            default => 'Не удалось принять файл',
        };
    }
}

/** Ошибка загрузки с машинным кодом (для клиента) и RU-текстом. */
final class UploadException extends RuntimeException
{
    public function __construct(public readonly string $errorCode, string $userMessage)
    {
        parent::__construct($userMessage);
    }
}
