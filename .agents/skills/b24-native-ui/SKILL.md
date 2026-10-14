---
name: b24-native-ui
description: "Стройте интерфейсы внутри коробочного Bitrix24: компоненты, настройки, локализация, фильтры и Bitrix UI; не standalone SPA."
---

# b24-native-ui

## Цель
Нативный экран с понятным действием, доступностью и теми же серверными правами, что у источника. Не устанавливайте внешний React/Nuxt framework для одного компонента.

## Процедура
1. Проверьте реальные UI extensions и версии портала read-only по разрешению. Для простой формы хватит PHP template; BitrixVue нужен при существенном клиентском состоянии, а не автоматически.
2. Пользовательский экран оформляйте компонентом, настройки — options.php; общий portal menu не меняйте без выбранного сценария. Встроенный пример не создаёт public pages.
3. Передайте эффективный server filter в view model и контрол. Пустой `filter=` с fallback должен визуально показывать welcome. Не выводите пустой контрол при выбранном сервером значении.
4. Выводите text/attribute через htmlspecialcharsbx, JS data через JSON encoding, URL после allowlist. Не вставляйте user content через innerHTML.
5. Используйте Loc для пользовательских строк, label/id, native keyboard focus, button semantics и понятный error/empty state. Длинные названия и узкая ширина не должны скрывать основное действие.
6. Ресурсы идут через активный публичный component/extension path. includeModule работоспособен при local и штатном Marketplace размещении; прямой modules URL сначала подтвердите Network.

## Пример
Форма component template выбирает option по `arResult['filter']`, а приветствие экранирует при выводе. Не берите selected из raw GET: источник использует уже разрешённый default.

## Проверка
PHP lint; при JS — node --check; native browser по согласованию: широкий/узкий экран, tab/Enter, error/empty, XSS payload как текст, filter fallback, Network/console, роль R и D. Скриншот admin не подтверждает права обычного пользователя.

Читайте [архитектуру](../../../docs/architecture.md) для путей классов/ресурсов и [тестирование](../../../docs/testing.md) для границы browser/native acceptance. Изменение access endpoint требует b24-server-security.
