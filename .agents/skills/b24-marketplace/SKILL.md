---
name: b24-marketplace
description: "Готовьте полную Marketplace сборку и обновление PHP/D7 модуля из точной предыдущей версии; не облачное REST приложение и не автоматическая публикация."
---

# b24-marketplace

## Процедура
1. Сверьте актуальные official уроки полной сборки/обновлений и текущие ограничения кабинета. Prefix/тип/metadata/version/name/card/license подтверждает владелец партнёрского аккаунта.
2. marketplace-full создаёт .last_version layout для первой установки. marketplace-update требует source/base commits и --previous-version, проверяет ancestor, ID/profile/version и рост версии.
3. Delta содержит изменённые files и необходимые autonomous service files для pre-copy updater. Не вызывайте новый class API в updater, учитывайте повтор и отсутствие регистрации. Полная сборка не заменяет обновление.
4. File deletion блокируется до документированного и проверенного owner-safe механизма; не выдумывайте DeleteFiles метод. Не перемещайте установленный модуль, не используйте local symlink.
5. Проверьте ZIP/manifest/SHA/rebuild, metadata двойные кавычки version.php, exclude secrets/tests/macOS trash, immutable outputs. Нативно проверьте штатную clean install и цепочку версий.
6. Обязателен импортированный using-marketplace-sec: scan с реальным аудитом → journal-review → journal-update → validation → journal UI и сверка current_state/dispositions. Bind journal к SHA финального archive. Recon без audit не считается проверкой.
7. Подготовьте release evidence и open native/cabinet gates. Публикация/модерация/production требуют отдельного поручения; CI не загружает пакет.

## Пример
Из опубликованного 0.1.0 commit A в 0.2.0 commit B: сравните дерево, сохраните options/rows, включите support closures и component payload, протестируйте repeated updater до копирования нового module code. Archive 0.2.0.zip имеет root 0.2.0/, full ZIP — .last_version/.

## Источники и импорт
Оригинальные MIT skills переносятся генератором автоматически. Update command принимает reviewed full upstream SHA; проверьте diff/scripts/license до использования. Silent refresh во время generation отсутствует и сеть не нужна.

Читайте [Marketplace](../../../docs/marketplace.md) и [sources](../../../docs/sources.json). Полные security инструкции находятся `external/marketplace-security/skills/bitrix-security/using-marketplace-sec/SKILL.md`; сохранены оригинальные references, license и upstream lock.
