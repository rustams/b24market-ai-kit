---
name: b24-local-delivery
description: "Готовьте local/Git сборку коробочного модуля, согласованные активные копии и проверяемую поставку; не подключайтесь к порталу автоматически."
---

# b24-local-delivery

## Процедура
1. Подтвердите local profile и точный source commit/tag, clean/foreign worktree state и target boundaries. Сборщик читает Git blobs, не cwd files.
2. Для first install: local-full. Для Git delivery с активными компонентами: local-deployment. Не отправляйте GitHub source ZIP как module package.
3. Запустите локальные tests/lint и build, проверьте manifest/SHA256 и повторную byte-identical сборку в новый каталог. Existing published output не заменять.
4. Подготовьте конкретный package и инструкции до согласования deployment. Разрешение на push не разрешает portal install или production.
5. Администратор делает backup собственных копий/БД, сверяет hashes/кастомизации и OS write. Права Bitrix admin не дают права web-user; chmod 777 не решение.
6. Доставьте module+active copies из одного commit на все app-узлы; migration CLI отдельно от DoInstall. FPM/OPcache policy подтверждает владелец, CLI invalidation не обещает очистку FPM.
7. Проверьте hash/version fanout и role/browser сценарий; rollback восстанавливает согласованный комплект, не purge.

## Команды
`python3 scripts/build.py local-full --ref HEAD --output build/first`
`python3 scripts/build.py local-deployment --ref HEAD --output build/deploy`
`php scripts/update-local.php DOCUMENT_ROOT` — только на явно разрешённом стенде под владельцем портала.

## Git и критерии
Перед normal push fetch, diff --check, commit review и remote state; никаких force/merge без поручения. После push проверьте clean status, exact remote SHA и ahead/behind. Shared composer update не включайте в module-only delivery.

Читайте [local delivery](../../../docs/local-delivery.md) и [lifecycle](../../../docs/lifecycle.md), если есть migrations. Production readiness требует native acceptance, не только merge/build.
