#!/usr/bin/env python3
"""Build a deterministic installable theme; never export WordPress data."""
from pathlib import Path
import hashlib
import re
import sys
import zipfile

ROOT = Path(__file__).resolve().parent.parent
header = (ROOT / 'style.css').read_text()
version = re.search(r'^Version: (\d+\.\d+\.\d+)$', header, re.M).group(1)
assert f"define('MARANDA_THEME_VERSION', '{version}');" in (ROOT / 'functions.php').read_text()
assert 'Update URI: https://github.com/maranda-dev/maranda-wp-theme' in header
if len(sys.argv) > 1:
    assert sys.argv[1] == 'v' + version, 'Le tag et la version du thème diffèrent.'
paths = [ROOT / 'style.css', ROOT / 'theme.json', ROOT / 'functions.php']
for directory in ['assets', 'data', 'inc', 'parts', 'templates']:
    paths.extend(p for p in (ROOT / directory).rglob('*') if p.is_file())
assert (ROOT / 'templates/index.html') in paths
dist = ROOT / 'dist'
dist.mkdir(exist_ok=True)
target = dist / f'maranda-wp-theme-{version}.zip'
with zipfile.ZipFile(target, 'w', zipfile.ZIP_DEFLATED) as archive:
    for source in sorted(paths):
        relative = source.relative_to(ROOT)
        assert source.suffix in {'.php', '.css', '.html', '.json', '.js', '.svg', '.png', '.jpg', '.webp', '.txt'}
        info = zipfile.ZipInfo('maranda-wp-theme/' + relative.as_posix(), (2026, 1, 1, 0, 0, 0))
        info.external_attr = 0o100644 << 16
        info.compress_type = zipfile.ZIP_DEFLATED
        archive.writestr(info, source.read_bytes())
digest = hashlib.sha256(target.read_bytes()).hexdigest()
(dist / 'SHA256SUMS').write_text(f'{digest}  {target.name}\n')
print(target.name, digest)
