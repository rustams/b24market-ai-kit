# b24market-ai-kit

Работающий starter-kit для агентной разработки PHP/D7 модулей **коробочного Bitrix24**. Генератор, минимальный модуль, восемь переносимых скиллов, база знаний, lifecycle tests и воспроизводимая упаковка. Это не облачное REST-приложение. Лицензия MIT; security skills bitrix-tools включены с оригинальной MIT-лицензией и зафиксированным upstream commit.

Требуются Python 3.9+ и PHP 8.2+ или Docker. Runtime модуля не зависит от этого репозитория и не содержит Composer зависимостей. Bitrix, лицензии, секреты и доступ к порталу не требуются для генерации и локальных проверок.

Интерактивный запуск:
```sh
python3 scripts/new-project.py
```
Генератор спрашивает профиль, ID/namespace, название/описание, разработчика/сайт, версию, новый каталог, PHP/main minimum и опциональное ORM-хранилище. Версия по умолчанию 0.1.0. Существующие каталоги, traversal и симлинки отвергаются.

Эквивалентные команды для автоматизации:
```sh
python3 scripts/new-project.py --non-interactive \
  --profile local --module-id acme.hello --namespace 'Acme\Hello' \
  --name 'Приветствие' --description 'Защищённый пример' \
  --developer 'Acme' --website https://example.org \
  --output ./build/hello-local

python3 scripts/new-project.py --non-interactive \
  --profile marketplace --module-id acme.hello --namespace 'Acme\Hello' \
  --name 'Приветствие' --description 'Защищённый пример' \
  --developer 'Acme' --website https://example.org \
  --output ./build/hello-marketplace
```
По умолчанию PHP 8.2.0, Bitrix main 23.0.0; их можно задать `--min-php`, `--min-bitrix`, начальную версию — `--version`. Это нижние границы проекта, не подтверждение native совместимости. `--with-storage` добавляет D7 ORM пример собственной MySQL таблицы с owner isolation. CRM/агенты/очереди не добавляются.

Проверки starter-kit:
```sh
python3 -m unittest discover -s tests -v
# Принудительно использовать только новый контейнер без сети и портальных mounts:
KIT_PHP_IMAGE=php:8.2-cli python3 -m unittest discover -s tests -v
```
В сгенерированном проекте: `python3 scripts/verify.py`. Он самостоятельно содержит fixtures, документацию, скиллы, упаковщик и обновление security materials.

Пример после генерации:
```sh
cd build/hello-local
python3 scripts/verify.py --docker-image php:8.2-cli
git init -b main
git add .
git commit -m 'feat: initialize module'
python3 scripts/build.py local-full --ref HEAD --output build/first
python3 scripts/build.py local-deployment --ref HEAD --output build/deploy
```
Marketplace проект использует `marketplace-full`. После увеличения версии и коммита:
```sh
python3 scripts/build.py marketplace-full --ref HEAD --output build/full
python3 scripts/build.py marketplace-update --ref HEAD \
  --base-ref PREVIOUS_COMMIT --previous-version 0.1.0 --output build/update
```
Подставьте точный commit опубликованного выпуска. Архивы создаются только из Git blobs и нового выходного каталога; manifest/SHA256 и разные layouts входят в contract. Полная `.last_version/` сборка и version-root delta различаются. На macOS имя полного ZIP без точки — `last_version.zip`; принятие текущим кабинетом остаётся открытым.

Демонстрация: штатная установка на отдельно разрешённом стенде → настройка приветствия → компонент на собственной тестовой странице → права R/D и пустой filter → обновление с сохранением → default uninstall/reinstall → explicit purge. Страницы, menu/ACL, события и агенты не создаются автоматически. CSV export и опциональный ORM — проверенные серверные примеры для дальнейшей интеграции, без лишних endpoints.

Официальные материалы проверены и зарегистрированы в docs/sources.json; PHP/D7 и REST разделены. Например, [полная сборка](https://dev.1c-bitrix.ru/learning/course/index.php?COURSE_ID=101&LESSON_ID=3216&LESSON_PATH=8781.4793.3216) и [обновления](https://dev.1c-bitrix.ru/learning/course/index.php?COURSE_ID=101&LESSON_ID=3218&LESSON_PATH=8781.4793.3218) имеют разные назначения. Исследование Radar отражено только в самостоятельно написанных универсальных решениях, без копирования его кода/данных/доступов; отменённый эксперимент не принят за production contract.

Security skills автоматически включаются в каждый проект. Обновление из [оригинального репозитория](https://github.com/bitrix-tools/marketplace-security-skills):
```sh
python3 scripts/update-security-skills.py --ref REVIEWED_40_CHARACTER_UPSTREAM_SHA
```
Сначала проверьте upstream commit, затем diff и тесты. Silent latest обновление отключено, генерация работает offline. Перед публикацией обязателен полный scan/review/journal/validate/UI flow по docs/marketplace.md. Нет обещания модерации или production-готовности без проверки кабинета и licensed native cycle.

Короткий промпт для нового агента:
> Используй https://github.com/rustams/b24market-ai-kit и AGENTS.md. Моя идея модуля: [описание]. Уточни только требования первого полезного сценария, роли, источник данных и канал local/marketplace. Подтверди PHP/D7 API официальными источниками, сгенерируй независимый проект в новом каталоге, реализуй сценарий, выполни локальные проверки и подготовь поставку из коммита. Не меняй существующие порталы; native acceptance и публикацию согласуй отдельно. Отвечай по-русски, указывай количество изменённых файлов без списка.

Готовность: generator/profile/security/lifecycle/archive checks зелёные; skills и ссылки самодостаточны; приёмка заполнена реальными доказательствами, в том числе native и security journal. Шаблоны ADR/приёмки и точная граница локальных checks включены. CI только проверяет; production/portal/Marketplace jobs отсутствуют.

Полный импортированный security flow требует Python 3.10+ (рекомендуется 3.12); Python 3.9 поддерживается только для генератора и основных локальных проверок. Проверяйте `python3 --version` перед review/journal/UI. Проверка --help не подтверждает исполнение полного flow.

Пошаговая будущая нативная приёмка демонстрационного acme.hello: [инструкция](docs/native-acceptance-acme-hello.md). Два отдельных прогона: clean 0.2.1 и exact 0.2.0 → 0.2.1; кабинет/prefix и baseline являются предварительными условиями. Портальные действия по этой инструкции ещё не выполнялись.
