#!/usr/bin/env python3
"""Static validation of the VirshkeTech WP theme: PHP syntax, block.json schema, template parts."""
import json, os, re, sys

ROOT = "wp-theme/virshketech"
errors, warnings = [], []

# 1. PHP files: balanced braces/parens/brackets outside strings & comments (heuristic lint)
def strip_php(src):
    out, i, n = [], 0, len(src)
    while i < n:
        c = src[i]
        if c == "'":
            i += 1
            while i < n:
                if src[i] == "\\": i += 2; continue
                if src[i] == "'": i += 1; break
                i += 1
        elif c == '"':
            i += 1
            while i < n:
                if src[i] == "\\": i += 2; continue
                if src[i] == '"': i += 1; break
                i += 1
        elif src.startswith("//", i) or src.startswith("#", i):
            j = src.find("\n", i); i = len(src) if j < 0 else j
        elif src.startswith("/*", i):
            j = src.find("*/", i + 2); i = len(src) if j < 0 else j + 2
        else:
            out.append(c); i += 1
    return "".join(out)

php_files = []
for dirpath, _, files in os.walk(ROOT):
    for f in files:
        if f.endswith(".php"): php_files.append(os.path.join(dirpath, f))

for pf in php_files:
    src = open(pf, encoding="utf-8").read()
    code = strip_php(src)
    for o, cl in [("{", "}"), ("(", ")"), ("[", "]")]:
        if code.count(o) != code.count(cl):
            errors.append(f"{pf}: unbalanced {o}{cl} ({code.count(o)} vs {code.count(cl)})")
    if "<?php" not in src:
        errors.append(f"{pf}: missing <?php opening tag")
    # every function called from hooks should exist somewhere
print(f"PHP files checked: {len(php_files)}")

# 2. functions.php includes exist
fn = open(os.path.join(ROOT, "functions.php"), encoding="utf-8").read()
for inc in re.findall(r"(?:require|include)(?:_once)?\s*\(?\s*[^'\"]*['\"]([^'\"]+\.php)", fn):
    path = os.path.join(ROOT, inc.replace("%s/", "").replace("get_template_directory().'/", "").strip("/"))
    if not any(inc.split("/")[-1] == os.path.basename(p) for p in php_files):
        errors.append(f"functions.php requires missing file: {inc}")

# 3. block.json validity
block_dirs = []
for d in sorted(os.listdir(os.path.join(ROOT, "blocks"))):
    bd = os.path.join(ROOT, "blocks", d)
    if os.path.isdir(bd): block_dirs.append((d, bd))
names = set()
for d, bd in block_dirs:
    bj_path = os.path.join(bd, "block.json")
    if not os.path.exists(bj_path):
        errors.append(f"blocks/{d}: no block.json"); continue
    try:
        bj = json.load(open(bj_path, encoding="utf-8"))
    except Exception as e:
        errors.append(f"blocks/{d}/block.json: invalid JSON: {e}"); continue
    for key in ("apiVersion", "name", "title", "editorScript"):
        if key not in bj: errors.append(f"blocks/{d}/block.json: missing '{key}'")
    if "name" in bj:
        if bj["name"] in names: errors.append(f"duplicate block name {bj['name']}")
        names.add(bj["name"])
        if not bj["name"].startswith("virshketech/"): errors.append(f"blocks/{d}: namespace should be virshketech/")
    # editorScript file exists
    es = bj.get("editorScript", "")
    if isinstance(es, str) and es and not es.startswith("file:"):
        errors.append(f"blocks/{d}: editorScript should be file:... got {es}")
    if isinstance(es, str) and es.startswith("file:"):
        if not os.path.exists(os.path.join(bd, es[5:].lstrip("./"))):
            errors.append(f"blocks/{d}: editorScript target missing: {es}")
    # attributes have type
    for a, meta in bj.get("attributes", {}).items():
        if "type" not in meta and "default" not in meta:
            warnings.append(f"blocks/{d}: attribute '{a}' has neither type nor default")
print(f"Blocks checked: {[d for d,_ in block_dirs]}")

# 4. JS syntax check via node
import subprocess
for d, bd in block_dirs:
    js = os.path.join(bd, "index.js")
    if os.path.exists(js):
        r = subprocess.run(["node", "--check", js], capture_output=True, text=True)
        if r.returncode != 0: errors.append(f"{js}: syntax error\n{r.stderr[:400]}")
js_main = os.path.join(ROOT, "assets", "script.js")
r = subprocess.run(["node", "--check", js_main], capture_output=True, text=True)
if r.returncode != 0: errors.append(f"{js_main}: syntax error\n{r.stderr[:400]}")

# 5. render callbacks referenced by blocks exist in PHP
all_php = "".join(open(p, encoding="utf-8").read() for p in php_files)
for m in re.findall(r"render_callback['\"]\s*=>\s*['\"]?([a-zA-Z_][a-zA-Z0-9_]*)", all_php):
    if not re.search(r"function\s+" + re.escape(m) + r"\b", all_php):
        errors.append(f"render_callback function missing: {m}")
for m in re.findall(r"register_block_type\s*\(\s*[^,]+,\s*array\([^)]*['\"]render_callback['\"]\s*=>\s*['\"]([a-zA-Z_]+)", all_php):
    if not re.search(r"function\s+" + re.escape(m) + r"\b", all_php):
        errors.append(f"registered render_callback missing: {m}")

# 6. templates/parts dirs
tpl = os.path.join(ROOT, "templates"); prt = os.path.join(ROOT, "parts")
need_tpl = ["index.html", "front-page.html", "page.html", "single-audience_page.html"]
for t in need_tpl:
    if not os.path.exists(os.path.join(tpl, t)): errors.append(f"templates/{t} missing")
for p in ["header.html", "footer.html"]:
    if not os.path.exists(os.path.join(prt, p)): errors.append(f"parts/{p} missing")

# 7. theme.json valid
try:
    tj = json.load(open(os.path.join(ROOT, "theme.json"), encoding="utf-8"))
    if "settings" not in tj or "styles" not in tj: errors.append("theme.json: settings/styles missing")
except Exception as e:
    errors.append(f"theme.json invalid: {e}")

# 8. style.css header
sc = open(os.path.join(ROOT, "style.css"), encoding="utf-8").read()
for field in ("Theme Name:", "Version:"):
    if field not in sc: errors.append(f"style.css header missing '{field}'")

print("\n=== ERRORS ===" if errors else "\n=== NO ERRORS ===")
for e in errors: print("E:", e)
for w in warnings: print("W:", w)
sys.exit(1 if errors else 0)
