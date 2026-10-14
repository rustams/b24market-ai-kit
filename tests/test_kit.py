import argparse
import hashlib
import importlib.util
import json
import os
from pathlib import Path
import shutil
import subprocess
import tempfile
import unittest
import zipfile

ROOT = Path(__file__).resolve().parents[1]


def load(name, path):
    spec = importlib.util.spec_from_file_location(name, path)
    module = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(module)
    return module


GEN = load('generator', ROOT / 'scripts/new-project.py')
BUILDER = load('builder', ROOT / 'scripts/build.py')
VERIFY = load('verify', ROOT / 'scripts/verify.py')


class KitTest(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.TemporaryDirectory(prefix='.work-test-', dir=ROOT)
        self.base = Path(self.tmp.name)

    def tearDown(self):
        self.tmp.cleanup()

    def args(self, profile='local', storage=False, **changes):
        data = dict(profile=profile, module_id='acme.hello', namespace='Acme\\Hello',
                    name='Пример', description='Описание', developer='Acme', website='https://example.org',
                    version='0.1.0', min_php='8.2.0', min_bitrix='23.0.0', output=str(self.base / profile), with_storage=storage)
        data.update(changes)
        return argparse.Namespace(**data)

    def generate(self, profile='local', storage=False, **changes):
        args = self.args(profile, storage, **changes)
        GEN.generate(args)
        return Path(args.output)

    def git(self, project, *args):
        return subprocess.check_output(['git', '-C', str(project), *args], stderr=subprocess.STDOUT).decode().strip()

    def init(self, project):
        self.git(project, 'init', '-b', 'main')
        self.git(project, 'config', 'user.name', 'Kit Tests')
        self.git(project, 'config', 'user.email', 'tests@example.invalid')
        self.git(project, 'add', '.')
        self.git(project, 'commit', '-m', 'initial')
        return self.git(project, 'rev-parse', 'HEAD')

    def test_profiles_are_standalone(self):
        for profile in ('local', 'marketplace'):
            with self.subTest(profile=profile):
                project = self.generate(profile)
                cfg = json.loads((project / 'module.json').read_text())
                self.assertEqual(cfg['profile'], profile)
                self.assertFalse((project / 'module/lib/notetable.php').exists())
                for path in project.rglob('*'):
                    if path.is_file() and 'external' not in path.parts:
                        data = path.read_bytes()
                        self.assertNotIn(b'/Users/rustam/', data)
                        self.assertNotRegex(data, rb'@@[A-Z_]+@@')
                        if path.relative_to(project).parts[0] == 'module':
                            self.assertNotIn(b'rgdigital.radar', data)
                            self.assertNotIn(b'b24onprem', data)
                self.assertTrue((project / 'scripts/build.py').is_file())
                self.assertTrue((project / 'tests/runtime/check.php').is_file())
                self.assertEqual(len(list((project / '.agents/skills').glob('*/SKILL.md'))), 8)
                self.assertTrue((project / 'external/marketplace-security/LICENSE').is_file())

    def test_rejects_malicious_inputs_before_writing(self):
        for field, values in {
            'module_id': ['../evil', 'acme.hello/evil', 'bitrix.crm', 'acme.under_score', '1acme.hello', 'acme.Hello', 'acme.hello.php'],
            'namespace': ['Acme\\Class', 'Bitrix\\Hello', 'Acme\\Hello;evil()', 'Acme', 'Acme\\../Evil'],
            'version': ['0.0.0', '01.0.0', '1.0.0-rc.1', '1.0', '../../x', '1.0.0;rm'],
            'min_php': ['7.4.0', 'bad'], 'website': ['javascript:alert(1)', 'https://user:pass@example.org', 'http://example.org'],
            'name': ['bad\x00', '', '@@ID@@'], 'output': [str(self.base / '..' / 'escape')]
        }.items():
            for value in values:
                with self.subTest(field=field, value=value):
                    with self.assertRaises((ValueError, OSError)):
                        GEN.generate(self.args(**{field: value}))
                    self.assertFalse((self.base / 'local').exists())

    def test_no_overwrite_or_symlink(self):
        existing = self.base / 'existing'
        existing.mkdir(); (existing / 'owned').write_text('keep')
        with self.assertRaises(ValueError):
            self.generate(output=str(existing))
        self.assertEqual((existing / 'owned').read_text(), 'keep')
        (self.base / 'link').symlink_to(existing, target_is_directory=True)
        with self.assertRaises(ValueError):
            self.generate(output=str(self.base / 'link' / 'new'))
        self.assertFalse((existing / 'new').exists())

    def test_quotes_do_not_inject_php(self):
        project = self.generate(name="'; system('evil'); //", developer='Acme \\ example')
        text = (project / 'module/lang/ru/install/index.php').read_text()
        self.assertIn("\\'; system(\\'evil\\'); //", text)
        self.assertEqual(GEN.php_literal("a'b\\c"), "'a\\'b\\\\c'")

    def test_cli_and_interactive_defaults(self):
        cmd = ['python3', str(ROOT / 'scripts/new-project.py'), '--non-interactive', '--profile', 'local', '--module-id', 'acme.hello', '--namespace', 'Acme\\Hello', '--name', 'Demo', '--description', 'Demo', '--developer', 'Acme', '--website', 'https://example.org', '--output', str(self.base / 'cli')]
        subprocess.run(cmd, check=True, stdout=subprocess.DEVNULL)
        cfg = json.loads((self.base / 'cli/module.json').read_text())
        self.assertEqual(cfg['version'], '0.1.0')
        answers = '\n'.join(['marketplace', 'acme.hello', 'Acme\\Hello', 'Demo', 'Demo', 'Acme', 'https://example.org', '', str(self.base / 'interactive'), '', '', 'нет']) + '\n'
        subprocess.run(['python3', str(ROOT / 'scripts/new-project.py')], input=answers.encode(), check=True, stdout=subprocess.DEVNULL)
        self.assertEqual(json.loads((self.base / 'interactive/module.json').read_text())['profile'], 'marketplace')

    def test_archives_and_manifest_reproducible(self):
        for profile in ('local', 'marketplace'):
            project = self.generate(profile)
            source = self.init(project)
            BUILDER.ROOT = project
            kind = 'local-full' if profile == 'local' else 'marketplace-full'
            one = self.base / (profile + '-one'); two = self.base / (profile + '-two')
            manifest = BUILDER.build(source, kind, one)
            # Uncommitted data must not enter the artifact.
            (project / 'module/options.php').write_text('UNCOMMITTED')
            BUILDER.build(source, kind, two)
            for path in one.iterdir():
                self.assertEqual(path.read_bytes(), (two / path.name).read_bytes())
            archive = next(one.glob('*.zip'))
            with zipfile.ZipFile(archive) as z:
                self.assertEqual(set(z.namelist()), {f['path'] for f in manifest['files']})
                for f in manifest['files']:
                    self.assertEqual(hashlib.sha256(z.read(f['path'])).hexdigest(), f['sha256'])
                self.assertTrue(all(p.startswith('acme.hello/' if profile == 'local' else '.last_version/') for p in z.namelist()))
                self.assertFalse(any('tests/' in p or '.DS_Store' in p for p in z.namelist()))
            with self.assertRaises(ValueError):
                BUILDER.build(source, kind, one)
            wrong = 'marketplace-full' if profile == 'local' else 'local-full'
            with self.assertRaises(ValueError):
                BUILDER.build(source, wrong, self.base / 'wrong')
            if profile == 'local':
                deploy = self.base / 'deploy'; m = BUILDER.build(source, 'local-deployment', deploy)
                with zipfile.ZipFile(next(deploy.glob('*.zip'))) as z:
                    for p in z.namelist():
                        if p.startswith('local/components/'):
                            original = 'local/modules/acme.hello/install/components/' + p[len('local/components/'):]
                            self.assertEqual(z.read(p), z.read(original))
                    self.assertTrue(all(p.startswith(('local/modules/acme.hello/', 'local/components/acme/hello.demo/')) for p in z.namelist()))

    def test_marketplace_delta_and_base_contract(self):
        project = self.generate('marketplace')
        first = self.init(project); BUILDER.ROOT = project
        config = json.loads((project / 'module.json').read_text()); config['version'] = '0.2.0'
        (project / 'module.json').write_text(json.dumps(config))
        version = project / 'module/install/version.php'; version.write_text(version.read_text().replace('0.1.0', '0.2.0'))
        demo = project / 'module/lib/demo.php'; demo.write_text(demo.read_text().replace('Модуль установлен.', 'Новая версия.'))
        self.git(project, 'add', '.'); self.git(project, 'commit', '-m', 'update')
        source = self.git(project, 'rev-parse', 'HEAD')
        manifest = BUILDER.build(source, 'marketplace-update', self.base / 'delta', first, '0.1.0')
        self.assertEqual(manifest['base_commit'], first)
        with zipfile.ZipFile(next((self.base / 'delta').glob('*.zip'))) as z:
            self.assertIn('0.2.0/lib/demo.php', z.namelist())
            self.assertIn('0.2.0/install/version.php', z.namelist())
            self.assertIn('0.2.0/updater.php', z.namelist())
            self.assertNotIn('0.2.0/lib/access.php', z.namelist())
            self.assertNotIn('0.2.0/options.php', z.namelist())
        for base, previous in [(None, None), (first, '9.0.0'), (source, '0.2.0')]:
            with self.assertRaises(ValueError):
                BUILDER.build(source, 'marketplace-update', self.base / 'bad', base, previous)
        self.git(project, 'rm', 'module/lib/demo.php'); self.git(project, 'commit', '-m', 'delete')
        with self.assertRaisesRegex(ValueError, 'Удаление'):
            BUILDER.build('HEAD', 'marketplace-update', self.base / 'delete', first, '0.1.0')

    def test_build_rejects_tracked_secret_and_symlink(self):
        project = self.generate(); self.init(project); BUILDER.ROOT = project
        (project / 'module/.env').write_text('secret=synthetic')
        self.git(project, 'add', '-f', 'module/.env'); self.git(project, 'commit', '-m', 'bad fixture')
        with self.assertRaises(ValueError):
            BUILDER.build('HEAD', 'local-full', self.base / 'bad')
        self.git(project, 'rm', 'module/.env')
        (project / 'module/lib/alias.php').symlink_to('demo.php')
        self.git(project, 'add', '.'); self.git(project, 'commit', '-m', 'symlink fixture')
        with self.assertRaises(ValueError):
            BUILDER.build('HEAD', 'local-full', self.base / 'link')

    def test_runtime_php_profiles_and_storage(self):
        for profile, storage in [('local', False), ('local', True), ('marketplace', False), ('marketplace', True)]:
            project = self.generate(profile, storage, output=str(self.base / (profile + str(storage))))
            VERIFY.verify(project, os.environ.get('KIT_PHP_IMAGE'))

    def test_security_refresh_preserves_local_changes(self):
        project = self.generate()
        imported = project / 'external/marketplace-security/README.md'
        imported.write_text('Local customization must survive')
        lock = (project / 'external/marketplace-security.lock.json').read_bytes()
        ref = json.loads(lock)['commit']
        result = subprocess.run(['python3', str(project / 'scripts/update-security-skills.py'), '--ref', ref], capture_output=True)
        self.assertEqual(result.returncode, 2)
        self.assertIn('локально изменён'.encode(), result.stderr)
        self.assertEqual(imported.read_text(), 'Local customization must survive')
        self.assertEqual((project / 'external/marketplace-security.lock.json').read_bytes(), lock)
        bad = subprocess.run(['python3', str(project / 'scripts/update-security-skills.py'), '--ref', 'HEAD'], capture_output=True)
        self.assertEqual(bad.returncode, 2)

    def test_skill_routing_and_references(self):
        for skill in (ROOT / '.agents/skills').glob('*/SKILL.md'):
            text = skill.read_text()
            self.assertTrue(text.startswith('---\nname: '))
            self.assertIn('description:', text)
            self.assertGreater(len(text), 1500)
            import re
            for link in re.findall(r'\]\(([^)]+)\)', text):
                if not link.startswith('http'):
                    self.assertTrue((skill.parent / link).resolve().exists(), link)


if __name__ == '__main__':
    unittest.main()
