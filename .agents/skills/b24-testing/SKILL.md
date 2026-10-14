---
name: b24-testing
description: "Тестируйте генератор и PHP/D7 поведение, воспроизводите ошибки, отделяйте mocks от нативной приёмки Bitrix."
---

# b24-testing

## Процедура
1. Найдите минимальный trigger, identity/role, версии, входные значения и ожидаемый результат. Не чините портал по generic error без диагностики.
2. Запустите ближайший behavioral test до изменения. Bug reproducer должен ломаться на прежнем поведении; тест, который сравнивает только текст реализации, не доказывает исправление.
3. Сгенерированный проект: `python3 scripts/verify.py`; без host PHP используйте --docker-image php:8.2-cli. Starter-kit: unittest discover. Контейнер network none, только собственный read-only mount и disposable tmp.
4. Проверяйте auth/CSRF до options/cache, owner isolation, effective filter, XSS/CSV, retained rows, missing table+stale cursor, rollback реальных файлов и untouched neighbor.
5. Сборки сравнивайте по ZIP contents, per-file hash, source/base commits и byte identity в одной toolchain. Working-tree change не должен попадать в archive. Invalid profile/base/delete/overwrite обязаны остановиться.
6. PHP lint всего runtime; JS lint только при наличии JS. UI/browser, DB concurrency, native loader/updater/cabinet, FPM cluster и UAT записываются отдельными открытыми проверками.
7. При failure читайте private logs без env/headers, диагностируйте service availability до изменения SQL/module. Не меняйте rights/source data ради воспроизведения без разрешения.

## Пример
Незарегистрированный updater не пишет в Option/FS. Registration failure после component copy возвращает false и восстанавливает старые файлы. Cache filled by admin недоступен после revoke role.

## Выход
Команда, версия среды, число passed behavior checks, подтверждённые пределы mocks, exact next native test. Не приравнивайте PHP fixture, HTTP 200 или скриншот к пользовательской приёмке.

Читайте [тестирование](../../../docs/testing.md), relevant contract doc и [матрицу приёмки](../../../docs/templates/acceptance.md), без чтения всех знаний сразу.
