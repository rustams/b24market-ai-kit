# Local и Git доставка

Команды внутри сгенерированного проекта:
```sh
python3 scripts/build.py local-full --ref HEAD --output build/first
python3 scripts/build.py local-deployment --ref HEAD --output build/deploy
```
Первая содержит ID/; размещение — `/local/modules/<id>`, затем штатная установка. Вторая имеет document-root layout, только собственные `local/modules/<id>` и `local/components/<vendor>/<module>.demo`. Активная копия генерируется из той же Git tree; не поддерживайте две версии вручную. Никаких симлинков или автоматического SSH.

Поставка не выполняется сборщиком. Администратор по отдельному разрешению:
1. Подтверждает target, все app-узлы, права web-user на принадлежащие модулю каталоги, DB/PHP/main и maintenance window. Вход в Bitrix не даёт OS write; никаких chmod 777.
2. Проверяет manifest и SHA256SUMS, делает backup собственных кода/активных копий и БД вне document root. Сверяет существующие hashes, кастомизации и конфликты.
3. Доставляет согласованный комплект с остановкой при ошибке; не перетирает ACL, public pages, чужие components, core и общий vendor.
4. Запускает `php scripts/update-local.php DOCUMENT_ROOT` под владельцем портала после копирования; reconciliation обновит manifest и применит миграции. Не DoInstall.
5. Применяет согласованную политику PHP-FPM/OPcache на всех app-узлах. CLI opcache_invalidate не гарантирует очистку cache FPM. Подтверждает хеши и версию на каждом узле, затем роли, settings, cache и браузер.

Rollback: восстановить согласованный комплект кода и компонентов того же выпуска, учесть совместимость БД; restore DB требует согласования возможной потери данных после backup. Удаление с purge не является rollback.

Сборка использует только tracked Git blobs указанного commit. Отдельные manifests содержат commit и hashes, ZIP deterministic в одной версии Python/zlib. Выходной каталог должен отсутствовать; опубликованный artifact не заменяется. Между версиями компрессора byte identity не обещается, content hashes остаются сравнимыми.

Пример CI запускает только генерацию и локальные тесты; deployment отсутствует. Production job добавляет владелец контура отдельно с approvals, host verification и проверкой app fanout.
