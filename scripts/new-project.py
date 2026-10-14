#!/usr/bin/env python3
"""Generate a standalone PHP/D7 module project (Python standard library only)."""
import argparse
from datetime import datetime, timezone
import json
import re
import shutil
import tempfile
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
RESERVED = {'bitrix', 'main', 'crm', 'local', 'install', 'admin', 'ui', 'security'}
PHP_RESERVED = {'class', 'trait', 'interface', 'enum', 'function', 'namespace', 'self', 'parent', 'static', 'null', 'true', 'false', 'match', 'readonly', 'use', 'new', 'extends', 'implements', 'public', 'private', 'protected', 'final', 'abstract', 'echo', 'return', 'exit', 'die', 'array', 'string', 'int', 'float', 'bool', 'object', 'void', 'mixed', 'never', 'iterable', 'callable', 'resource', 'const', 'default', 'global', 'include', 'require', 'yield', 'if', 'else', 'while', 'for', 'switch', 'try', 'catch', 'throw', 'list', 'empty', 'isset', 'unset', 'var', 'clone', 'fn', 'break', 'continue', 'case', 'do', 'as', 'instanceof', 'print', 'eval', 'goto', 'finally', 'insteadof', 'and', 'or', 'xor', '__halt_compiler'}


def version(value):
    if not re.fullmatch(r'(?:0|[1-9][0-9]*)\.(?:0|[1-9][0-9]*)\.(?:0|[1-9][0-9]*)', value) or value == '0.0.0':
        raise ValueError('Ожидается ненулевая версия X.Y.Z без суффикса.')
    if any(int(v) > 9999 for v in value.split('.')):
        raise ValueError('Слишком большой компонент версии.')
    return tuple(map(int, value.split('.')))


def php_literal(value):
    return "'" + value.replace('\\', '\\\\').replace("'", "\\'") + "'"


def safe_new_path(value):
    raw = Path(value).expanduser()
    if '..' in raw.parts or '\\' in str(raw) or '\x00' in str(raw):
        raise ValueError('Недопустимый путь результата.')
    path = raw.absolute()
    for parent in [path, *path.parents]:
        if parent.is_symlink():
            raise ValueError('Символические ссылки в пути запрещены.')
    if path.exists():
        raise ValueError('Каталог результата уже существует; перезапись запрещена.')
    return path


def validate(args):
    if not re.fullmatch(r'[a-z][a-z0-9]{1,19}\.[a-z][a-z0-9]{1,29}', args.module_id):
        raise ValueError('ID: vendor.module, строчные латинские буквы/цифры, без подчёркивания.')
    vendor, module = args.module_id.split('.')
    if vendor in RESERVED or module in RESERVED:
        raise ValueError('Зарезервированный ID модуля.')
    if not re.fullmatch(r'[A-Z][A-Za-z0-9]*(?:\\[A-Z][A-Za-z0-9]*)+', args.namespace):
        raise ValueError('Namespace: Vendor\\Module, минимум два сегмента.')
    if any(p.lower() in PHP_RESERVED | RESERVED for p in args.namespace.split('\\')):
        raise ValueError('Namespace использует зарезервированное имя.')
    version(args.version)
    version(args.min_bitrix)
    version(args.min_php)
    if version(args.min_php) < (8, 2, 0):
        raise ValueError('Каркас поддерживает PHP начиная с 8.2.0.')
    for field in ['name', 'description', 'developer', 'website']:
        value = getattr(args, field)
        if '@@' in value or not value or len(value) > (2000 if field == 'description' else 200) or any(ord(c) < 32 for c in value):
            raise ValueError('Пустое, длинное или управляющее значение: ' + field)
    if not re.fullmatch(r'https://[A-Za-z0-9.-]+(?::[0-9]{1,5})?(?:/[A-Za-z0-9/._~%+-]*)?', args.website):
        raise ValueError('Сайт должен быть HTTPS URL без credentials, query и fragment.')
    return safe_new_path(args.output)


def generate(args):
    target = validate(args)
    vendor, module = args.module_id.split('.')
    values = {'ID': args.module_id, 'CLASS': args.module_id.replace('.', '_'),
              'LOC': args.module_id.replace('.', '_').upper(), 'NS': args.namespace, 'NS_LITERAL': php_literal(args.namespace),
              'VENDOR': vendor, 'MODULE': module, 'VERSION': args.version, 'VERSION_DATE': datetime.now(timezone.utc).strftime('%Y-%m-%d %H:%M:%S'),
              'MIN_PHP': args.min_php, 'MIN_BITRIX': args.min_bitrix,
              'NAME_PHP': php_literal(args.name), 'DESCRIPTION_PHP': php_literal(args.description),
              'DEVELOPER_PHP': php_literal(args.developer), 'WEBSITE_PHP': php_literal(args.website),
              'PROFILE': args.profile, 'STORAGE': 'true' if args.with_storage else 'false',
              'TABLE': 'b_' + args.module_id.replace('.', '_') + '_note'}
    target.parent.mkdir(parents=True, exist_ok=True)
    stage = Path(tempfile.mkdtemp(prefix='.b24-generate-', dir=target.parent))
    try:
        for src in (ROOT / 'templates/project').rglob('*'):
            if not src.is_file():
                continue
            rel = str(src.relative_to(ROOT / 'templates/project'))
            data = src.read_text()
            # Single pass prevents user text from becoming a second template injection.
            rel = re.sub(r'@@([A-Z_]+)@@', lambda m: values[m[1]], rel)
            data = re.sub(r'@@([A-Z_]+)@@', lambda m: values[m[1]], data)
            if not args.with_storage and rel == 'module/lib/notetable.php':
                continue
            dst = stage / rel
            dst.parent.mkdir(parents=True, exist_ok=True)
            dst.write_text(data)
        shutil.copytree(ROOT / '.agents', stage / '.agents', ignore=shutil.ignore_patterns('__pycache__', '*.pyc'))
        shutil.copytree(ROOT / 'docs', stage / 'docs')
        shutil.copytree(ROOT / 'external', stage / 'external', ignore=shutil.ignore_patterns('__pycache__', '*.pyc'))
        for name in ['build.py', 'verify.py', 'update-security-skills.py']:
            shutil.copy2(ROOT / 'scripts' / name, stage / 'scripts' / name)
        shutil.copytree(ROOT / 'tests/runtime', stage / 'tests/runtime')
        (stage / 'module.json').write_text(json.dumps({
            'id': args.module_id, 'namespace': args.namespace, 'profile': args.profile,
            'version': args.version, 'min_php': args.min_php, 'min_bitrix': args.min_bitrix,
            'storage': args.with_storage}, ensure_ascii=False, indent=2) + '\n')
        shutil.copy2(ROOT / 'LICENSE', stage / 'LICENSE')
        shutil.copy2(ROOT / 'LICENSE', stage / 'module/LICENSE')
        if target.exists() or target.is_symlink():
            raise ValueError('Каталог появился во время генерации; запись отменена.')
        stage.rename(target)
    except BaseException:
        shutil.rmtree(stage, ignore_errors=True)
        raise
    print('Проект создан: ' + str(target))


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    for flag in ['profile', 'module-id', 'namespace', 'name', 'description', 'developer', 'website', 'version', 'output', 'min-php', 'min-bitrix']:
        parser.add_argument('--' + flag, choices=['local', 'marketplace'] if flag == 'profile' else None)
    parser.add_argument('--non-interactive', action='store_true', help='Без вопросов; обязательные поля передаются параметрами.')
    parser.add_argument('--with-storage', action='store_true', help='Добавить пример D7 ORM с собственной таблицей заметок.')
    args = parser.parse_args()
    defaults = {'profile': 'local', 'version': '0.1.0', 'min_php': '8.2.0', 'min_bitrix': '23.0.0'}
    labels = {'profile': 'Профиль (local/marketplace)', 'module_id': 'ID vendor.module', 'namespace': 'Namespace Vendor\\Module', 'name': 'Название', 'description': 'Описание', 'developer': 'Разработчик', 'website': 'Сайт https://...', 'version': 'Начальная версия', 'output': 'Новый каталог результата', 'min_php': 'Минимальная версия PHP', 'min_bitrix': 'Минимальная версия главного модуля Bitrix'}
    try:
        for field, label in labels.items():
            if getattr(args, field) is None:
                default = defaults.get(field, '')
                if args.non_interactive:
                    if not default:
                        raise ValueError('Не задан обязательный параметр --' + field.replace('_', '-'))
                    setattr(args, field, default)
                    continue
                setattr(args, field, input(label + (' [' + default + ']' if default else '') + ': ').strip() or default)
        if args.profile not in ('local', 'marketplace'):
            raise ValueError('Неизвестный профиль.')
        if not args.non_interactive and not args.with_storage:
            args.with_storage = input('Включить опциональное ORM-хранилище? [нет]: ').strip().lower() in ('да', 'yes', 'y')
        generate(args)
    except (ValueError, OSError, EOFError) as error:
        parser.exit(2, str(error) + '\n')


if __name__ == '__main__':
    main()
