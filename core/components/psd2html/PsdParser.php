<?php
/**
 * PSD2HTML — лёгкий парсер структуры PSD (Задача 3, вариант «b»).
 *
 * Читает ТОЛЬКО метаданные файла (без растрирования):
 *  - заголовок документа (ширина/высота/битность/режим);
 *  - Resource Block «LayerAndMask» (8BIM #1039) — дерево слоёв:
 *    имя, тип (text/image/group), видимость, bounding box, вложенность;
 *  - для текстовых слоёв — блок «TySh»: строка текста, family, размер кегля.
 *
 * Формат PSD big-endian; спецификация Adobe Photoshop File Formats (App. A).
 * Рендер пикселей делает Imagick (Converter.php) — здесь только структура.
 *
 * Ограничения MVP (осознанные): не поддерживаем smart objects как отдельные
 * ноды (берём их bbox+имя), эффекты/маски игнорируем, PSD Large (PSB) — да.
 */

declare(strict_types=1);

final class PsdParser
{
    /** @var array<string,mixed> Дерево слоёв */
    public array $layers = [];
    public int $width = 0;
    public int $height = 0;
    public int $channels = 3;
    public int $bitDepth = 8;
    public int $totalLayers = 0;

    private string $data = '';
    private int $pos = 0;

    /**
     * @throws PsdParseException если файл не является корректным PSD
     */
    public function parse(string $filePath): void
    {
        // Защита: читаем целиком только до лимита очереди (файлы ≤ maxFileSizeMb)
        $this->data = (string)file_get_contents($filePath);
        $len = strlen($this->data);
        if ($len < 26 || substr($this->data, 0, 4) !== '8BPS') {
            throw new PsdParseException('not_psd');
        }

        $this->pos = 0;
        $sig   = $this->read(4);
        $ver   = $this->u16();               // 1 = PSD, 2 = PSB
        $this->read(6);                        // reserved
        $this->channels = $this->u16();
        $this->height   = $this->u32($ver === 2);
        $this->width    = $this->u32($ver === 2);
        $this->bitDepth = $this->u32($ver === 2);
        $this->colorMode = $this->u16();      // 3 = RGB
        if ($sig !== '8BPS' || !in_array($ver, [1, 2], true)) {
            throw new PsdParseException('not_psd');
        }

        // --- Image Resources: ищем 8BIM type 0x1039 (Layer and Mask Info) ----------
        $resLen = $this->u32($ver === 2);
        $resEnd = $this->pos + $resLen;
        while ($this->pos + 12 <= $resEnd) {
            $sig = $this->read(4);
            if ($sig !== '8BIM') { break; }
            $type = $this->u16();
            $nameLen = $this->u8();
            $pad = ($nameLen % 2 === 0) ? 1 : 0;   // имена выровнены по 2 байта
            $this->pos += $nameLen + $pad;
            $sz = $this->u32($ver === 2);
            if ($type === 0x1039) {
                $start = $this->pos;              // блок слоёв начнём после выхода из цикла
                $this->parseLayerBlock($start, $sz, $ver);
                $this->pos = $start + $sz;
            } else {
                $this->pos += $sz;                 // пропускаем прочие ресурсы
            }
        }
        $this->pos = $resEnd;                     // синхронизация на всякий случай
    }

    // ============================ Блок слоёв =====================================
    // Структура (Adobe spec): Layer info length | count(int16/int32) | layer records...
    private function parseLayerBlock(int $blockStart, int $blockSize, int $ver): void
    {
        $p = $blockStart;
        $layerInfoLen = $this->peekU32($p);
        $p += 4;
        if ($layerInfoLen <= 0) { return; }
        $infoEnd = $p + $layerInfoLen - 4;        // длина включает сам счётчик? нет: поле длины идёт перед данными
        // В спецификации: 4-байтная длина блока layer info, затем данные длиной (len) байт,
        // но len считается от позиции ПОСЛЕ поля длины. Поэтому:
        $infoDataEnd = $p + $layerInfoLen;

        $count = $this->peekI16($p);
        $p += 2;
        if ($count < 0) {                          // отрицательный = первый слой — группа-фон, пропускаем флаг
            $count = -$count;
        }
        if ($count > 5000) {                       // защита от битых файлов
            throw new PsdParseException('corrupted');
        }
        $this->totalLayers = $count;

        $stack = [];                                // стек открытых групп (для вложенности)
        $order = [];                                // плоский список для генератора (z снизу вверх)
        $idx = 0;

        for ($i = 0; $i < $count; $i++) {
            [$rec, $p] = $this->readLayerRecord($p, $ver, $infoDataEnd);
            if ($rec === null) { break; }           // ушли за границу — конец данных

            // --- Обработка флагов группы -------------------------------------------
            if ($rec['type'] === 'group_end') {
                array_pop($stack);
                continue;
            }
            if ($rec['type'] === 'group_open') {
                // divider-блок «Section Divider Setting» определяет тип группы:
                // closed ('origType'==='opSL') = обычная группа, open = фрейм/other
                $rec['children'] = [];
                $rec['depth'] = count($stack);
                $stack[] = $rec;
                $order[] = &$rec;
                continue;
            }

            $rec['depth'] = count($stack);
            $rec['index'] = $idx++;
            $order[] = &$rec;
            if ($stack) {
                $stack[count($stack) - 1]['children'][] = &$rec;
            }
            unset($rec);
        }

        // Собираем корневой список (depth==0) — порядок z: PSD хранит снизу вверх,
        // разворачиваем, чтобы DOM шёл сверху вниз (первый элемент = верхний слой).
        $roots = [];
        foreach ($order as $node) {
            if ((int)$node['depth'] === 0 && in_array($node['type'], ['image', 'text'], true)) {
                $roots[] = $node;
            } elseif ((int)$node['depth'] === 0 && $node['type'] === 'group_open') {
                $roots[] = $node;
            }
        }
        $this->layers = array_reverse($roots);
    }

    /** Одна запись слоя. Возвращает [нода|null, новая позиция]. */
    private function readLayerRecord(int $p, int $ver, int $end): array
    {
        if ($p + 16 > $end) { return [null, $p]; }
        $top    = $this->peekU32($p, $ver === 2); $p += ($ver === 2 ? 4 : 4);
        $left   = $this->peekU32($p, $ver === 2); $p += 4;
        $bottom = $this->peekU32($p, $ver === 2); $p += 4;
        $right  = $this->peekU32($p, $ver === 2); $p += 4;
        $channelsCount = $this->peekU16($p); $p += 2;
        // channel info: count * (2 байта id + 4 байта длина данных канала)
        $chanBytes = 0;
        for ($c = 0; $c < $channelsCount; $c++) {
            if ($p + 6 > $end) { return [null, $p]; }
            $cid = $this->peekI16($p); $p += 2;
            $clen = $this->peekU32($p); $p += 4;
            if ($cid >= 0) { $chanBytes += $clen; } // считаем реальные каналы (id>=0)
        }
        $blend = $this->read(4);                     // '8BIM'
        $opacity = $this->peekU8($p); $p += 1;
        $p += 3;                                     // clipping, flags
        $flags = $this->peekU8($p - 4 + 2);          // байт flags: bit1 (0x02) = hidden
        $visible = (($flags & 0x02) === 0) && $opacity > 0;
        $p += 1;                                     // filler
        $extraLen = $this->peekU32($p); $p += 4;
        $extraEnd = $p + $extraLen;
        if ($extraEnd > $end) { return [null, $p]; }

        $layerName = '';
        $tyshText = null; $fontFamily = null; $fontSize = null;
        $blendKey = null;
        $q = $p;
        $nameFieldLen = $this->peekU32($q); $q += 4; // PascalString с padding до 4
        $nameBytes = min(255, max(0, $nameFieldLen - 4));
        $raw = $this->slice($q, $nameBytes);
        $layerName = $this->decodePascalName($raw);
        $q += $nameFieldLen + ((4 - ($nameFieldLen % 4)) % 4);

        // License / Blending clips… идём к extra data по смещению конца имени
        // Layer Extra: цепочка 8BIM ключей до конца extra.
        $e = $q;
        while ($e + 12 <= $extraEnd) {
            $sig = $this->slice($e, 4); $e += 4;
            if ($sig !== '8BIM' && $sig !== '8B64') { break; }
            $key = $this->slice($e, 4); $e += 4;
            $sz = $this->peekU32($e); $e += 4;
            if ($key === 'luni') {                   // Unicode layer name (предпочтительнее Pascal)
                $un = $this->slice($e, $sz);
                $dec = @unpack('Nlen', substr($un, 0, 4));
                if ($dec) {
                    $layerName = mb_convert_encoding(substr($un, 4, min($dec['len'] * 2, $sz - 4)), 'UTF-8', 'UTF-16BE');
                }
            } elseif ($key === 'TySh') {             // Text data
                [$tyshText, $fontFamily, $fontSize] = $this->parseTySh($this->slice($e, $sz));
            } elseif ($key === 'layr' || $key === 'lsct') {
                if ($key === 'lsct' && $sz >= 12) {  // Section Divider Setting → тип группы
                    $subtype = $this->peekU32($e + 8);
                    $blkType = $this->slice($e + 8, 4); // 'type' field is at offset 8? spec: block signature/type
                    $blendKey = trim($this->slice($e + 8, 4));
                }
            }
            $e += $sz + ((4 - ($sz % 4)) % 4);
        }

        $p = $extraEnd;                              // переходим к pixel data (нам не нужен)
        $pixelSkip = $chanBytes;                      // данные каналов идут сразу за записью
        $p += $pixelSkip;

        // --- Классификация ноды -----------------------------------------------------
        if ($layerName === '</Layer group>' || $left === 0 && $top === 0 && $right === 0 && $bottom === 0 && $tyshText === null && $channelsCount === 0) {
            return [['type' => 'group_end'], $p];
        }
        $isGroup = ($channelsCount === 0 && $right <= $left && $bottom <= $top)
            || ($blendKey === 'opSL' && $channelsCount === 0)
            || ($channelsCount === 0);
        $type = $tyshText !== null ? 'text' : ($isGroup ? 'group_open' : 'image');

        $node = [
            'type'    => $type,
            'name'    => $layerName !== '' ? $layerName : ('layer-' . $idx),
            'visible' => $visible,
            'bbox'    => ['top' => $top, 'left' => $left, 'bottom' => $bottom, 'right' => $right],
            'opacity' => $opacity,
            'text'    => $tyshText,
            'font'    => $fontFamily,
            'size'    => $fontSize,
        ];
        return [$node, $p];
    }

    /** Разбор TySh: возвращает [текст, семейство шрифта, кегль(pt)]. */
    private function parseTySh(string $blob): array
    {
        // Версия(4) + transform(6*8) + text version(4) + text length(4) + text (UTF-16BE) ...
        if (strlen($blob) < 26) { return [null, null, null]; }
        $tvVersion = unpack('N', substr($blob, 18, 4))[1] ?? 1;
        $off = 22;
        if ($tvVersion === 1) {
            $txtLen = unpack('N', substr($blob, $off, 4))[1] ?? 0; $off += 4;
            $text = mb_convert_encoding(substr($blob, $off, $txtLen * 2), 'UTF-8', 'UTF-16BE');
            $off += $txtLen * 2;
        } else {
            $warped = unpack('N', substr($blob, $off, 4))[1] ?? 0; $off += 4;
            if ($warped === 0) { return [null, null, null]; }
            $txtLen = unpack('N', substr($blob, $off, 4))[1] ?? 0; $off += 4;
            $text = mb_convert_encoding(substr($blob, $off, $txtLen * 2), 'UTF-8', 'UTF-16BE');
            $off += $txtLen * 2;
        }
        // style version(4) + default style(4)+... — вытащим family/size из run stream
        $family = null; $size = null;
        if (preg_match('/Font\/([A-Za-z0-9\-+,\. ]+)Hi\/Base/', '', $text)) {} // no-op guard
        // Простой эвристика: в TySh после текста идёт XML-подобный flow; берём First Size/Font через regex
        if (preg_match('/Font"?\s+\[([^\]]+)\]/', $blob, $m)) { $family = $m[1]; }
        if (preg_match('/Size"?\s+([\d\.]+)/', $blob, $m)) { $size = (float)$m[1]; }
        $text = str_replace(["\r", "\n"], [' ', '<br>'], $text);
        return [$text !== '' ? $text : null, $family, $size];
    }

    /** Pascal-имя слоя (макс 255) → UTF-8 (Latin-1 fallback). */
    private function decodePascalName(string $raw): string
    {
        if ($raw === '') { return ''; }
        $len = ord($raw[0]);
        $name = substr($raw, 1, $len);
        return mb_convert_encoding($name, 'UTF-8', 'Windows-1252');
    }

    // ============================ Чтение big-endian ===============================
    private function slice(int $at, int $n): string
    {
        if ($at < 0 || $n < 0 || $at + $n > strlen($this->data)) { return ''; }
        return substr($this->data, $at, $n);
    }
    private function peekU8(int $p): int   { return ord($this->data[$p] ?? "\0"); }
    private function peekU16(int $p): int  { return unpack('n', $this->slice($p, 2))[1] ?? 0; }
    private function peekI16(int $p): int  { $v = $this->peekU16($p); return $v > 32767 ? $v - 65536 : $v; }
    private function peekU32(int $p): int  { return unpack('N', $this->slice($p, 4))[1] ?? 0; }

    // Стрим-варианты для parse() (используют $this->pos)
    private function read(int $n): string { $s = substr($this->data, $this->pos, $n); $this->pos += $n; return $s; }
    private function u8(): int  { return ord($this->read(1)); }
    private function u16(): int { return unpack('n', $this->read(2))[1] ?? 0; }
    private function u32(bool $psb = false): int { return unpack('N', $this->read(4))[1] ?? 0; }
}

final class PsdParseException extends RuntimeException
{
    public function __construct(public readonly string $code)
    {
        parent::__construct('PSD parse error: ' . $code);
    }
}
