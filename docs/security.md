# Серверная безопасность и практические проверки

Границы доверия: HTTP → сервис → Option/ORM/Cache; администратор → lifecycle → собственные FS/DB объекты; Git blobs → ZIP; upstream skills → локальные инструкции. Активы: данные пользователей, настройки, код портала, права и secrets. Клиентская скрытая кнопка не является контролем доступа.

## Практические контракты

- Authentication: Access проверяет IsAuthorized, затем разрешение операции. Уровни D/R/E/W/X явно отображаются в числа; неизвестный уровень закрыт. Просмотр, экспорт, изменение и настройки проверяются отдельно.
- Mutation: POST и check_bitrix_sessid до Option::set или удаления. AJAX controller должен использовать штатные Authentication/HttpMethod/Csrf filters и такой же серверный domain policy.
- IDOR: NoteTable::readOwn ограничивает ORM запрос точным ID и текущим OWNER_ID; отсутствие чужой записи возвращает null. Не читайте запись для последующей клиентской проверки.
- XSS: пользовательское приветствие хранится как текст; HTML template и attribute выводят htmlspecialcharsbx. Для JS используйте JSON encoding, для URL — проверку схемы/host плюс encoding; HTML escaping не универсален.
- SQL: user ID и object ID в ORM filter, без конкатенации SQL. DDL использует сгенерированное валидированное имя собственной таблицы, не HTTP input.
- Пути: генератор отвергает traversal, ссылки, существующий output; installer разрешает только свой компонент, проверяет ancestors и hash прежней копии. Upload не включён. Добавляя upload, ограничьте размер, MIME по содержимому, extension allowlist, случайное имя, отдельный каталог без исполнения, owner metadata. Download получает непрозрачный ID, проверяет owner до открытия; не принимает пользовательский FS путь.
- Export: Demo::export имеет отдельное право E и CSRF, CSV cells с =,+,-,@ после whitespace получают префикс '. Новый download endpoint обязан повторять права и ограничивать строки/байты/время; полный XLSX/Excel пересчёт проверять отдельно.
- Исключения: наружу общие сообщения и неприватный correlation ID. Журнал не содержит request, аргументы, credentials и message исключения. Настройте PHP error_log вне DOCUMENT_ROOT, ACL, rotation/retention и display_errors=Off; это проверяет администратор стенда.

## Кэш и постоянные данные

Права проверяются до initCache и чтения Option/ORM. Identity, право, канонический фильтр, site и language входят в ключ. TTL 60 и module-local invalidation при изменении; неизвестные значения фильтра не создают keys. Фиксированное множество filters ограничивает число живых срезов пользователя, но TTL не удаляет все старые файлы автоматически: настройте штатную очистку истёкшего кэша, дисковую квоту и мониторинг. На больших порталах определите лимиты user partitions и rate limit чтения.

История/очередь не включены. Перед их добавлением определите retention, max rows/bytes/keys, индекс, chunk cleanup и concurrent limits. Не храните готовый приватный HTML/CSV в shared cache. При отзыве прав уже заполненный кэш должен быть недоступен. Если URL filter пуст, показывайте значение результата Demo::filter, не исходный URL.

CRM-права не расширяются; модульный scope может лишь сужать доступ источника. File ACL не равны CRM ACL. При неизвестной безопасной фильтрации закрывайте функцию, а не выдавайте полный aggregate.

## Проверки и зависимости

Runtime fixture проверяет auth, разрешения операций, CSRF/GET, отзыв прав до cache, owner, XSS, CSV, invalid filter, TTL/invalidation, чужие файлы и symlink. Стенд дополнительно проверяет role matrix, прямые URL, POST без cookie/nonce, concurrency и логи без публичного доступа.

Runtime Composer зависимостей нет. Если нужны: module-private vendor, composer.json + composer.lock в Git, проверка лицензий/provenance и конфликтов FQCN с платформой, dependency scoping при необходимости. `composer validate --strict`, `composer audit --locked`, затем `composer install --no-dev --prefer-dist --no-interaction --no-scripts` из lock; scripts разрешаются отдельно после review. Никакого безусловного composer update при delivery, общего vendor и копирования лицензированных Bitrix исходников.

Перед публикацией выполняется полный импортированный security flow. Собственные smoke-tests не заменяют этот аудит. Не храните secrets даже в отчётах; сборщик проверяет известные сигнатуры, что не доказывает отсутствие всех возможных секретов. Review tracked content/history обязателен.
