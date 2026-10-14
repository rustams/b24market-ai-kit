# Marketplace упаковка и подготовка

Сначала прочитайте записи vendor в sources.json. Полная сборка и обновление — разные артефакты. Используется штатная установка/обновление через партнёрскую систему; starter-kit не публикует и не перемещает установленный модуль.

```sh
python3 scripts/build.py marketplace-full --ref HEAD --output build/full
python3 scripts/build.py marketplace-update --ref HEAD --base-ref PREVIOUS_COMMIT --previous-version 0.1.0 --output build/update
```
Полная сборка содержит `.last_version/`. Документация одновременно показывает имя `.last_version.zip` и исключение для Mac без точки в имени. Проект выбрал `last_version.zip`, сохранив точку внутреннего каталога; acceptance текущего кабинета остаётся открытым. Версия ненулевая, version.php использует двойные кавычки. ID консервативный lowercase vendor.module, без underscores; vendor prefix должен подтвердить партнёр.

Обновление содержит каталог новой версии, изменённые файлы относительно точного base commit и необходимые service-файлы: version, description.ru, updater и его автономные support/migrations/полное дерево активного компонента. Последнее нужно для проверки manifest и полной согласованной копии до установки кода. Base profile/ID/version/ancestor проверяются. Не публикуйте delta из неполного неизвестного предыдущего выпуска.

Updater запускается до копирования кода; он не вызывает новые классы этого модуля и учитывает отсутствие регистрации и повторный запуск. Служебный errorMessage сообщает об остановке без деталей. Чистое копирование в каталог модуля выполняет штатный клиент. Кластер, updater failures и активные компоненты требуют нативной проверки.

Удаление старых файлов автоматически запрещено. Публичная страница об обновлении не была принята за доказательство произвольного DeleteFiles API. Если файл нужно убрать, подтвердите документированный механизм на целевой версии и добавьте отдельную reviewed миграцию с owner/path/hash проверкой и тестом сохранения чужих файлов. Не выдумывайте метод и не доставляйте опасный delete-list.

## Обязательный security flow

Требуется Python 3.10+; generator/core tests совместимы с 3.9, но upstream review использует zip(strict=True). Проверьте `python3 --version`.

Откройте `external/marketplace-security/skills/bitrix-security/using-marketplace-sec/SKILL.md`. Весь upstream импорт MIT с оригинальными references и скриптами, версии в lock JSON. Генератор копирует его без сети. Обновление — `python3 scripts/update-security-skills.py --ref FULL_REVIEWED_SHA`, после чтения нового upstream и проверки diff. Silent auto-update отключён: это изменение исполняемых инструментов и агентных инструкций.

1. Агент выполняет skill scan для `module`, читает recon и каждый достижимый sink, сохраняет доказательный raw JSON. Recon сам по себе не аудит.
2. BUS-aware review:
```sh
python3 external/marketplace-security/skills/bitrix-security/journal-review/scripts/review_journal.py reports/module.json --output reports/module.reviewed.json
python3 external/marketplace-security/skills/bitrix-security/journal-update/scripts/update_journal.py reports/module.reviewed.json --module-path module --output reports/security-journal.json
python3 external/marketplace-security/skills/bitrix-security/journal-update/scripts/update_journal.py --validate-only reports/security-journal.json
python3 external/marketplace-security/tools/journal_ui_server.py --reviewed reports/module.reviewed.json --journal reports/security-journal.json --host 127.0.0.1 --port 8765
```
При повторе добавьте --previous и SHA256 финального архива по upstream инструкции. Проверьте UI/API текущего журнала и dispositions. Нельзя объявлять flow завершённым без UI. `ready_for_submission` относится только к findings, не к вендорской модерации, лицензии или production.

Перед публикацией отдельно подтвердите PARTNER metadata, название карточки/установщика, prefix, версии продукта, лицензионные условия, demo support если используется, лимиты загрузки, языки/кодировку, чистую установку, последовательные обновления, полный purge и роли. Кабинет и модерация не проверены. CI ничего не загружает в Marketplace.
