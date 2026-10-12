<!-- templates/base.tpl — базовый каркас. Остальные шаблоны наследуют его разметку
     (в MODX задаётся через static-файл + чанки; здесь полный каркас). -->
<!DOCTYPE html>
<html lang="ru">
<head>
  [[!meta]]
</head>
<body class="d-flex flex-column min-vh-100">
[[!header]]

<main id="content" class="flex-grow-1">
  [[+content]]
</main>

[[!footer]]
</body>
</html>
