---
name: b24-server-security
description: "Обеспечивайте authentication, authorization, CSRF, IDOR, безопасный ввод/экспорт/кэш в PHP/D7; применяйте при каждом новом endpoint или хранилище."
---

# b24-server-security

## Перед реализацией
Назовите HTTP/files/Git/upstream trust boundaries и активы. Запишите abuse case: другая identity/owner, GET вместо POST, nonce absent, path traversal, formula export, бесконечный filter key. Сначала тест, затем самый маленький контроль.

## Процедура
1. Authentication и operation permission на сервере до query/cache. Не принимайте user ID/role/scope от клиента как доверенные. CRM source rights не расширяются модульной ролью.
2. Mutation и чувствительный export: POST и CSRF. Для controller сохраните штатные Authentication/Csrf/HttpMethod; пустой prefilters список требует объяснения и отрицательного теста.
3. IDOR устраняйте exact owner/source predicate до чтения. Проверка кнопки или редирект не защищает прямой URL.
4. Нормализуйте ограниченные enums, ID, размеры. Контекстно экранируйте HTML/attribute/JS/URL. Export neutralizes formulas после whitespace; число строк и размер bounded.
5. FS paths имеют собственный prefix, canonical parents, запрет symlink/..; uploads требуют MIME/size/extension, random name и запрет execution. Download разрешает owner до открытия.
6. Exceptions не возвращают message/trace. Серверный журнал вне web root, ACL/rotation, без secrets/request. Audit события не должны дублировать PII.
7. Cache: identity/current rights/scope/effective filters, TTL, invalidation, bounded partitions и cleanup. Отозванные права закрывают уже существующий cache.
8. Composer из lockfile внутри module, license/FQCN review; общий vendor не трогайте. Secret signature scan дополняет ручную проверку history.

## Пример и проверки
Demo fixture повторяет read после отзыва роли и удостоверяется, что cache read counter не растёт. NoteTable чужому owner возвращает null. CSV ` \t=SUM(1)` получает literal prefix. Проверьте ещё negative inputs, XSS render, GET mutation, no nonce и symlink outside tree.

Читайте [безопасность](../../../docs/security.md), а при изменении lifecycle — [жизненный цикл](../../../docs/lifecycle.md). Перед публикацией запускайте импортированный using-marketplace-sec; собственный review не заменяет upstream scan/journal/UI.
