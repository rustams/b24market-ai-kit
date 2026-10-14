<?php
if (PHP_SAPI !== 'cli' || $argc !== 2) { fwrite(STDERR, "Usage: php scripts/update-local.php DOCUMENT_ROOT\n"); exit(2); }
$root = realpath($argv[1]);
if ($root === false || !is_file($root . '/bitrix/modules/main/include/prolog_before.php')) { exit(2); }
$_SERVER['DOCUMENT_ROOT'] = $root;
require $root . '/bitrix/modules/main/include/prolog_before.php';
$updatePath = \Bitrix\Main\Loader::getLocal('modules/@@ID@@/install/update.php');
$source = $updatePath ? dirname($updatePath) : false;
if (!$source || !\Bitrix\Main\ModuleManager::isModuleInstalled('@@ID@@')) { fwrite(STDERR, "Module is not installed\n"); exit(2); }
try {
    $files = require $source . '/files.php';
    $result = $files($source, $root, json_decode(\Bitrix\Main\Config\Option::get('@@ID@@', 'files_manifest', '{}'), true, 32, JSON_THROW_ON_ERROR));
    try {
        $update = require $source . '/update.php';
        $update($source);
        \Bitrix\Main\Config\Option::set('@@ID@@', 'files_manifest', json_encode($result['manifest'], JSON_THROW_ON_ERROR));
    } catch (Throwable $error) { $result['undo'](); throw $error; }
    echo "Миграции выполнены. Активные копии и manifest согласованы; кластер проверяется отдельно.\n";
} catch (Throwable $error) {
    error_log('[@@ID@@] CLI update ' . get_class($error) . ' at ' . $error->getFile() . ':' . $error->getLine());
    fwrite(STDERR, "Обновление остановлено; проверьте серверный журнал.\n"); exit(1);
}
