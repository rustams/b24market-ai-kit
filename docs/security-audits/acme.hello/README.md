# Полный security flow acme.hello — 15.10.2026

Проверен сгенерированный marketplace-модуль с опциональным MySQL ORM-хранилищем. Исходный кандидат 0.2.0, исправленный 0.2.1. Порталы, серверы и их данные не использовались. Применены оригинальные импортированные scan, journal-review, journal-update и using-marketplace-sec на зафиксированном upstream SHA.

## Результат

Финальный security-journal: `ready_for_submission`. Open 0, blocking 0, false positive 0, accepted risk 0, fixed 2, total historical 2. Два запуска сохранены скриптом без ручного редактирования lifecycle. Финальный raw scan содержит 0 findings после повторного прочтения кода.

| Проблема | Доказательство | Исправление |
|---|---|---|
| Прямой запуск installer без пролога при display_errors=On раскрывает путь/stack | Изолированный PHP direct execution; low Information Disclosure. HTTP доступность такого URL на Bitrix не подтверждена | Guard B_PROLOG_INCLUDED до классов платформы |
| Прямой запуск ORM-класса без пролога раскрывает путь/stack | Такой же локальный reproducer; отдельная low finding согласно upstream per-file contract | Guard до наследования DataManager |
| CSV sanitizer пропускает =1+1 с байтом FF | PHP regression failed до фикса; PCRE /u возвращает false | Только строгий результат 0 допускает исходный текст, false также экранируется |
| Команды review/journal не работают на Python 3.9 | zip(strict=True) падает; --help этого не обнаруживает | Документирован Python 3.10+ для security flow; полный запуск и CI test на 3.12 |

CSV проблема записана отдельно от vulnerability enum: upstream не имеет CSV Injection, а PHP RCE, выполнение формулы в Excel и сохранение этих байтов настоящим Bitrix не доказаны. Она не была ошибочно названа XSS/RCE и не скрыта: добавлены воспроизводитель, исправление и постоянный regression. Две low findings относятся к условной конфигурации HTTP/display_errors; нельзя выдавать CLI fixture за подтверждение эксплуатации портала.

## Полнота

Recon выполнен один раз до и один раз после исправления; 21 target (20 PHP и LICENSE), дополнительно description.ru. Все файлы прочитаны целиком. Для всех входных точек проверены authentication/authorization, CSRF, HTML/attribute encoding, SQL/ORM, execution/includes, serialization, paths/uploads/download, SSRF/TLS, owner/mass assignment, SSTI/XML, redirect/header и error disclosure. Внешние sink classes доведены до собственных callers; platform implementation не подменялся предположением о native UAT.

Регулярка manifest — фиксированный literal, include source — trusted module/update payload; HTTP не задаёт путь или имя SQL table. Не найдено доступных upload, network, XML/template interpreter, financial или deserialize sinks. Права проверяются до options/cache/owner query, источники CRM отсутствуют. Privileged deployment races, OS ACL/log config и cleanup cache остаются требованиями эксплуатации; они не объявлены устранёнными программной проверкой.

## Артефакты и UI

Full ZIP `last_version.zip` соответствует full.manifest.json и SHA256 `305063f2591bc9f64d634bfeab476e2161d238b82ae7f5321863d5cbe077ba96`. Именно он привязан к текущему журналу. Update `0.2.1.zip` от точного 0.2.0 base соответствует update.manifest.json и SHA256 `2fdd38b14c580bce5f792a40df7a8b62bc2f8fd888af3c78691cc90b74b6b729`. Это локальные кандидаты, не опубликованные Marketplace releases. Generated Git commits относятся к disposable проекту; ZIP bytes и manifest включены для независимой проверки без этих Git объектов.

UI открыт в браузере на http://127.0.0.1:8765/. Проверены график двух запусков, top counters 0/0/0/0, total 2 и отсутствие активных карточек. /api/data совпадает с current_state, findings=[]; ответ сохранён в ui-api.json. Developer feedback/dispositions не создавались: ни accepted risk, ни false positive не использованы для закрытия findings.

## Повторение tooling

Из корня starter-kit, Python 3.10+:
```sh
python3 external/marketplace-security/skills/bitrix-security/journal-review/scripts/review_journal.py --validate-only docs/security-audits/acme.hello/after.reviewed.json
python3 external/marketplace-security/skills/bitrix-security/journal-update/scripts/update_journal.py --validate-only docs/security-audits/acme.hello/security-journal.json
python3 external/marketplace-security/tools/journal_ui_server.py --reviewed docs/security-audits/acme.hello/after.reviewed.json --journal docs/security-audits/acme.hello/security-journal.json --host 127.0.0.1 --port 8765
KIT_PHP_IMAGE=php:8.2-cli python3 -m unittest discover -s tests -v
```
Если UI уже работает, не запускайте второй экземпляр на том же порту. Чтобы воспроизвести аудит нового изменённого модуля, сначала снова выполните scan по его собственному SKILL; validation старого JSON не заменяет новый аудит.

После исправления: 13 групп тестов passed на Python 3.12.14/PHP 8.2.33, 284 PHP behavioral assertions по двум профилям с/без storage. Direct-file regressions и реальное выполнение upstream review/journal/lifecycle validation включены в suite. На Python 3.9 upstream integration test явно skipped, остальные core checks поддерживаются.

Открыты native Bitrix install/upgrade/ORM, реальные HTTP routes/display_errors/log ACL, Excel, concurrency/cluster/FPM и партнёрский кабинет. `ready_for_submission` — итог этого локального security journal, не модерация и не production-готовность.

Точная историческая полная база 0.2.0 доступна в [baseline-0.2.0/last_version.zip](baseline-0.2.0/last_version.zip), с манифестом и SHA256SUMS. SHA256 4f489f79912fe091b04a16b5e2ad5998bdfa469f0cc34e46b0b7144f469daa3e. Проверены все Git blobs базы и наложение delta до полного 0.2.1; она содержит прежние low findings и предназначена только для изолированной приёмки обновления.
