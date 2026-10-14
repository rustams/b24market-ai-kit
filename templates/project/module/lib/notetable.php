<?php
namespace @@NS@@;
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) { return; }
use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Fields\IntegerField;
use Bitrix\Main\ORM\Fields\StringField;

final class NoteTable extends DataManager
{
    public static function getTableName(): string { return '@@TABLE@@'; }
    public static function getMap(): array
    {
        return [new IntegerField('ID', ['primary' => true, 'autocomplete' => true]),
            new IntegerField('OWNER_ID', ['required' => true]), new StringField('TEXT', ['required' => true])];
    }
    public static function readOwn(int $id): ?array
    {
        global $USER;
        Access::requirePermission('view');
        if ($id <= 0) { throw new \DomainException('Некорректный ID.'); }
        // Exact owner condition belongs in the query, before any data or cache read.
        $row = static::getList(['select' => ['ID', 'TEXT'], 'filter' => ['=ID' => $id, '=OWNER_ID' => (int)$USER->GetID()], 'limit' => 1])->fetch();
        return $row ?: null;
    }
}
