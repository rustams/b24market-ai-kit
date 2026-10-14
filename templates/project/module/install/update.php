<?php
// Shared update operation: no DoInstall, no new-module API in a SiteUpdate request.
return static function (string $source): void {
    $id = '@@ID@@';
    if (!\Bitrix\Main\ModuleManager::isModuleInstalled($id)) { return; }
    $migrations = require $source . '/migrations.php';
    ksort($migrations, SORT_NUMERIC);
    $current = (int)\Bitrix\Main\Config\Option::get($id, 'schema_version', '0');
    // Reconciliation is needed after external table deletion even with retained options.
    foreach ($migrations as $version => $migration) {
        if (!is_int($version) || $version < 1 || !is_callable($migration)) { throw new RuntimeException('Invalid migration'); }
        if ($version > $current || $version === 1) { $migration(); }
        if ($version > $current) { \Bitrix\Main\Config\Option::set($id, 'schema_version', (string)$version); }
    }
};
