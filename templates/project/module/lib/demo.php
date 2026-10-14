<?php
namespace @@NS@@;
use Bitrix\Main\Config\Option;
use Bitrix\Main\Data\Cache;

final class Demo
{
    public static function filter($value): string
    {
        if ($value === null || $value === '') { return 'welcome'; }
        if (!is_string($value) || !in_array($value, ['welcome', 'status'], true)) {
            throw new \DomainException('Некорректный фильтр.');
        }
        return $value;
    }

    public static function read($filter = null): array
    {
        global $USER, $APPLICATION;
        Access::requirePermission('view');
        $effective = self::filter($filter);
        // Two fixed filters per user; raw URL values never allocate cache entries.
        $key = hash('sha256', json_encode([(int)$USER->GetID(), $APPLICATION->GetGroupRight('@@ID@@'), $effective, defined('SITE_ID') ? SITE_ID : '', defined('LANGUAGE_ID') ? LANGUAGE_ID : 'ru']));
        $cache = Cache::createInstance();
        if ($cache->initCache(60, $key, '/@@ID@@/demo')) { return $cache->getVars(); }
        $result = ['filter' => $effective, 'text' => $effective === 'welcome' ? Option::get('@@ID@@', 'greeting', 'Здравствуйте!') : 'Модуль установлен.'];
        if ($cache->startDataCache()) { $cache->endDataCache($result); }
        return $result;
    }

    public static function save($text): void
    {
        Access::requirePermission('settings', true);
        if (!is_string($text) || trim($text) === '' || strlen($text) > 500 || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $text)) {
            throw new \DomainException('Введите текст длиной до 500 байт.');
        }
        Option::set('@@ID@@', 'greeting', $text);
        Cache::createInstance()->cleanDir('/@@ID@@/demo');
    }

    public static function csvCell(string $text): string
    {
        // Excel may interpret formulas after leading whitespace or control characters.
        return preg_match('/^[\s\x00-\x1F]*[=+@-]/u', $text) ? "'" . $text : $text;
    }

    public static function export($filter = null): string
    {
        Access::requirePermission('export', true);
        $data = self::read($filter);
        $stream = fopen('php://temp', 'w+');
        if ($stream === false) { throw new \RuntimeException('Export stream unavailable'); }
        try {
            if (fputcsv($stream, [self::csvCell($data['text'])], ';', '"', '') === false) {
                throw new \RuntimeException('Export write failed');
            }
            rewind($stream);
            $result = stream_get_contents($stream);
            if ($result === false) { throw new \RuntimeException('Export read failed'); }
            return $result;
        } finally { fclose($stream); }
    }
}
