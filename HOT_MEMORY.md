# Актуальное состояние — 15.10.2026

Starter-kit реализован самостоятельно в новом проекте. Есть offline генератор local/marketplace, общий PHP/D7 исходник, защищённый компонент, настройки, отдельное обновление, default keep-data uninstall и explicit purge. Storage MySQL/D7 ORM включается только флагом, CRM/агенты/очереди отсутствуют.

Доставка: local-full, local-deployment с согласованными активными копиями, marketplace-full .last_version и marketplace-update с точным base commit/version. Git blobs, manifest, SHA256, deterministic ZIP и отказ overwrite. Удаление старых файлов fail-closed до отдельной документированной migration.

Восемь русскоязычных переносимых скиллов прошли skill-creator validation. Original bitrix-tools security skills MIT включены на commit 0cdb4e3fee90b825a0088870712d1dce0f6683f5; обновление из upstream на проверенный SHA успешно проверено. Генератор копирует их автоматически, silent remote update отсутствует.

Локальная suite: 11 групп тестов; оба профиля с/без storage, PHP 8.2 из disposable Docker network none, auth/CSRF/cache/owner/XSS/export, files rollback/registration/DDL, ordered/repeated migrations, stale cursor with absent table, keep/reinstall/purge, symlink/neighbor, archive reproducibility/delta/base/secrets. Последняя актуальная проверка фиксируется в docs/verification.md.

Исторический Radar прочитан без записей и подключений; код/тексты не копировались, project license не обнаружен. Official references прочитаны, реестр включает ограничения. Production и Marketplace не менялись.

Открыты native Loader/ORM/install/update/OS permissions/browser/roles/logs/DB concurrency/cluster/FPM, полный security scan/journal/UI для финального модуля, кабинет/модерация. Локальные API stubs не закрывают native acceptance.

Проверено сохранение буквального значения, совпадавшего с прежним техническим sentinel: до фикса regression failed, после использования Option::getRealValue passed. Итог: 11 групп/280 PHP assertions, по 76 assertions в двух независимых проектах.
