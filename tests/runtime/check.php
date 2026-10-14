<?php
require __DIR__ . '/stubs.php';
define('B_PROLOG_INCLUDED', true);
define('SITE_ID', 's1');
define('LANGUAGE_ID', 'ru');
$project = realpath($argv[1] ?? __DIR__ . '/../..');
$config = json_decode(file_get_contents($project . '/module.json'), true, 32, JSON_THROW_ON_ERROR);
$ns = $config['namespace']; $id = $config['id'];
foreach (['access', 'demo', 'diagnostics'] as $name) { require $project . '/module/lib/' . $name . '.php'; }
require $project . '/module/install/index.php';
$class = str_replace('.', '_', $id);
$module = new $class();
$demo = $ns . '\\Demo'; $access = $ns . '\\Access';
$USER = new FixtureUser(); $APPLICATION = new FixtureApplication(); $csrf = true;
$_SERVER['REQUEST_METHOD'] = 'POST'; $_POST = ['step' => '2'];
$root = sys_get_temp_dir() . '/b24-kit-' . bin2hex(random_bytes(6));
mkdir($root); mkdir($root . '/local'); mkdir($root . '/local/components');
\Bitrix\Main\Application::$root = $root;
file_put_contents($root . '/local/neighbor.txt', 'untouched');
$checks = 0;
function check(bool $condition, string $message): void { global $checks; if (!$condition) { throw new RuntimeException($message); } $checks++; }
function denied(callable $call): void {
    try { $call(); } catch (DomainException $error) { check(true, 'denied'); return; }
    throw new RuntimeException('Expected access rejection');
}
function removeTree(string $path): void {
    if (is_link($path) || is_file($path)) { unlink($path); return; }
    foreach (new DirectoryIterator($path) as $entry) { if (!$entry->isDot()) { removeTree($entry->getPathname()); } }
    rmdir($path);
}
try {
    // Permission and CSRF rejection must precede any option/cache/filesystem activity.
    $USER->authorized = false;
    denied(fn() => $demo::read());
    check(\Bitrix\Main\Config\Option::$reads === 0 && \Bitrix\Main\Data\Cache::$reads === 0, 'Denied user read cache/options');
    check(!$module->DoInstall(), 'Anonymous install allowed');
    $USER->authorized = true; $USER->admin = false; $APPLICATION->right = 'D';
    denied(fn() => $demo::read());
    check(!$module->DoInstall(), 'Non-admin installed module');
    $USER->admin = true; $csrf = false;
    check(!$module->DoInstall(), 'CSRF install allowed');
    denied(fn() => $demo::save('attack'));
    $csrf = true; $_SERVER['REQUEST_METHOD'] = 'GET';
    check(!$module->DoInstall(), 'GET install allowed');
    denied(fn() => $demo::save('attack'));
    $_SERVER['REQUEST_METHOD'] = 'POST';
    // Registration failure must restore real files and previous options.
    \Bitrix\Main\ModuleManager::$failRegister = true;
    check(!$module->DoInstall(), 'Failure injection ignored');
    check(!is_dir($root . '/local/components/' . explode('.', $id)[0]), 'Partial installation not rolled back');
    check(!\Bitrix\Main\ModuleManager::isModuleInstalled($id), 'Failed module remained registered');
    check(!str_contains(implode('', $APPLICATION->errors), 'PRIVATE'), 'Internal exception leaked');
    \Bitrix\Main\ModuleManager::$failRegister = false;
    \Bitrix\Main\ModuleManager::$mainVersion = '1.0.0';
    check(!$module->DoInstall(), 'Dependency preflight ignored');
    check(!is_dir($root . '/local/components/' . explode('.', $id)[0]), 'Dependency rejection changed files');
    \Bitrix\Main\ModuleManager::$mainVersion = '99.0.0';
    if ($config['storage']) {
        \Bitrix\Main\Application::getConnection()->failCreate = true;
        check(!$module->DoInstall(), 'DDL failure ignored');
        check(!\Bitrix\Main\ModuleManager::isModuleInstalled($id), 'DDL failure left registration');
        check(!is_dir($root . '/local/components/' . explode('.', $id)[0]), 'DDL failure left files');
        \Bitrix\Main\Application::getConnection()->failCreate = false;
    }
    $savedRoot = \Bitrix\Main\Application::$root;
    \Bitrix\Main\Application::$root = '/sys';
    check(!$module->DoInstall(), 'Read-only target accepted');
    \Bitrix\Main\Application::$root = $savedRoot;
    check($module->DoInstall(), 'Fresh install failed');
    $manifest = json_decode(\Bitrix\Main\Config\Option::get($id, 'files_manifest'), true);
    [$vendor, $name] = explode('.', $id);
    $component = $root . '/local/components/' . $vendor . '/' . $name . '.demo';
    foreach ($manifest as $p => $hash) { check(hash_file('sha256', $component . '/' . $p) === $hash, 'Installed file differs'); }
    check($module->DoInstall(), 'Repeated install failed');
    $demo::save('<script>alert(1)</script>');
    $data = $demo::read('');
    check($data['filter'] === 'welcome', 'Empty filter does not show effective default');
    check($data['text'] === '<script>alert(1)</script>', 'Storage did not preserve user text');
    $arResult = $data;
    ob_start(); require $project . '/module/install/components/' . $vendor . '/' . $name . '.demo/templates/.default/template.php'; $html = ob_get_clean();
    check(!str_contains($html, '<script>') && str_contains($html, '&lt;script&gt;') && str_contains($html, 'selected'), 'XSS/effective filter rendering failed');
    denied(fn() => $demo::read(['bad']));
    denied(fn() => $demo::read('random-growth-key'));
    $readCount = \Bitrix\Main\Data\Cache::$reads;
    $USER->admin = false; $APPLICATION->right = 'D';
    denied(fn() => $demo::read());
    check(\Bitrix\Main\Data\Cache::$reads === $readCount, 'Revoked user reached existing cache');
    $APPLICATION->right = 'R';
    $demo::read(); denied(fn() => $demo::export()); denied(fn() => $demo::save('bad'));
    $APPLICATION->right = 'E';
    check(is_string($demo::export()), 'Export permission failed');
    denied(fn() => $access::requirePermission('edit'));
    $APPLICATION->right = 'W'; $access::requirePermission('edit'); denied(fn() => $demo::save('bad'));
    $APPLICATION->right = 'X';
    denied(fn() => $access::owner(999));
    $USER->id = 11; $demo::read(); $USER->id = 10;
    check(count(\Bitrix\Main\Data\Cache::$values) >= 2, 'Cache crosses identities/rights');
    $demo::save('=HYPERLINK("bad")');
    check(str_contains($demo::export(), "'=HYPERLINK"), 'CSV formula not neutralized');
    foreach ([" =SUM(1)", "\t+cmd", '-1', '@formula'] as $text) { check(str_starts_with($demo::csvCell($text), "'"), 'Formula bypass'); }
    check($demo::read()['text'] === '=HYPERLINK("bad")', 'Cache was not invalidated');
    check(max(\Bitrix\Main\Data\Cache::$ttls) <= 60, 'TTL unbounded');
    $USER->admin = true;
    if ($config['storage']) {
        require $project . '/module/lib/notetable.php'; $notes = $ns . '\\NoteTable';
        \Bitrix\Main\ORM\Data\DataManager::$rows = [1 => ['ID' => 1, 'OWNER_ID' => 999, 'TEXT' => 'private']];
        check($notes::readOwn(1) === null, 'IDOR read another owner');
        \Bitrix\Main\ORM\Data\DataManager::$rows[1]['OWNER_ID'] = 10;
        check($notes::readOwn(1)['TEXT'] === 'private', 'Own object unavailable');
        $table = 'b_' . str_replace('.', '_', $id) . '_note';
        \Bitrix\Main\Application::getConnection()->tables[$table] = [['ID'=>1, 'TEXT'=>'keep']];
    }
    $updater = new stdClass();
    require $project . '/module/updater.php';
    check(!isset($errorMessage), 'Standalone pre-copy updater failed');
    // Independent new payload exercises migrations/order/repetition and rollback of active files.
    $payload = $root . '/payload';
    mkdir($payload . '/install', 0755, true);
    foreach (['files.php', 'update.php', 'migrations.php'] as $p) { copy($project . '/module/install/' . $p, $payload . '/install/' . $p); }
    copy($project . '/module/updater.php', $payload . '/updater.php');
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($project . '/module/install/components', FilesystemIterator::SKIP_DOTS));
    foreach ($it as $file) {
        $rel = substr($file->getPathname(), strlen($project . '/module/install/') );
        $dst = $payload . '/install/' . $rel;
        if (!is_dir(dirname($dst))) { mkdir(dirname($dst), 0755, true); }
        copy($file->getPathname(), $dst);
    }
    $payloadClass = $payload . '/install/components/' . $vendor . '/' . $name . '.demo/class.php';
    file_put_contents($payloadClass, file_get_contents($payloadClass) . "\n// New active release\n");
    $migrationText = "<?php return array_reverse([2 => static function(): void { \\Bitrix\\Main\\Config\\Option::set('" . $id . "', 'migration_order', '2'); }, 3 => static function(): void { if (\\Bitrix\\Main\\Config\\Option::get('" . $id . "', 'migration_order') !== '2') { throw new RuntimeException('Order failure'); } \\Bitrix\\Main\\Config\\Option::set('" . $id . "', 'migration_order', '3'); }], true);";
    file_put_contents($payload . '/install/migrations.php', $migrationText);
    require $payload . '/updater.php';
    check(!isset($errorMessage) && file_get_contents($component . '/class.php') === file_get_contents($payloadClass), 'Pre-copy active update failed');
    check(\Bitrix\Main\Config\Option::get($id, 'migration_order') === '3', 'Migration order failed');
    require $payload . '/updater.php';
    check(\Bitrix\Main\Config\Option::get($id, 'schema_version') === '3', 'Repeated migration failed');
    $newActive = file_get_contents($component . '/class.php');
    file_put_contents($payloadClass, $newActive . "\n// Should roll back\n");
    file_put_contents($payload . '/install/migrations.php', "<?php return [4 => static function(): void { throw new RuntimeException('PRIVATE failure'); }];");
    require $payload . '/updater.php';
    check(isset($errorMessage) && !str_contains($errorMessage, 'PRIVATE'), 'Updater did not fail safely');
    check(file_get_contents($component . '/class.php') === $newActive, 'Updater migration failure left new active code');
    unset($errorMessage);
    // Return to the full installed source, keeping user data and manifest consistent.
    require $project . '/module/updater.php';

    check($demo::read()['text'] === '=HYPERLINK("bad")', 'Update lost user setting');
    if ($config['storage']) { check(count(\Bitrix\Main\Application::getConnection()->tables[$table]) === 1, 'Update lost rows'); }
    // Customized active code must survive rejection; neighboring paths are untouched.
    $target = $component . '/class.php'; $original = file_get_contents($target);
    file_put_contents($target, 'custom');
    check(!$module->DoUninstall() && file_get_contents($target) === 'custom', 'Uninstall erased customization');
    file_put_contents($target, $original);
    $csrf = false; check(!$module->DoUninstall(), 'CSRF uninstall allowed'); $csrf = true;
    check($module->DoUninstall(), 'Keep-data uninstall failed');
    check($demo::read()['text'] === '=HYPERLINK("bad")', 'Uninstall lost setting');
    check($module->DoInstall(), 'Reinstall failed');
    if ($config['storage']) {
        check(count(\Bitrix\Main\Application::getConnection()->tables[$table]) === 1, 'Reinstall lost retained rows');
        check($module->DoUninstall(), 'Second uninstall failed');
        \Bitrix\Main\Application::getConnection()->dropTable($table);
        \Bitrix\Main\Config\Option::set($id, 'storage_cursor', '999');
        check($module->DoInstall(), 'Reinstall with stale options failed');
        check(\Bitrix\Main\Config\Option::get($id, 'storage_cursor') === '0', 'Old cursor prevented new storage');
        check($demo::read()['text'] === '=HYPERLINK("bad")', 'Fresh storage erased unrelated settings');
    }
    $_POST['purge'] = 'Y';
    check($module->DoUninstall(), 'Explicit purge failed');
    check(!isset(\Bitrix\Main\Config\Option::$values[$id]), 'Purge left options');
    if ($config['storage']) { check(!\Bitrix\Main\Application::getConnection()->isTableExists($table), 'Purge left table'); }
    // Symlink rejection must precede mutation even in an administrator request.
    removeTree($root . '/local/components/' . $vendor);
    mkdir($root . '/foreign'); symlink($root . '/foreign', $root . '/local/components/' . $vendor);
    check(!$module->DoInstall(), 'Symlink accepted');
    check(count(scandir($root . '/foreign')) === 2, 'Symlink target changed');
    check(file_get_contents($root . '/local/neighbor.txt') === 'untouched', 'Neighbor changed');
    echo json_encode(['profile' => $config['profile'], 'storage' => $config['storage'], 'behavior_checks' => $checks, 'native_bitrix' => false], JSON_UNESCAPED_UNICODE) . "\n";
} finally { removeTree($root); }
