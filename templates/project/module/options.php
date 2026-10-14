<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) { return; }
if (!\Bitrix\Main\Loader::includeModule('@@ID@@')) { return; }
use @@NS@@\Access;
use @@NS@@\Demo;
use @@NS@@\Diagnostics;
use Bitrix\Main\Localization\Loc;
Loc::loadMessages(__FILE__);
try {
    Access::requirePermission('settings');
    if ($_SERVER['REQUEST_METHOD'] === 'POST') { Demo::save($_POST['greeting'] ?? null); }
    $data = Demo::read();
} catch (\DomainException $error) { ShowError(Loc::getMessage('@@LOC@@_DENIED')); return; }
catch (\Throwable $error) { Diagnostics::report($error); ShowError(Loc::getMessage('@@LOC@@_FAILED')); return; }
?>
<form method="post">
    <?= bitrix_sessid_post() ?>
    <label for="@@CLASS@@-greeting"><?= htmlspecialcharsbx(Loc::getMessage('@@LOC@@_GREETING')) ?></label>
    <input id="@@CLASS@@-greeting" name="greeting" maxlength="500" value="<?= htmlspecialcharsbx($data['text']) ?>">
    <button type="submit"><?= htmlspecialcharsbx(Loc::getMessage('@@LOC@@_SAVE')) ?></button>
</form>
