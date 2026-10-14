<?php
namespace @@NS@@;

final class Access
{
    public static function requirePermission(string $operation, bool $mutation = false): void
    {
        global $USER, $APPLICATION;
        $levels = ['D' => 0, 'R' => 1, 'E' => 2, 'W' => 3, 'X' => 4];
        $required = ['view' => 1, 'export' => 2, 'edit' => 3, 'settings' => 4];
        if (!$USER || !$USER->IsAuthorized() || !isset($required[$operation]) ||
            (!$USER->IsAdmin() && ($levels[$APPLICATION->GetGroupRight('@@ID@@')] ?? 0) < $required[$operation])) {
            throw new \DomainException('Доступ запрещён.');
        }
        if ($mutation && ($_SERVER['REQUEST_METHOD'] !== 'POST' || !check_bitrix_sessid())) {
            throw new \DomainException('Запрос не подтверждён.');
        }
    }

    public static function owner(int $ownerId): void
    {
        global $USER;
        self::requirePermission('view');
        // Administrators do not implicitly own another user's private object.
        if ($ownerId !== (int)$USER->GetID()) { throw new \DomainException('Доступ запрещён.'); }
    }
}
