<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) { die(); }
?>
<form method="post">
    <?= bitrix_sessid_post() ?>
    <input type="hidden" name="id" value="@@ID@@">
    <input type="hidden" name="uninstall" value="Y">
    <input type="hidden" name="step" value="2">
    <input type="hidden" name="lang" value="<?= htmlspecialcharsbx(LANGUAGE_ID) ?>">
    <p>По умолчанию пользовательские данные и настройки сохраняются.</p>
    <label><input type="checkbox" name="purge" value="Y">Удалить все данные и настройки этого модуля</label>
    <button name="inst" type="submit">Удалить модуль</button>
</form>
