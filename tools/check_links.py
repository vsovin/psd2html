"""Проверка целостности: все локальные ссылки/ресурсы существуют, нет внешних зависимостей."""
import os, re, sys

root = '/workspace'
errors = []
external = []
for dirpath, dirs, files in os.walk(root):
    dirs[:] = [d for d in dirs if d not in ('node_modules', '.git', 'tools')]
    for fn in files:
        if fn.endswith('.js'): continue  # ссылки в JS — шаблонные строки, не проверяем
        if not fn.endswith(('.html', '.css')): continue
        fp = os.path.join(dirpath, fn)
        html = open(fp, encoding='utf-8').read()
        refs = re.findall(r'(?:href|src)="([^"]+)"', html)
        for r in refs:
            if r.startswith(('http://', 'https://', '//')):
                external.append((fp, r))
            elif r.startswith(('mailto:', 'tel:', '#', 'data:')):
                continue
            else:
                clean = r.split('?')[0].split('#')[0]
                if not clean: continue
                target = os.path.normpath(os.path.join(dirpath, clean))
                if os.path.isdir(target): target = os.path.join(target, 'index.html')
                if not os.path.isfile(target):
                    errors.append(f'{fp}: битая ссылка -> {r}')
print('EXTERNAL DEPS:', external or 'нет')
if errors:
    print('BROKEN LINKS:'); [print(' ', e) for e in errors]; sys.exit(1)
print('ALL LOCAL LINKS OK')
