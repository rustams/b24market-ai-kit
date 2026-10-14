#!/usr/bin/env python3
"""Lint and exercise a generated module using PHP or an isolated, network-free PHP container."""
import argparse
import hashlib
import json
import shutil
import subprocess
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


def php_command(path, project, docker):
    if not docker and shutil.which('php'):
        return ['php', str(path)]
    relative = path.relative_to(project)
    return ['docker', 'run', '--rm', '--network', 'none', '--read-only', '--tmpfs', '/tmp:rw,nosuid,nodev', '-v', str(project) + ':/work:ro', '-w', '/work', docker or 'php:8.2-cli', 'php', '/work/' + str(relative)]


def verify(project, docker=None):
    project = Path(project).resolve()
    cfg = json.loads((project / 'module.json').read_text())
    php_files = sorted([*(project / 'module').rglob('*.php'), *(project / 'scripts').rglob('*.php')])
    if not docker and shutil.which('php'):
        for path in php_files:
            subprocess.run(['php', '-l', str(path)], check=True, stdout=subprocess.DEVNULL)
    else:
        # One PHP container, no portal mounts or network. Lint filenames come from a local filesystem iterator.
        code = 'foreach(["/work/module","/work/scripts"] as $dir){$it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir,FilesystemIterator::SKIP_DOTS));foreach($it as $p){if($p->getExtension()==="php"){passthru("php -l ".escapeshellarg($p->getPathname()),$s);if($s)exit($s);}}}'
        subprocess.run(['docker', 'run', '--rm', '--network', 'none', '--read-only', '-v', str(project) + ':/work:ro', docker or 'php:8.2-cli', 'php', '-r', code], check=True, stdout=subprocess.DEVNULL)
    args = php_command(project / 'tests/runtime/check.php', project, docker)
    if args[0] == 'php':
        args.append(str(project))
    else:
        args.append('/work')
    subprocess.run(args, check=True)
    lock = json.loads((project / 'external/marketplace-security.lock.json').read_text())
    imported = project / 'external/marketplace-security'
    actual = {str(p.relative_to(imported)): hashlib.sha256(p.read_bytes()).hexdigest() for p in sorted(imported.rglob('*')) if p.is_file() and '__pycache__' not in p.parts and p.suffix != '.pyc'}
    if actual != lock['files']:
        raise ValueError('Импортированные скиллы не совпадают с lock manifest.')
    for path in (project / 'module').rglob('*'):
        if path.is_file():
            data = path.read_bytes()
            if b'@@' in data or b'/Users/' in data or b'b24onprem' in data or b'rgdigital.radar' in data:
                raise ValueError('Непереносимый или незаполненный исходник: ' + str(path))
    if shutil.which('node'):
        for path in (project / 'module').rglob('*.js'):
            subprocess.run(['node', '--check', str(path)], check=True)
    print('PHP lint, поведение и целостность импорта прошли для ' + cfg['id'] + '. Нативная приёмка открыта.')


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--project', default=str(ROOT))
    parser.add_argument('--docker-image')
    args = parser.parse_args()
    verify(args.project, args.docker_image)


if __name__ == '__main__':
    main()
