#!/usr/bin/env python3
"""Build immutable local or Marketplace archives from Git blobs, never working files."""
import argparse
import hashlib
import json
import re
import subprocess
import zipfile
from pathlib import Path, PurePosixPath

ROOT = Path(__file__).resolve().parents[1]


def git(*args):
    return subprocess.check_output(['git', '-C', str(ROOT), *args])


def commit(ref):
    return git('rev-parse', '--verify', ref + '^{commit}').decode().strip()


def files(ref):
    result = {}
    for entry in git('ls-tree', '-r', '-z', ref, '--', 'module/').split(b'\0'):
        if not entry:
            continue
        meta, raw_path = entry.split(b'\t', 1)
        mode, kind, oid = meta.decode().split()
        path = raw_path.decode()[len('module/'):]
        parts = PurePosixPath(path).parts
        if mode not in ('100644', '100755') or kind != 'blob':
            raise ValueError('Ссылки и submodules в модуле запрещены: ' + path)
        if any(p.startswith(('.env', '._')) or p in {'tests', 'node_modules', '__MACOSX', '.DS_Store', 'tmp', 'build'} or p.endswith(('.key', '.pem', '.bak', '.tmp', '~')) for p in parts):
            raise ValueError('Запрещённый файл в модуле: ' + path)
        if parts[0] not in {'install', 'lib', 'lang', 'admin', 'assets', 'vendor'} and path not in {'include.php', 'options.php', 'default_option.php', '.settings.php', 'updater.php', 'description.ru', 'composer.json', 'composer.lock', 'LICENSE'}:
            raise ValueError('Неклассифицированный файл: ' + path)
        data = git('cat-file', 'blob', oid)
        if b'PRIVATE KEY-----' in data or re.search(rb'(?:ghp_|github_pat_|sk-proj-)[A-Za-z0-9_]{20,}', data):
            raise ValueError('Возможный секрет: ' + path)
        result[path] = data
    for required in ('install/index.php', 'install/version.php', 'include.php', 'options.php'):
        if required not in result:
            raise ValueError('Нет обязательного файла: ' + required)
    if 'composer.json' in result and 'composer.lock' not in result:
        raise ValueError('Composer требует зафиксированного lockfile.')
    return result


def metadata(ref, contents):
    config = json.loads(git('show', ref + ':module.json'))
    match = re.search(rb'"VERSION"\s*=>\s*"([0-9]+\.[0-9]+\.[0-9]+)"', contents['install/version.php'])
    if not match or match[1].decode() != config['version']:
        raise ValueError('Версия module.json не совпадает с install/version.php.')
    if not re.fullmatch(r'[a-z][a-z0-9]{1,19}\.[a-z][a-z0-9]{1,29}', config['id']):
        raise ValueError('Недопустимый ID в module.json.')
    return config


def build(ref, kind, output, base_ref=None, previous_version=None):
    source = commit(ref)
    contents = files(source)
    cfg = metadata(source, contents)
    if cfg['profile'] != ('local' if kind.startswith('local') else 'marketplace'):
        raise ValueError('Команда сборки не соответствует профилю проекта.')
    base = None
    removed = []
    if kind == 'marketplace-update':
        if not base_ref or not previous_version:
            raise ValueError('Для обновления обязательны --base-ref и --previous-version.')
        base = commit(base_ref)
        subprocess.run(['git', '-C', str(ROOT), 'merge-base', '--is-ancestor', base, source], check=True)
        old = files(base)
        old_cfg = metadata(base, old)
        if old_cfg['id'] != cfg['id'] or old_cfg['profile'] != cfg['profile'] or old_cfg['version'] != previous_version:
            raise ValueError('Предыдущая версия/ID/профиль не совпадают с базовым коммитом.')
        if tuple(map(int, previous_version.split('.'))) >= tuple(map(int, cfg['version'].split('.'))):
            raise ValueError('Обновление должно увеличивать версию.')
        removed = sorted(set(old) - set(contents))
        if removed:
            # shortcut: deletion is fail-closed until a vendor-documented removal migration is reviewed.
            raise ValueError('Удаление файлов не поддерживается автоматически. Нужна отдельная проверенная миграция: ' + ', '.join(removed))
        payload = {p: d for p, d in contents.items() if old.get(p) != d}
        # Updater runs before copying and needs a complete external component tree and standalone support.
        for p, d in contents.items():
            if p.startswith('install/components/') or p in {'install/version.php', 'install/files.php', 'install/migrations.php', 'install/update.php', 'updater.php', 'description.ru'}:
                payload[p] = d
        if 'updater.php' not in payload or 'description.ru' not in payload:
            raise ValueError('Служебные файлы обновления отсутствуют.')
        prefix, filename = cfg['version'] + '/', cfg['version'] + '.zip'
    else:
        payload = {p: d for p, d in contents.items() if p not in {'updater.php', 'description.ru'}}
        prefix = '.last_version/' if kind == 'marketplace-full' else cfg['id'] + '/'
        # macOS naming exception in vendor documentation; internal directory keeps its leading dot.
        filename = 'last_version.zip' if kind == 'marketplace-full' else cfg['id'] + '-' + cfg['version'] + '-' + kind + '.zip'
    archive_contents = {prefix + p: d for p, d in payload.items()}
    if kind == 'local-deployment':
        archive_contents = {'local/modules/' + p: d for p, d in archive_contents.items()}
        component = 'install/components/'
        for p, d in payload.items():
            if p.startswith(component):
                archive_contents['local/components/' + p[len(component):]] = d
    output = Path(output).absolute()
    if '..' in output.parts or any(p.is_symlink() for p in [output, *output.parents]) or output.exists():
        raise ValueError('Используйте новый каталог без ссылок и traversal; архивы не заменяются.')
    output.mkdir(parents=True)
    archive = output / filename
    with zipfile.ZipFile(archive, 'w', compression=zipfile.ZIP_DEFLATED, compresslevel=9) as z:
        for path, data in sorted(archive_contents.items()):
            info = zipfile.ZipInfo(path, date_time=(2026, 1, 1, 0, 0, 0))
            info.create_system = 3
            info.external_attr = 0o100644 << 16
            info.compress_type = zipfile.ZIP_DEFLATED
            z.writestr(info, data, compresslevel=9)
    with zipfile.ZipFile(archive) as z:
        if z.testzip() is not None or set(z.namelist()) != set(archive_contents):
            raise ValueError('Проверка архива не прошла.')
        for path, data in archive_contents.items():
            if z.read(path) != data:
                raise ValueError('Архив отличается от исходника.')
    manifest = {'module': cfg['id'], 'version': cfg['version'], 'kind': kind, 'source_commit': source,
                'base_commit': base, 'previous_version': previous_version, 'removed_files': removed,
                'archive_sha256': hashlib.sha256(archive.read_bytes()).hexdigest(),
                'files': [{'path': p, 'bytes': len(d), 'sha256': hashlib.sha256(d).hexdigest()} for p, d in sorted(archive_contents.items())]}
    (output / 'manifest.json').write_text(json.dumps(manifest, ensure_ascii=False, sort_keys=True, indent=2) + '\n')
    (output / 'SHA256SUMS').write_text(''.join(hashlib.sha256(p.read_bytes()).hexdigest() + '  ' + p.name + '\n' for p in [archive, output / 'manifest.json']))
    return manifest


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('kind', choices=['local-full', 'local-deployment', 'marketplace-full', 'marketplace-update'])
    parser.add_argument('--ref', required=True)
    parser.add_argument('--output', required=True)
    parser.add_argument('--base-ref')
    parser.add_argument('--previous-version')
    args = parser.parse_args()
    try:
        result = build(args.ref, args.kind, args.output, args.base_ref, args.previous_version)
        print(json.dumps({'commit': result['source_commit'], 'files': len(result['files']), 'sha256': result['archive_sha256']}, ensure_ascii=False))
    except (ValueError, OSError, subprocess.CalledProcessError) as error:
        parser.exit(2, str(error) + '\n')


if __name__ == '__main__':
    main()
