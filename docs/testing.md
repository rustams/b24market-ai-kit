# Проверка и диагностика

Starter-kit: `python3 -m unittest discover -s tests -v`. Сгенерированный проект: `python3 scripts/verify.py` или `python3 scripts/verify.py --docker-image php:8.2-cli`.

Docker mount включает только новый проект read-only, сеть отключена, filesystem read-only, временный root fixtures находится в /tmp контейнера. Ни портал, ни Bitrix код/лицензия не нужны. Тесты проверяют реальную файловую операцию и реальные PHP сервисы; API платформы подменены. Это локальное свидетельство поведения, а не native acceptance.

Генератор проверяется для обоих профилей, storage по выбору, malicious ID/namespace/version/paths/text, существующего output и symlinks. Тесты сборок инициализируют независимый Git, сравнивают manifest с ZIP и повторные byte-identical archives, проверяют working-tree exclusion, delta/base version, удалённые файлы и запрет overwrite.

Жизненный цикл: auth/CSRF → fresh install → use → pre-copy updater/repeat → keep-data uninstall → reinstall → missing table + stale options → purge. Failure injections покрывают регистрацию/DDL, rollback собственного кода и сохранение соседнего файла. Миграции с несколькими шагами отдельно проверяют порядок и повтор.

На licensed стенде по разрешению пользователя выполните матрицу acceptance: native installation, support versions, actual DB/FQCN, role matrix/direct URL, settings, XSS, effective filters, log ACL, browser Network, export, update sequence, uninstall/default/purge, cluster and FPM. Снимок одного узла не доказывает кластер. JS в базовом runtime нет, поэтому JS lint не требуется; при добавлении выполняется node --check, затем browser tests.

Диагностика: сначала точный trigger/user/version/request и ненулевой exit/ошибка. Проверьте разрешение, CSRF, PHP logs и dependencies; при generic DB ошибке сначала соединение/доступность сервиса, затем код. Не выводите exception text в UI, не печатайте env и auth headers. Сделайте минимальный reproducer до исправления; новый test должен ломаться на старом поведении. Не меняйте данные/права ради воспроизведения без разрешения.
