#!/usr/bin/env python3
from __future__ import annotations

import hashlib
import pathlib
import sys
import zipfile


def main() -> int:
    if len(sys.argv) not in (3, 4):
        raise SystemExit("usage: package_plugin.py partneropen-connector OUTPUT_ZIP [directory]")
    variant = sys.argv[3] if len(sys.argv) == 4 else ''
    if variant not in ('', 'directory'):
        raise SystemExit("variant must be empty or directory")

    root = pathlib.Path(__file__).resolve().parents[1]
    plugin_name = sys.argv[1]
    output = pathlib.Path(sys.argv[2]).resolve()
    source = root / "plugins" / plugin_name
    archive_root = f"{plugin_name}-directory" if variant == 'directory' else plugin_name
    if not source.is_dir():
        raise SystemExit(f"plugin directory not found: {source}")

    output.parent.mkdir(parents=True, exist_ok=True)
    output.unlink(missing_ok=True)
    with zipfile.ZipFile(output, "w", compression=zipfile.ZIP_DEFLATED) as archive:
        for path in sorted(source.rglob("*")):
            if not path.is_file() or "tests" in path.parts:
                continue
            archive_path = f"{archive_root}/{path.relative_to(source).as_posix()}"
            if variant == 'directory' and path.name == 'partneropen-connector.php':
                payload = path.read_bytes().replace(
                    b"define('PARTNEROPEN_CONNECTOR_DIRECTORY_BUILD', false);",
                    b"define('PARTNEROPEN_CONNECTOR_DIRECTORY_BUILD', true);",
                )
                archive.writestr(archive_path, payload)
            elif variant == 'directory' and path.name == 'readme.txt':
                directory_readme = root / 'distribution' / 'wordpress-org-directory-readme.txt'
                archive.writestr(archive_path, directory_readme.read_bytes())
            else:
                archive.write(path, archive_path)

    checksum = hashlib.sha256(output.read_bytes()).hexdigest()
    output.with_suffix(output.suffix + ".sha256").write_text(
        f"{checksum}  {output.name}\n", encoding="utf-8"
    )
    print(output)
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
