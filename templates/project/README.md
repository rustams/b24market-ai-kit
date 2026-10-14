# @@ID@@ — @@PROFILE@@

Независимый PHP/D7 модуль коробочного Bitrix24. Требуются Python 3.9+ и PHP @@MIN_PHP@@+; вместо локального PHP можно использовать Docker с `php:8.2-cli`. Минимальная версия main — @@MIN_BITRIX@@; совместимость нужно проверить на лицензированном стенде. Runtime зависимостей Composer нет.

```sh
python3 scripts/verify.py
# Если нет PHP: изолированный контейнер без сети и доступа к порталу.
python3 scripts/verify.py --docker-image php:8.2-cli

git init -b main
git add .
git commit -m 'feat: initialize module'
```

Для local:
```sh
python3 scripts/build.py local-full --ref HEAD --output build/first
python3 scripts/build.py local-deployment --ref HEAD --output build/deploy
```
Для marketplace:
```sh
python3 scripts/build.py marketplace-full --ref HEAD --output build/full
# После увеличения версии в module.json и module/install/version.php и нового коммита:
python3 scripts/build.py marketplace-update --ref HEAD --base-ref PREVIOUS_COMMIT --previous-version 0.1.0 --output build/update
```
Сборщик запрещает команды другого профиля. Подставьте реальный предыдущий коммит; Git tag версии должен указывать на точный опубликованный исходник.

Полная local-сборка содержит @@ID@@/ для размещения в `/local/modules`. Затем штатная установка в админке. Deployment дополнительно содержит активную копию компонента, из того же коммита. Миграции после согласованной Git-доставки: `php scripts/update-local.php DOCUMENT_ROOT` под владельцем портала. Не выполнять DoInstall при обновлении.
Marketplace полная сборка содержит `.last_version/`; имя ZIP без начальной точки учитывает документированное исключение macOS. Обновление содержит каталог версии, delta и необходимые support-файлы апдейтера; полный ZIP не обновляет установленный модуль. Штатное размещение выбирает клиент Bitrix, переносить модуль самостоятельно нельзя.

Демонстрация после отдельно разрешённой установки: откройте настройки модуля, сохраните приветствие. Администратор создаёт собственную тестовую страницу с компонентом `@@VENDOR@@:@@MODULE@@.demo` через редактор Bitrix. Установщик не создаёт страницы и не изменяет меню портала. Проверьте авторизованного пользователя с правом R и пользователя D; назначение прав требует согласования. Пустой `filter=` показывает выбранное «Приветствие», HTML приветствия экранируется. Экспорт — серверный пример Demo::export с отдельным правом E и POST/CSRF, его интеграция в UI требует собственного endpoint по docs/security.md.

Перед Marketplace-публикацией пройдите весь импортированный security flow по docs/marketplace.md. Для обновления его материалов: `python3 scripts/update-security-skills.py --ref REVIEWED_UPSTREAM_SHA`. Проверенный MIT импорт включён автоматически; генерация и runtime не обращаются в сеть.

Готовность: локальные тесты зелёные, архив/манифест совпадают, нативная матрица docs/templates/acceptance.md заполнена реальными доказательствами, security journal проверен, кабинет принимает пакет. Production и публикация требуют отдельного разрешения.

Полный импортированный security flow требует Python 3.10+ (рекомендуется 3.12); Python 3.9 поддерживается только для генератора и основных локальных проверок. Проверяйте `python3 --version` перед review/journal/UI. Проверка --help не подтверждает исполнение полного flow.
