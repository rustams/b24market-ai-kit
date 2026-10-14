# Выполненные проверки — 15.10.2026

Команда: `KIT_PHP_IMAGE=php:8.2-cli python3 -m unittest discover -s tests -v`.
Результат: 11 групп тестов passed, в том числе 280 PHP assertions поведения для local/marketplace с/без storage. Python 3.9.6, PHP 8.2.33, Docker с network none, проект read-only, disposable /tmp. PHP lint модулей и собственного CLI updater выполнен. Runtime JS нет, JS lint не требуется.

Два независимых финальных проекта с optional storage дополнительно прошли verify по 76 assertions. Собственные восемь SKILL.md прошли официальный skill-creator validator; relative references проверены. Импорт MIT зафиксирован hashes; original upstream update на тот же SHA проверен, CLI review/journal/UI запускаются. Отказ обновления при локальных изменениях проверен без сети; diff/lock не изменяются. Это smoke-проверка инструментов, не полный security audit.

Созданы четыре вида пакетов: local-full, local-deployment, marketplace-full и marketplace-update 0.1.0→0.2.0 от точного commit. Полные ZIP и update byte-identical повторно в одной toolchain; SHA256/manifest/Git blobs совпадают. Deployment module и активные components согласованы. Запрет неправильного profile/base/version, overwrite, удалённых файлов, tracked secrets и symlinks проверен. Локальные disposable artifact hashes сохранены в verification-artifacts.json; это свидетельство прогона, не опубликованные release-пакеты.

Auth/CSRF/GET rejection до reads/writes, operation permissions, revoke before cache, IDOR owner predicate, XSS rendering, CSV formulas, effective filter, TTL/invalidation и bounded filter keys проверены. Lifecycle failure injections: registration/DDL/read-only destination/customization/symlink; откат реальных файлов; ordered migrations даже с обратным порядком объявления, repeated updater, data preservation, default keep/reinstall, missing table+stale cursor, explicit purge и неизменность соседнего файла.

Работа выполнена только в новом workspace, кроме разрешённого чтения исторических материалов/скиллов и official/upstream/GitHub запросов. Серверы/секреты Radar не использовались, код и настройки существующих порталов не менялись. Git diff --check прошёл перед коммитами.

## Незавершённая приёмка

Лицензированного Bitrix стенда не было. Stubs не подтверждают native Loader/ORM/installer/updater, actual OS ACL/DB errors/transactions, user roles, browser/Network, logs ACL, concurrency, cluster и FPM OPcache. Полный импортированный scan→review→journal→validate→UI на финальном модуле остаётся обязательным перед публикацией; пустой успешный отчёт не создавался.

Current partner cabinet, macOS full ZIP filename acceptance, licensing/demo requirements, native sequential upgrades и moderation не проверены. Optional storage заявлен только MySQL, deletion fail-closed, compensation не покрывает crash/concurrent deployment/общую атомарность SiteUpdate. Production и Marketplace не изменялись. Нет заявления production-готовности.

Дополнительный regression: строка `__KIT_ABSENT__` терялась при reinstall из-за sentinel default. До исправления meaningful test failed; snapshot теперь использует официальный Option::getRealValue с null для отсутствия, буквальный пользовательский текст сохраняется. После исправления вся suite и две независимые генерации прошли, архивы пересобраны в новые каталоги; прежние локальные кандидаты не заменялись.
