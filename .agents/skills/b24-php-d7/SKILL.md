---
name: b24-php-d7
description: "Разрабатывайте PHP/D7 сервисы, ORM и компоненты внутри коробочного модуля; не используйте для облачного REST backend."
---

# b24-php-d7

## Перед изменением
Найдите все callers, include/autoload, fixtures, installer dependencies и active copies. Прочитайте код, затем определите минимальную границу изменения. Не вводите отдельный фреймворк ради простого сервиса.

## Процедура
1. Разделяйте компонент представления и lib сервис. Компонент включает Loader::includeModule по ID; namespace регистрируется в include.php от __DIR__, lowercase filename соответствует классу.
2. Сначала Access operation, затем нормализация ввода, source query, aggregate и cache. ORM filter должен уже содержать source/owner scope; post-filter нельзя использовать для безопасности sums/counts.
3. Возвращайте маленький view model без лишних полей. Для HTTP ловите DomainException как общий отказ; Throwable журналируйте через Diagnostics и выдавайте общий ответ.
4. Не храните техническое состояние вне собственного Option ID/таблицы. Если нужен storage, явно включите пример, задайте индексы/retention/DB compatibility и миграцию.
5. Не пишите raw SQL из запроса. ID — положительное число; ORM exact filter и select allowlist. DDL может использовать только фиксированное сгенерированное собственное имя.
6. Обновите всех callers и meaningful tests; lint применяйте к сгенерированному PHP, шаблон с незаполненными токенами не является валидным PHP.

## Пример
`NoteTable::readOwn(42)` проверяет просмотр и использует ORM `=ID`, `=OWNER_ID` текущего пользователя, limit 1. Если нужен список, добавьте bounded pagination и total по полному разрешённому набору, не считайте итог текущей страницы.

## Проверка
Отрицательные ID/array input, другой owner, отсутствие модуля, exception без утечки, запись без nonce, namespace после штатной установки. Fixture доказывает собственный contract; реальный Loader/ORM проверяется на портале отдельно.

Читайте [архитектуру](../../../docs/architecture.md) для D7/REST boundary, [безопасность](../../../docs/security.md) для trust boundary и [lifecycle](../../../docs/lifecycle.md), если сервис добавляет DB/events/agents. Official API ссылки находятся в sources.json.
