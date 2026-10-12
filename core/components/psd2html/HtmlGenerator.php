<?php
/**
 * PSD2HTML — генератор семантического HTML5 + CSS из дерева слоёв (Задача 3).
 *
 * Правила маппинга:
 *  - группа слоёв            -> <section class="..."> (контейнер по bbox);
 *  - текстовый слой          -> <p>/<h1..h6> (по кеглю) c экранированием;
 *  - растровый слой          -> <img src="assets/img/..."> (абсолютное позиционирование);
 *  - скрытые слои            -> пропускаются;
 *  - имена слоёв             -> CSS-классы (slugify, транслит RU→EN);
 *  - «умные» имена (header/footer/nav/btn...) -> семантические теги <header>/<footer>/<nav>.
 *
 * Результат: index.html + style.css + assets/img/*.png|webp.
 * Всё выводимое экранируется; классы — только [a-z0-9_-].
 */

declare(strict_types=1);

final class HtmlGenerator
{
    /** @var array<int,array> Плоский список нод в порядке z (верхние первыми) */
    private array $nodes = [];
    private int $usedCount = 0;

    public function __construct(
        private array $tree,
        private int $canvasW,
        private int $canvasH,
        /** @var array<int,string> index слоя => относительный путь image в ZIP */
        private array $sliceMap = [],
    ) {}

    /** Собирает дерево → плоский список с сохранением вложенности групп. */
    private function flatten(array $nodes, int $depth = 0): void
    {
        foreach ($nodes as $n) {
            if (empty($n['visible'])) { continue; }   // скрытые слои пропускаем
            $n['depth'] = $depth;
            $this->nodes[] = $n;
            if (($n['type'] ?? '') === 'group_open' && !empty($n['children'])) {
                $this->flatten($n['children'], $depth + 1);
            }
        }
    }

    /** slug имени слоя → безопасный CSS-класс (транслит RU). */
    public static function slug(string $name): string
    {
        $tr = [
            'а'=>'a','б'=>'b','в'=>'v','г'=>'g','д'=>'d','е'=>'e','ё'=>'e','ж'=>'zh','з'=>'z',
            'и'=>'i','й'=>'y','к'=>'k','л'=>'l','м'=>'m','н'=>'n','о'=>'o','п'=>'p','р'=>'r',
            'с'=>'s','т'=>'t','у'=>'u','ф'=>'f','х'=>'h','ц'=>'ts','ч'=>'ch','ш'=>'sh',
            'щ'=>'sch','ъ'=>'','ы'=>'y','ь'=>'','э'=>'e','ю'=>'yu','я'=>'ya',
        ];
        $s = mb_strtolower(trim($name));
        $s = strtr($s, $tr);
        $s = preg_replace('/[^a-z0-9]+/', '-', $s) ?? '';
        $s = trim($s, '-');
        if ($s === '' || preg_match('/^\d/', $s)) { $s = 'layer-' . $s; }
        return substr($s, 0, 48);
    }

    /** Семантический тег по имени слоя/подсказкам. */
    private function semanticTag(string $nameLower): string
    {
        $map = [
            'header' => 'header', 'шапка' => 'header', 'хедер' => 'header',
            'footer' => 'footer', 'подвал' => 'footer', 'футер' => 'footer',
            'nav' => 'nav', 'меню' => 'nav', 'menu' => 'nav',
            'main' => 'main', 'hero' => 'section',
        ];
        foreach ($map as $needle => $tag) {
            if (str_contains($nameLower, $needle)) { return $tag; }
        }
        return 'div';
    }

    /** Тег заголовка по кеглю текста. */
    private function headingTag(?float $size): string
    {
        if ($size === null) { return 'p'; }
        return match (true) {
            $size >= 48 => 'h1',
            $size >= 32 => 'h2',
            $size >= 24 => 'h3',
            $size >= 18 => 'h4',
            default => 'p',
        };
    }

    /** @return array{html:string,css:string,layers_used:int} */
    public function build(): array
    {
        $this->flatten($this->tree);
        $html = [];
        $cssRules = [];
        $openStack = []; // depth => id элемента для закрытия section

        foreach ($this->nodes as $n) {
            $bbox = $n['bbox'];
            $w = max(0, $bbox['right'] - $bbox['left']);
            $h = max(0, $bbox['bottom'] - $bbox['top']);
            $cls = self::slug((string)$n['name']);
            $id = 'el-' . (++$this->usedCount);
            $styleBase = sprintf(
                'position:absolute;left:%dpx;top:%dpx;width:%dpx;height:%dpx;',
                $bbox['left'], $bbox['top'], $w, $h
            );
            $cssRules[] = "#{$id}{ {$styleBase} }\n";

            switch ($n['type']) {
                case 'group_open':
                    $tag = $this->semanticTag(mb_strtolower((string)$n['name']));
                    $html[] = "<{$tag} id=\"{$id}\" class=\"grp grp--{$cls}\">";
                    $openStack[] = ['depth' => (int)$n['depth'], 'tag' => $tag];
                    break;

                case 'text':
                    $tag = $this->headingTag($n['size'] ?? null);
                    $text = htmlspecialchars((string)$n['text'], ENT_QUOTES, 'UTF-8');
                    // TySh уже заменил \n на <br> — разрешаем только их
                    $text = str_replace('&lt;br&gt;', '<br>', $text);
                    $font = $n['font'] ? $this->cssFontFamily((string)$n['font']) : 'inherit';
                    $size = $n['size'] ? sprintf('%.1fpx', ((float)$n['size']) * 4 / 3) : '16px'; // pt→px
                    $cssRules[] = "#{$id}{ font-family:{$font};font-size:{$size};line-height:1.35;color:#222;white-space:normal;overflow:hidden; }\n";
                    $html[] = "<{$tag} id=\"{$id}\" class=\"txt txt--{$cls}\">{$text}</{$tag}>";
                    break;

                case 'image':
                default:
                    $src = $this->sliceMap[$n['index']] ?? '';
                    if ($src === '') { break; }         // нет растра — пропускаем ноду
                    $alt = htmlspecialchars((string)$n['name'], ENT_QUOTES, 'UTF-8');
                    if ($this->looksLikeButton((string)$n['name'])) {
                        $html[] = "<a id=\"{$id}\" class=\"btn btn--{$cls}\" href=\"#\" aria-label=\"{$alt}\"><img src=\"{$src}\" alt=\"{$alt}\" width=\"{$w}\" height=\"{$h}\"></a>";
                    } else {
                        $html[] = "<img id=\"{$id}\" class=\"img img--{$cls}\" src=\"{$src}\" alt=\"{$alt}\" width=\"{$w}\" height=\"{$h}\" loading=\"lazy\">";
                    }
                    break;
            }
        }

        // Закрываем незакрытые группы (обратный порядок)
        while ($openStack) {
            $g = array_pop($openStack);
            $html[] = "</{$g['tag']}>";
        }

        $body = implode("\n", $html);
        $doc = <<<HTML
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{$this->titleFromName()}</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<main class="page" id="page">
{$body}
</main>
</body>
</html>
HTML;

        $cssHead = sprintf(
            "/* Сгенерировано PSD2HTML автоматически. Макет фиксированный: %d×%d px.\n   Замените absolute-позиционирование на flex/grid при ручной доработке. */\n"
            . "*{box-sizing:border-box;margin:0;padding:0}\n"
            . "html,body{background:#fff;color:#222;font-family:system-ui,-apple-system,\"Segoe UI\",Roboto,Arial,sans-serif}\n"
            . ".page{position:relative;width:%dpx;height:%dpx;margin:0 auto;overflow:hidden}\n"
            . "@media (max-width:%dpx){.page{transform-origin:top left;}}\n",
            $this->canvasW, $this->canvasH, $this->canvasW, $this->canvasH, $this->canvasW
        );

        return [
            'html' => $doc,
            'css' => $cssHead . implode('', $cssRules),
            'layers_used' => $this->usedCount,
        ];
    }

    /** Название страницы из имени файла/первого group-слоя. */
    private function titleFromName(): string
    {
        foreach ($this->tree as $n) {
            if (($n['type'] ?? '') === 'group_open') {
                return htmlspecialchars(self::slug((string)$n['name']), ENT_QUOTES);
            }
        }
        return 'psd-layout';
    }

    /** Шрифт PSD → CSS stack с кириллическим fallback. */
    private function cssFontFamily(string $psdFont): string
    {
        $base = preg_replace('/(Bold|Italic|Regular|Light|Medium|Black|Thin).*$/iu', '', $psdFont) ?? $psdFont;
        $base = str_replace('"', '', trim($base));
        return '"' . $base . '", "PT Sans", Arial, sans-serif';
    }

    /** Эвристика: слой похож на кнопку. */
    private function looksLikeButton(string $name): bool
    {
        $n = mb_strtolower($name);
        return (bool)preg_match('/(btn|button|кнопка|cta)/u', $n);
    }
}
