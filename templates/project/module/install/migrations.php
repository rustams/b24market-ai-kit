<?php
// Append ordered, idempotent migrations; never change a released migration.
return [
    1 => static function (): void {
        if (@@STORAGE@@) {
            $db = \Bitrix\Main\Application::getConnection();
            if ($db->getType() !== 'mysql') { throw new RuntimeException('Optional storage supports MySQL only'); }
            if (!$db->isTableExists('@@TABLE@@')) {
                $db->queryExecute('CREATE TABLE @@TABLE@@ (ID INT NOT NULL AUTO_INCREMENT, OWNER_ID INT NOT NULL, TEXT VARCHAR(500) NOT NULL, PRIMARY KEY(ID), INDEX IX_OWNER (OWNER_ID))');
                // Reset technical progress only when the corresponding storage did not exist.
                \Bitrix\Main\Config\Option::set('@@ID@@', 'storage_cursor', '0');
            }
        }
    },
];
