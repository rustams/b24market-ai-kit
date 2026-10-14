<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) { die(); }
\Bitrix\Main\Localization\Loc::loadMessages(__FILE__);
?>
<form method="get">
    <label for="@@CLASS@@-filter"><?= htmlspecialcharsbx(\Bitrix\Main\Localization\Loc::getMessage('@@LOC@@_FILTER')) ?></label>
    <select id="@@CLASS@@-filter" name="filter">
        <option value="welcome" <?= $arResult['filter'] === 'welcome' ? 'selected' : '' ?>><?= htmlspecialcharsbx(\Bitrix\Main\Localization\Loc::getMessage('@@LOC@@_WELCOME')) ?></option>
        <option value="status" <?= $arResult['filter'] === 'status' ? 'selected' : '' ?>><?= htmlspecialcharsbx(\Bitrix\Main\Localization\Loc::getMessage('@@LOC@@_STATUS')) ?></option>
    </select>
    <button type="submit"><?= htmlspecialcharsbx(\Bitrix\Main\Localization\Loc::getMessage('@@LOC@@_SHOW')) ?></button>
</form>
<p><?= htmlspecialcharsbx($arResult['text']) ?></p>
