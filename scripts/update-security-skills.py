#!/usr/bin/env python3
"""Explicitly refresh MIT vendor skills from a reviewed upstream commit, without executing them."""
import argparse
import hashlib
import json
import shutil
import subprocess
import tempfile
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
URL = 'https://github.com/bitrix-tools/marketplace-security-skills.git'


def hashes(path):
    return {str(p.relative_to(path)): hashlib.sha256(p.read_bytes()).hexdigest() for p in sorted(path.rglob('*')) if p.is_file() and '__pycache__' not in p.parts and p.suffix != '.pyc'}


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--ref', required=True, help='Reviewed full 40-character upstream commit SHA')
    args = parser.parse_args()
    import re
    if not re.fullmatch('[a-f0-9]{40}', args.ref):
        parser.error('Нужен полный SHA предварительно проверенного коммита.')
    target = ROOT / 'external/marketplace-security'
    lock_path = ROOT / 'external/marketplace-security.lock.json'
    lock = json.loads(lock_path.read_text())
    if hashes(target) != lock['files']:
        parser.error('Импорт локально изменён; автоматическая замена запрещена.')
    with tempfile.TemporaryDirectory(prefix='.work-security-', dir=ROOT) as tmp:
        clone = Path(tmp) / 'source'
        subprocess.run(['git', 'clone', '--no-checkout', URL, str(clone)], check=True)
        subprocess.run(['git', '-C', str(clone), 'checkout', '--detach', args.ref], check=True)
        if 'MIT License' not in (clone / 'LICENSE').read_text():
            parser.error('Лицензия изменилась: требуется проверка.')
        stage = Path(tmp) / 'import'
        stage.mkdir()
        for name in ['skills', 'tools', 'LICENSE', 'README.md']:
            src = clone / name
            for p in [src, *src.rglob('*')] if src.is_dir() else [src]:
                if p.is_symlink():
                    parser.error('Ссылки upstream запрещены.')
            if src.is_dir():
                shutil.copytree(src, stage / name)
            else:
                shutil.copy2(src, stage / name)
        new_lock = {'url': URL, 'commit': args.ref, 'license': 'MIT', 'files': hashes(stage)}
        backup = Path(tmp) / 'old'
        target.rename(backup)
        try:
            stage.rename(target)
            lock_path.write_text(json.dumps(new_lock, sort_keys=True, indent=2) + '\n')
        except BaseException:
            if target.exists():
                shutil.rmtree(target)
            backup.rename(target)
            raise
    print('Скиллы обновлены. Проверьте diff, процедуры и тесты перед коммитом; новые инструкции не исполнялись.')


if __name__ == '__main__':
    main()
