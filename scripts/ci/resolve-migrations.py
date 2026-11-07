#!/usr/bin/env python3
"""Decide whether this deploy runs forward database migrations.

Production migrates only when ``prod_migrations`` is the exact string
``true``. Omitting it, or passing the historical ``run_migrations`` flag,
does not migrate production. Other environments keep ``run_migrations``
(default true at the workflow input).

A true result means apply new DDL. It does not take a backup. Callers must
already have a backup and a schema change that the previous image can still
run. Deploying an older image later does not undo migrations.
"""

from __future__ import annotations

import os


def resolve_migrations(environment: str, run_migrations: str, prod_migrations: str) -> str:
    if environment == 'prod':
        enabled = prod_migrations == 'true'
    else:
        enabled = run_migrations == 'true'
    return 'true' if enabled else 'false'


def main() -> None:
    value = resolve_migrations(
        os.environ.get('DEPLOY_ENVIRONMENT', ''),
        os.environ.get('RUN_MIGRATIONS_INPUT', ''),
        os.environ.get('PROD_MIGRATIONS_INPUT', ''),
    )
    line = f'run={value}\n'
    output = os.environ.get('GITHUB_OUTPUT')
    if output:
        with open(output, 'a', encoding='utf-8') as handle:
            handle.write(line)
    else:
        print(line, end='')


if __name__ == '__main__':
    main()
