<?php
use Bitrix\Main\Config\Option;
use Bitrix\Main\ModuleManager;
use Bitrix\Main\Localization\Loc;
Loc::loadMessages(__FILE__);

class @@CLASS@@ extends CModule
{
    public $MODULE_ID = '@@ID@@';
    public $MODULE_VERSION;
    public $MODULE_VERSION_DATE;
    public $MODULE_NAME;
    public $MODULE_DESCRIPTION;
    public $PARTNER_NAME = @@DEVELOPER_PHP@@;
    public $PARTNER_URI = @@WEBSITE_PHP@@;
    public $MODULE_GROUP_RIGHTS = 'Y';

    public function __construct()
    {
        require __DIR__ . '/version.php';
        $this->MODULE_VERSION = $arModuleVersion['VERSION'];
        $this->MODULE_VERSION_DATE = $arModuleVersion['VERSION_DATE'];
        $this->MODULE_NAME = Loc::getMessage('@@LOC@@_NAME');
        $this->MODULE_DESCRIPTION = Loc::getMessage('@@LOC@@_DESCRIPTION');
    }
    public function GetModuleRightList(): array
    {
        return ['reference_id' => ['D','R','E','W','X'], 'reference' => ['Запрет','Просмотр','Экспорт','Изменение','Настройки']];
    }
    private function authorized(): bool
    {
        global $USER;
        return $USER && $USER->IsAuthorized() && $USER->IsAdmin() && $_SERVER['REQUEST_METHOD'] === 'POST' && check_bitrix_sessid();
    }
    private function fail(Throwable $error): bool
    {
        global $APPLICATION;
        error_log('[@@ID@@] lifecycle ' . get_class($error) . ' at ' . $error->getFile() . ':' . $error->getLine());
        $APPLICATION->ThrowException(Loc::getMessage('@@LOC@@_INSTALL_ERROR'));
        return false;
    }
    public function DoInstall(): bool
    {
        if (!$this->authorized()) { return false; }
        if (ModuleManager::isModuleInstalled($this->MODULE_ID)) { return true; }
        $undo = null; $db = null; $tableExisted = true; $snapshot = [];
        try {
            $db = \Bitrix\Main\Application::getConnection();
            $tableExisted = @@STORAGE@@ && $db->isTableExists('@@TABLE@@');
            foreach (['greeting', 'files_manifest', 'schema_version', 'storage_cursor'] as $key) {
                $snapshot[$key] = Option::get($this->MODULE_ID, $key, '__KIT_ABSENT__');
            }
            if (version_compare(PHP_VERSION, '@@MIN_PHP@@', '<') || version_compare(ModuleManager::getVersion('main') ?: '0', '@@MIN_BITRIX@@', '<')) {
                throw new RuntimeException('Unsupported dependency version');
            }
            if (@@STORAGE@@ && $db->getType() !== 'mysql') { throw new RuntimeException('Unsupported storage database'); }
            $files = require __DIR__ . '/files.php';
            $result = $files(__DIR__, \Bitrix\Main\Application::getDocumentRoot(), json_decode(Option::get($this->MODULE_ID, 'files_manifest', '{}'), true, 32, JSON_THROW_ON_ERROR));
            $undo = $result['undo'];
            ModuleManager::registerModule($this->MODULE_ID);
            if (!ModuleManager::isModuleInstalled($this->MODULE_ID)) { throw new RuntimeException('Module registration failed'); }
            $update = require __DIR__ . '/update.php';
            $update(__DIR__);
            if ($snapshot['greeting'] === '__KIT_ABSENT__') { Option::set($this->MODULE_ID, 'greeting', 'Здравствуйте!'); }
            Option::set($this->MODULE_ID, 'files_manifest', json_encode($result['manifest'], JSON_THROW_ON_ERROR));
            return true;
        } catch (Throwable $error) {
            try {
                if (ModuleManager::isModuleInstalled($this->MODULE_ID)) { ModuleManager::unRegisterModule($this->MODULE_ID); }
                if ($db && @@STORAGE@@ && !$tableExisted && $db->isTableExists('@@TABLE@@')) { $db->dropTable('@@TABLE@@'); }
                foreach ($snapshot as $key => $value) {
                    if ($value === '__KIT_ABSENT__') { Option::delete($this->MODULE_ID, ['name' => $key]); }
                    else { Option::set($this->MODULE_ID, $key, $value); }
                }
                if ($undo) { $undo(); }
            } catch (Throwable $rollbackError) { $this->fail($rollbackError); }
            return $this->fail($error);
        }
    }
    public function DoUninstall(): bool
    {
        global $APPLICATION;
        if (!$this->authorized()) { return false; }
        if (($_POST['step'] ?? '') !== '2') {
            $APPLICATION->IncludeAdminFile('Удаление модуля', __DIR__ . '/uninstall.php');
            return true;
        }
        $undo = null;
        try {
            $files = require __DIR__ . '/files.php';
            $result = $files(__DIR__, \Bitrix\Main\Application::getDocumentRoot(), json_decode(Option::get($this->MODULE_ID, 'files_manifest', '{}'), true, 32, JSON_THROW_ON_ERROR), true);
            $undo = $result['undo'];
            if (($_POST['purge'] ?? '') === 'Y') {
                $db = \Bitrix\Main\Application::getConnection();
                if (@@STORAGE@@ && $db->isTableExists('@@TABLE@@')) { $db->dropTable('@@TABLE@@'); }
                Option::delete($this->MODULE_ID);
            }
            \Bitrix\Main\Data\Cache::createInstance()->cleanDir('/@@ID@@/demo');
            ModuleManager::unRegisterModule($this->MODULE_ID);
            if (ModuleManager::isModuleInstalled($this->MODULE_ID)) { throw new RuntimeException('Unregistration failed'); }
            return true;
        } catch (Throwable $error) {
            if ($undo) {
                try { $undo(); } catch (Throwable $rollbackError) { $this->fail($rollbackError); }
            }
            // MySQL DDL is not transactional. On purge failure restore DB from an approved backup.
            return $this->fail($error);
        }
    }
}
