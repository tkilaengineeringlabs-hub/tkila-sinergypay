#!/usr/bin/env python3
"""Package only audited runtime files; never include docs, secrets or dev files."""
from pathlib import Path
import re
import zipfile

root = Path(__file__).resolve().parents[1]
entry = root / 'tkila-sinergypay.php'
version = re.search(r'^ \* Version: ([0-9.]+)$', entry.read_text(), re.M).group(1)
files = [entry, root / 'README.md'] + sorted((root / 'includes').glob('class-*.php'))
for file in files:
    assert file.is_file() and not file.is_symlink(), file
    for dependency in re.findall(r"require_once TKILA_SINERGYPAY_PATH \. '([^']+)'", file.read_text()):
        assert root / dependency in files, f'Missing runtime dependency: {dependency}'
target = root / 'dist' / f'tkila-sinergypay-{version}-sandbox.zip'
target.parent.mkdir(exist_ok=True)
with zipfile.ZipFile(target, 'w', zipfile.ZIP_DEFLATED) as archive:
    for file in files:
        archive.write(file, 'tkila-sinergypay/' + file.relative_to(root).as_posix())
with zipfile.ZipFile(target) as archive:
    assert archive.testzip() is None
    assert len(archive.namelist()) == len(files)
print(target)
print(f'{len(files)} files; CRC verified; development files and official confidential PDF excluded.')
