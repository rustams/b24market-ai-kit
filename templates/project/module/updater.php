<?php
if (!isset($updater) || !is_object($updater)) { return; }
try {
    if (\Bitrix\Main\ModuleManager::isModuleInstalled('@@ID@@')) {
        $files = require __DIR__ . '/install/files.php';
        $result = $files(__DIR__ . '/install', \Bitrix\Main\Application::getDocumentRoot(), json_decode(\Bitrix\Main\Config\Option::get('@@ID@@', 'files_manifest', '{}'), true, 32, JSON_THROW_ON_ERROR));
        try {
            $update = require __DIR__ . '/install/update.php';
            $update(__DIR__ . '/install');
            \Bitrix\Main\Config\Option::set('@@ID@@', 'files_manifest', json_encode($result['manifest'], JSON_THROW_ON_ERROR));
        } catch (Throwable $error) { $result['undo'](); throw $error; }
    }
} catch (Throwable $error) {
    error_log('[@@ID@@] updater ' . get_class($error) . ' at ' . $error->getFile() . ':' . $error->getLine());
    $errorMessage = 'Обновление остановлено. Проверьте защищённый серверный журнал.';
}
