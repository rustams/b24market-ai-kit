<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) { die(); }

class @@CLASS@@DemoComponent extends CBitrixComponent
{
    public function executeComponent()
    {
        if (!\Bitrix\Main\Loader::includeModule('@@ID@@')) { ShowError('Модуль недоступен.'); return; }
        try {
            $this->arResult = \@@NS@@\Demo::read($_GET['filter'] ?? null);
            $this->includeComponentTemplate();
        } catch (\DomainException $error) {
            ShowError('Доступ запрещён или некорректный запрос.');
        } catch (\Throwable $error) {
            $id = \@@NS@@\Diagnostics::report($error);
            ShowError('Не удалось загрузить данные. Код обращения: ' . $id);
        }
    }
}
