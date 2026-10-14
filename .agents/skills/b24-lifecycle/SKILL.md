---
name: b24-lifecycle
description: "Изменяйте установку, миграции, обновление и удаление коробочного модуля; применяйте при DB/files/events/agents или новой версии."
---

# b24-lifecycle

## Карта собственности
До кода перечислите собственные таблицы, Option keys, активные файлы, страницы, events и agents; у каждого определите keep/purge/reinstall. Отсутствие таблиц не означает отсутствие options. Никакого blanket cleanup в /local или /bitrix.

## Процедура
1. Выберите профиль и официальный installer/update contract. DoInstall применяется к установке, не обновлению; класс наследует CModule, metadata/version локализуются.
2. Admin+POST+CSRF, dependency/DB/OS write/path/symlink/conflict preflight до изменений. Установка не меняет общий menu/page/ACL. Ошибка регистрации после копии требует rollback.
3. Пишите миграции как нумерованные idempotent operations, marker после успеха, сохранение пользовательских строк. Выпущенные operations не переписывайте. DDL MySQL не объявляйте транзакционным.
4. При fresh/reinstall определяйте наличие соответствующего storage до миграции; absent table + retained cursor означает сброс лишь этого технического cursor.
5. Default uninstall сохраняет данные/options, purge требует явного POST выбора. Остановите собственные handlers/agents в обоих случаях. Hash mismatch активных файлов означает конфликт, не разрешение удалить.
6. Marketplace updater выполняется до новых classes. Используйте автономные support closures из payload, учитывайте незарегистрированный модуль и повтор. Local delivery обновляет код и активные копии одного commit; отдельный CLI согласует manifest/migrations.
7. Поведение partial failure записывается: компенсация exceptions, где требуется DB restore, предел crash/concurrency/cluster атомарности.

## Пример
Добавьте migration 2 для новой настройки без замены greeting; fixture применяет 1→2, повторяет update и проверяет сохранение строки. После manual table drop с retained schema/cursor reinstall восстанавливает storage и cursor=0.

## Проверка
Fresh→use→update→keep uninstall→reinstall→purge; отдельно missing table/retained options, copy/registration/DDL failure, unknown files, symlinks и повтор updater. Затем licensed native cycle, backup restore и target-version errors отдельно.

Читайте [lifecycle](../../../docs/lifecycle.md) и [acceptance template](../../../docs/templates/acceptance.md); при упаковке соответствующий delivery skill. Не переносите исторические experiments в универсальные правила.
