<?php
/**
 * PSD2HTML — конвертер: PSD → (структура + растры) → HTML5/CSS → ZIP (Задача 3).
 *
 * Конвейер одной задачи очереди:
 *  1. PsdParser читает дерево слоёв (имена/видимость/bbox/тексты);
 *  2. Imagick рендерит композит в preview.png и каждый видимый растровый слой — slice;
 *     (если у слоя есть текстовые данные, растр не нужен — текст пойдёт в DOM);
 *  3. HtmlGenerator собирает index.html + style.css;
 *  4. ZipArchive pack'ует результат в results/<token>/result.zip.
 *
 * Таймауты и лимиты памяти задаёт воркер (worker.php): на задачу отводится
 * PROCESS_TIMEOUT секунд, Imagick ограничивается area/内存.
 */

declare(strict_types=1);

require_once __DIR__ . '/PsdParser.php';
require_once __DIR__ . '/HtmlGenerator.php';

final class Converter
{
    /** Секунд на одну задачу (сверх — считаем зависшей). */
    public const PROCESS_TIMEOUT = 180;

    public function __construct(
        private string $storageRoot,   // /var/data/psd2html
        private int $maxSlicePx = 6000,// сторона largest slice (защита от PSD-бомб)
    ) {}

    /**
     * Выполняет конвертацию. Бросает ConversionException с кодом для error_code.
     *
     * @param array $row строка из history: token, stored_path, orig_filename...
     * @return array{zip_rel:string,preview_rel:string,layers_total:int,layers_used:int}
     */
    public function convert(array $row): array
    {
        $srcAbs = $this->storageRoot . '/' . $row['stored_path'];
        if (!is_file($srcAbs)) {
            throw new ConversionException('corrupted', 'Исходный файл утерян');
        }

        // --- 1. Структура -------------------------------------------------------------
        $parser = new PsdParser();
        try {
            $parser->parse($srcAbs);
        } catch (PsdParseException $e) {
            throw new ConversionException($e->code, $e->getMessage());
        }

        // --- 2. Растровый проход Imagick ----------------------------------------------
        $outDirRel = 'results/' . $row['token'];
        $outDirAbs = $this->storageRoot . '/' . $outDirRel;
        $imgDirAbs = $outDirAbs . '/assets/img';
        if (!is_dir($imgDirAbs) && !mkdir($imgDirAbs, 0750, true) && !is_dir($imgDirAbs)) {
            throw new ConversionException('internal', 'Не удалось создать папку результата');
        }

        $sliceMap = [];       // layer index => путь внутри ZIP (assets/img/...)
        $layersUsed = 0;
        $flat = [];
        $collectVisible = function (array $nodes) use (&$collectVisible, &$flat): void {
            foreach ($nodes as $n) {
                if (!empty($n['visible'])) { $flat[] = $n; }
                if (($n['type'] ?? '') === 'group_open') { $collectVisible($n['children'] ?? []); }
            }
        };
        $collectVisible($parser->layers);

        try {
            $im = new Imagick();
            // Лимиты: площадь одного изображения и память процесса
            if (method_exists($im, 'setMemoryLimit')) { $im->setMemoryLimit(256 * 1024 * 1024); }
            Imagick::clearCache();
            $im->pingImage($srcAbs);                    // дешёвая проверка читаемости
            if ($im->getImageWidth() > $this->maxSlicePx || $im->getImageHeight() > $this->maxSlicePx) {
                throw new ConversionException('too_big', 'Слишком большой холст для автoконвертации');
            }
            $im->readImage($srcAbs);

            // 2a. Композит → preview.png (для предпросмотра на сайте)
            $preview = clone $im;
            $preview->flattenImages();
            $preview->setImageFormat('png');
            // Превью уменьшаем до 1200px по ширине — экономия трафика
            if ($preview->getImageWidth() > 1200) {
                $preview->thumbnailImage(1200, 0, true);
            }
            $previewPath = $outDirAbs . '/preview.png';
            $preview->writeImage($previewPath);
            $preview->destroy();

            // 2b. Слои → срезы. Imagick отдаёт слои как отдельные страницы (PSD: page == layer)
            $layerPages = [];
            foreach ($im as $i => $page) { /* IndexedImage iteration: pages = layers снизу вверх */
                $layerPages[$i] = $page;
            }
            // Сопоставление страница↔слой делаем по имени страницы (Imagick кладёт имя слоя в page property)
            $byNameLower = [];
            foreach ($layerPages as $i => $pg) {
                $psdName = trim((string)$pg->getImageProperty('psd:layer-name'));
                $byNameLower[mb_strtolower($psdName)] = $i;
            }

            foreach ($flat as $n) {
                if (($n['type'] ?? '') !== 'image') { continue; }   // текст генерируем DOM-ом
                $bbox = $n['bbox'];
                $w = $bbox['right'] - $bbox['left'];
                $h = $bbox['bottom'] - $bbox['top'];
                if ($w <= 0 || $h <= 0) { continue; }

                $idx = $byNameLower[mb_strtolower((string)$n['name'])] ?? null;
                if ($idx === null) { continue; }                     // слой не найден в растрах — пропускаем
                $cut = clone $layerPages[$idx];
                $cut->cropImage($w, $h, $bbox['left'], $bbox['top']);
                // WebP если поддерживается (меньше вес), иначе PNG
                $fmt = in_array('WEBP', Imagick::queryFormats(), true) ? 'webp' : 'png';
                $cut->setImageFormat($fmt);
                if ($fmt === 'webp') { $cut->setImageCompressionQuality(82); }
                $fname = sprintf('slice-%03d-%s.%s', (int)$n['index'], HtmlGenerator::slug((string)$n['name']), $fmt);
                $cut->writeImage($imgDirAbs . '/' . $fname);
                $sliceMap[(int)$n['index']] = 'assets/img/' . $fname;
                $cut->destroy();
                $layersUsed++;
            }
            $im->destroy();
            Imagick::clearCache();
        } catch (ImagickException $e) {
            error_log('psd2html imagick: ' . $e->getMessage());
            throw new ConversionException('internal', 'Ошибка растрирования PSD (' . $e->getMessage() . ')');
        }

        // --- 3. HTML/CSS ----------------------------------------------------------------
        $gen = new HtmlGenerator($parser->layers, $parser->width, $parser->height, $sliceMap);
        $built = $gen->build();
        file_put_contents($outDirAbs . '/index.html', $built['html']);
        file_put_contents($outDirAbs . '/style.css', $built['css']);

        // --- 4. ZIP ----------------------------------------------------------------------
        $zipRel = $outDirRel . '/result.zip';
        $zip = new ZipArchive();
        if ($zip->open($this->storageRoot . '/' . $zipRel, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new ConversionException('internal', 'Не удалось собрать архив');
        }
        $zip->addFile($outDirAbs . '/index.html', 'index.html');
        $zip->addFile($outDirAbs . '/style.css', 'style.css');
        foreach (glob($imgDirAbs . '/*') ?: [] as $f) {
            $zip->addFile($f, 'assets/img/' . basename($f));
        }
        $zip->close();

        return [
            'zip_rel' => $zipRel,
            'preview_rel' => $outDirRel . '/preview.png',
            'layers_total' => count($flat),
            'layers_used' => $layersUsed,
        ];
    }
}

final class ConversionException extends RuntimeException
{
    public function __construct(public readonly string $code, string $msg = '')
    {
        parent::__construct($msg !== '' ? $msg : $code);
    }
}
