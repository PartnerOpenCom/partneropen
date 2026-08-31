#!/usr/bin/env python3
from __future__ import annotations

import pathlib
import sys
import zipfile


def main() -> int:
    if len(sys.argv) != 2:
        raise SystemExit("usage: verify_directory_package.py PACKAGE_ZIP")
    package = pathlib.Path(sys.argv[1])
    expected_root = 'partneropen-connector-directory/'
    with zipfile.ZipFile(package) as archive:
        names = set(archive.namelist())
        roots = {name.split('/', 1)[0] + '/' for name in names if name}
        if roots != {expected_root}:
            raise SystemExit(f'directory package must contain only the {expected_root} archive root')
        main_name = 'partneropen-connector-directory/partneropen-connector.php'
        readme_name = 'partneropen-connector-directory/readme.txt'
        if main_name not in names or readme_name not in names:
            raise SystemExit('directory package is missing the plugin bootstrap or readme')
        bootstrap = archive.read(main_name).decode('utf-8')
        readme = archive.read(readme_name).decode('utf-8')
        if "define('PARTNEROPEN_CONNECTOR_DIRECTORY_BUILD', true);" not in bootstrap:
            raise SystemExit('directory package does not enable directory build mode')
        if 'Contributors: partneropenteam' not in readme:
            raise SystemExit('directory readme must retain the explicit contributor placeholder until a real .org account is supplied')
        if 'without affiliate link blocks' not in readme:
            raise SystemExit('directory readme does not describe its link boundary')
        if 'same-origin resolver' in readme or 'rel="sponsored' in readme:
            raise SystemExit('directory readme contains full-build resolver/affiliate claims')
        if any('/tests/' in name for name in names):
            raise SystemExit('directory package contains tests')
    print(f'Verified directory package shape (replace Contributors before submission): {package}')
    return 0


if __name__ == '__main__':
    raise SystemExit(main())
