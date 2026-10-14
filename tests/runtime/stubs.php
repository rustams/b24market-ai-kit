<?php
namespace Bitrix\Main {
    final class Application {
        public static string $root;
        public static ?Connection $db = null;
        public static function getDocumentRoot(): string { return self::$root; }
        public static function getConnection(): Connection { return self::$db ??= new Connection(); }
    }
    final class Connection {
        public array $tables = [];
        public bool $failCreate = false;
        public function getType(): string { return 'mysql'; }
        public function isTableExists(string $table): bool { return isset($this->tables[$table]); }
        public function queryExecute(string $sql): void {
            if ($this->failCreate) { throw new \RuntimeException('PRIVATE SQL /server/path'); }
            if (!preg_match('/^CREATE TABLE ([a-z0-9_]+)/', $sql, $m)) { throw new \RuntimeException('Unsupported fixture query'); }
            $this->tables[$m[1]] = [];
        }
        public function dropTable(string $table): void { unset($this->tables[$table]); }
    }
    final class ModuleManager {
        public static array $installed = [];
        public static bool $failRegister = false;
        public static string $mainVersion = '25.0.0';
        public static function isModuleInstalled(string $id): bool { return isset(self::$installed[$id]); }
        public static function getVersion(string $id): string { return self::$mainVersion; }
        public static function registerModule(string $id): void {
            if (self::$failRegister) { throw new \RuntimeException('PRIVATE register error'); }
            self::$installed[$id] = true;
        }
        public static function unRegisterModule(string $id): void { unset(self::$installed[$id]); }
    }
    final class Loader { public static function registerNamespace(...$args): void {} }
}
namespace Bitrix\Main\Config {
    final class Option {
        public static array $values = [];
        public static int $reads = 0;
        public static function get(string $id, string $key, string $default = ''): string { self::$reads++; return self::$values[$id][$key] ?? $default; }
        public static function set(string $id, string $key, string $value): void { self::$values[$id][$key] = $value; }
        public static function delete(string $id, array $filter = []): void {
            if (isset($filter['name'])) { unset(self::$values[$id][$filter['name']]); }
            else { unset(self::$values[$id]); }
        }
    }
}
namespace Bitrix\Main\Localization {
    final class Loc {
        public static function loadMessages(string $file): void {}
        public static function getMessage(string $key): string { return str_ends_with($key, '_INSTALL_ERROR') ? 'Операция остановлена.' : $key; }
    }
}
namespace Bitrix\Main\Data {
    final class Cache {
        public static array $values = [];
        public static int $reads = 0;
        public static array $ttls = [];
        private string $key;
        public static function createInstance(): self { return new self(); }
        public function initCache(int $ttl, string $key, string $path): bool {
            self::$reads++; self::$ttls[] = $ttl; $this->key = $path . '/' . $key;
            return isset(self::$values[$this->key]);
        }
        public function getVars(): array { return self::$values[$this->key]; }
        public function startDataCache(): bool { return true; }
        public function endDataCache(array $data): void { self::$values[$this->key] = $data; }
        public function cleanDir(string $path): void {
            foreach (array_keys(self::$values) as $key) { if (str_starts_with($key, $path . '/')) { unset(self::$values[$key]); } }
        }
    }
}
namespace Bitrix\Main\ORM\Data {
    class DataManager {
        public static array $rows = [];
        public static function getList(array $params): object {
            $row = self::$rows[$params['filter']['=ID']] ?? null;
            if ($row && $row['OWNER_ID'] !== $params['filter']['=OWNER_ID']) { $row = null; }
            return new class($row) { private $row; public function __construct($row) { $this->row = $row; } public function fetch() { return $this->row; } };
        }
    }
}
namespace {
    class CModule {}
    final class FixtureUser {
        public bool $authorized = true;
        public bool $admin = true;
        public int $id = 10;
        public function IsAuthorized(): bool { return $this->authorized; }
        public function IsAdmin(): bool { return $this->admin; }
        public function GetID(): int { return $this->id; }
    }
    final class FixtureApplication {
        public string $right = 'X';
        public array $errors = [];
        public function GetGroupRight(string $module): string { return $this->right; }
        public function ThrowException(string $error): void { $this->errors[] = $error; }
        public function IncludeAdminFile(...$args): void {}
    }
    function check_bitrix_sessid(): bool { return $GLOBALS['csrf']; }
    function htmlspecialcharsbx(string $text): string { return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
}
