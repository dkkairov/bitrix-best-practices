# Снимок-манифест: документация Bitrix Framework (docs.1c-bitrix.ru), 2026-09-24

| Поле | Значение |
|------|----------|
| Источник | <https://docs.1c-bitrix.ru/> — «BitrixFramework», новый формат официальной документации |
| Фокус | фреймворк: ядро, ORM, база данных, UI, модули, безопасность, производительность |
| Статус | эталон наряду с офф. курсами и «Книгой разработчика» (`CLAUDE.md` §9, решение 2026-09-24) |
| Снят | 2026-09-24, 221 страница — полный список из `sitemap.xml` |
| Что внутри | только метаданные: адрес, хэш HTML, объём, заголовок |
| Чего нет | текста документации: в репозиторий не копируем. Содержание — в конспектах `wiki/sources/` и на страницах вики |

**Дат и версий у документа нет** — на страницах не проставлены ни дата обновления, ни версия
продукта (версии модулей встречаются только в тексте отдельных абзацев). Поэтому единственный способ
заметить правку эталона — сравнить хэши с этим снимком.

## Ловушка адресов

В `sitemap.xml` пути даны **без** префикса `/pages/`: `orm/orm-concepts.html`. По такому адресу сайт
отдаёт 404 — рабочий адрес `https://docs.1c-bitrix.ru/pages/orm/orm-concepts.html`. В таблицах ниже
путь указан так же, как в карте сайта; к нему добавляем `/pages/`.

## Как проверить, изменилась ли документация

```sh
curl -s https://docs.1c-bitrix.ru/sitemap.xml \
 | grep -o '<loc>[^<]*</loc>' | sed 's|</\?loc>||g' \
 | sed 's|https://docs.1c-bitrix.ru/||' | sort \
 | while read -r p; do
     h=$(curl -s "https://docs.1c-bitrix.ru/pages/$p" | sha256sum | cut -c1-16)
     printf '%s\t%s\n' "$p" "$h"
   done
```

Хэш считается от HTML страницы целиком; на 2026-09-24 он стабилен между запросами (проверено
двойной загрузкой одной страницы), динамических токенов в разметке нет.

## Состав разделов

| Раздел | Страниц | Взято в вики |
|---|---|---|
| `modules` | 48 | нет |
| `framework` | 37 (≈22 уникальных: часть страниц продублирована транслитерированными адресами) | **весь раздел, 2026-09-24** — новые страницы: роутинг, контроллеры, очереди, конфигурация ядра, сессии и хранилище, cookie, расширения, компоненты, запрос/ответ, консольные команды; сверены: события, ServiceLocator, Result, валидация, автозагрузка, агенты и фоновые задачи. «Архитектура» прочитана — фактов сверх уже описанного нет |
| `ui` | 34 | нет |
| `get-started` | 20 | нет |
| `cms-basics` | 16 | нет |
| `advanced` | 16 | **12 из 16, 2026-09-24** — взяты: HTTP-клиент, логгеры, отладка, Stepper, дата-время, локализация, изображения, нумератор, соглашения, многосайтовость, бэкап, монитор качества; отложены `uuid`, `encoding`, `geolocation` (по требованию) и `vue` (пойдёт с разделом `ui`) |
| `orm` | 15 | сверено 2026-09-24 ([[concept-d7-orm-entity]], бэклог источников) |
| `security` | 14 | **да, 2026-09-24** — четыре страницы: уязвимости и экранирование, шифрование, проактивная защита, чек-лист приёмки; частности (captcha, firewall, frame-protection, JWT, access-control) свёрнуты в них или отложены |
| `database` | 12 | **да, 2026-09-24** — см. `wiki/development/core-d7/` |
| `performance` | 6 | **да, 2026-09-24** — см. `wiki/development/performance/` |
| `about` | 3 | нет |

Очередь ингеста — строка 6 таблицы в `wiki/sources/sources-backlog.md`.

## Страницы, адрес → хэш → объём → заголовок


### about

| Путь | Хэш | Байт | Заголовок |
|---|---|---|---|
| `about/cms-comparison.html` | `03eae41941c6b64d` | 87559 | Отличия от других CMS |
| `about/ecosystem.html` | `ab2f335f4fea20be` | 53258 | Экосистема |
| `about/framework-comparison.html` | `7e8def8bc25e112d` | 80302 | Отличия от других фреймворков |

### advanced

| Путь | Хэш | Байт | Заголовок |
|---|---|---|---|
| `advanced/backup.html` | `8b220e67949710f5` | 202945 | Резервное копирование |
| `advanced/datetime.html` | `fb1478f4b6d509c4` | 283792 | Дата и время |
| `advanced/debug.html` | `d4d98c211bcb83a9` | 138618 | Отладка |
| `advanced/encoding.html` | `901d5b78a91320f7` | 144531 | Кодировка |
| `advanced/geolocation.html` | `4b0b9d24764f9e36` | 124830 | Геолокация |
| `advanced/http-client.html` | `2c267114adbafa74` | 324681 | HTTP-клиент |
| `advanced/images.html` | `4e18a925002bac70` | 171575 | Работа с изображениями |
| `advanced/localization.html` | `cf0b9e524d60d5a1` | 239314 | Локализация |
| `advanced/logger.html` | `aab77af43a8e213b` | 252027 | Логгеры |
| `advanced/multisite.html` | `4cee2a9b1a947cbd` | 297340 | Многосайтовость |
| `advanced/numerator.html` | `bf19d72c3073027b` | 268551 | Нумератор |
| `advanced/quality-monitor.html` | `6defe8dbbedca5f5` | 86740 | Монитор качества |
| `advanced/stepper.html` | `ffbfb8d5051382c8` | 134764 | Итератор |
| `advanced/user-consent.html` | `c145917bc7021c61` | 158676 | Пользовательские соглашения |
| `advanced/uuid.html` | `599b1510be27ab8e` | 59935 | Идентификатор UUID |
| `advanced/vue.html` | `9622c3bfb411faae` | 526497 | Vue.js |

### cms-basics

| Путь | Хэш | Байт | Заголовок |
|---|---|---|---|
| `cms-basics/admin-panel.html` | `5b82223854fdac7e` | 251214 | Панель администрирования |
| `cms-basics/breadcrumbs.html` | `a79fd113579c848d` | 91503 | Хлебные крошки |
| `cms-basics/includes.html` | `27fcdf4f2bf35b8c` | 100094 | Включаемые области |
| `cms-basics/mail.html` | `aae8e7213ebcefcb` | 351520 | Работа с почтой |
| `cms-basics/menu.html` | `705bdf651b4cf878` | 445701 | Меню |
| `cms-basics/page-navigation.html` | `4c176291237161dc` | 159419 | Постраничная навигация |
| `cms-basics/page-templates.html` | `534f29d17a7866b0` | 241194 | Шаблоны страницы |
| `cms-basics/properties.html` | `29e8ac2bf1e9b163` | 182869 | Свойства страницы и раздела |
| `cms-basics/site-templates.html` | `ebdad3def8e10d0e` | 112592 | Шаблоны сайтов |
| `cms-basics/sites.html` | `4097bc6e1014dbea` | 58320 | Сайты |
| `cms-basics/sms.html` | `c9569de11c798504` | 293751 | Работа с СМС |
| `cms-basics/styles.html` | `eb442dd22c5c9590` | 279607 | Работа со стилями |
| `cms-basics/titles.html` | `ea7c809ca0dfdb71` | 174219 | Заголовки страницы: на сайте и в браузере |
| `cms-basics/user-groups.html` | `db0a5235b99b4d46` | 107661 | Группы пользователей |
| `cms-basics/userfields.html` | `ab39ef6be753bbb5` | 247504 | Пользовательские поля |
| `cms-basics/users.html` | `8add16d10654e1aa` | 173698 | Пользователи |

### database

| Путь | Хэш | Байт | Заголовок |
|---|---|---|---|
| `database/configuration.html` | `4cb469a5899bfb2d` | 167008 | Конфигурация |
| `database/handlersocket.html` | `73298134d73d5455` | 76803 | HandlerSocket |
| `database/nosql.html` | `cefc16fa8b626de7` | 118241 | NoSQL |
| `database/postgresql/compatible-code.html` | `426732e195eb69c9` | 312404 | Как писать код, совместимый с PostgreSQL |
| `database/postgresql/installation-macos.html` | `2e883b19b67deae2` | 116182 | Установка PostgreSQL на macOS |
| `database/postgresql/migration.html` | `9e77819774704ad2` | 158437 | Как перейти с MySQL на PostgreSQL |
| `database/postgresql/module-support.html` | `1577dde902d5e299` | 133084 | Поддержка PostgreSQL в модулях |
| `database/query-builder.html` | `b76e9b4b545aaa3b` | 34782 | Построитель запросов |
| `database/query-execution.html` | `07d314cf1029d47b` | 227579 | Выполнение запросов |
| `database/sql-helper-and-expression.html` | `40d1f89baf499d55` | 359984 | Формирование запросов |
| `database/sql-tracker.html` | `8410a1b775650881` | 97826 | Отладка запросов |
| `database/transactions.html` | `8e6ba44d6c3d38b0` | 77675 | Транзакции |

### framework

| Путь | Хэш | Байт | Заголовок |
|---|---|---|---|
| `framework/agenty-i-fonovye-zadachi.html` | `8141e19558020e5b` | 64893 | Агенты и фоновые задачи |
| `framework/app-and-context.html` | `c9bf44a4886a3e09` | 77809 | Объект приложения и контекст |
| `framework/architecture.html` | `5de1bf8c573625fc` | 64370 | Архитектура |
| `framework/arkhitektura.html` | `8dc1e512cef12a42` | 6876 | Архитектура |
| `framework/autoloading.html` | `9ff40ce068903a59` | 80963 | Автозагрузка классов |
| `framework/avtoloading.html` | `41f79dc2ced8639c` | 13483 | Автозагрузка классов |
| `framework/background-jobs.html` | `13c375ad5db7bf6c` | 239863 | Агенты и фоновые задачи |
| `framework/components.html` | `d7cfa315133ddfdc` | 507648 | Компоненты |
| `framework/console-commands.html` | `6d83d36f3fbced99` | 224453 | Консольные команды |
| `framework/controllers.html` | `4b40aba4b6787c5c` | 635425 | Контроллеры |
| `framework/cookies.html` | `14115963361a8684` | 179006 | Cookie-файлы |
| `framework/di-i-servicelocator.html` | `84e6ccf7bc2037bf` | 20440 | Service Locator |
| `framework/events.html` | `ff3997d464b81ba6` | 198102 | События |
| `framework/extensions.html` | `92e48c6b84a4f53f` | 193135 | Расширения |
| `framework/komponenty.html` | `645b69cf5c48ea1f` | 137038 | Компоненты |
| `framework/kontrollery.html` | `29f268fa02dfea1d` | 69303 | Контроллеры |
| `framework/kuki.html` | `fc285b11ccc74b41` | 49069 | Cookie-файлы |
| `framework/messenger.html` | `f965c2ab02defb57` | 297673 | Очереди сообщений |
| `framework/obekt-prilozheniya.html` | `025f0a285509b765` | 13451 | Объект приложения и контекст |
| `framework/pre-i-post-filtry.html` | `fb990a6c160b9c12` | 46245 | Пре- и постфильтры |
| `framework/pre-post-filters.html` | `45a2c26999870373` | 287851 | Пре- и постфильтры |
| `framework/rasshireniya.html` | `484d4ebc9b180364` | 45417 | Расширения |
| `framework/request-lifecycle.html` | `5f34bd0765fa11ff` | 101731 | Жизненный цикл запроса |
| `framework/request-response-2.html` | `3b36c89918d9b249` | 52396 | Request и Response |
| `framework/request-response.html` | `6594ba4af9f7385d` | 252404 | Request и Response |
| `framework/results-and-errors.html` | `8e0e91d6bf8ec2c4` | 106824 | Результат работы и ошибки |
| `framework/rezultat-raboty-i-oshibki.html` | `242df72269257dd2` | 27123 | Результат работы и ошибки |
| `framework/routing.html` | `6c5a7598b839ffe8` | 422952 | Роутинг |
| `framework/service-locator.html` | `195bc76357cb9e6f` | 114103 | Service Locator |
| `framework/sessii-2.html` | `a6d21a9c94c705b6` | 38870 | Сессии |
| `framework/sessions.html` | `61e47bcec5e7e5ec` | 171373 | Сессии |
| `framework/settings.html` | `46f33beeb6fe7467` | 314382 | Конфигурация ядра |
| `framework/sobytiya.html` | `9c62dc9eb4444a79` | 32045 | События |
| `framework/storage.html` | `6d1e1879194f472c` | 156815 | Временное хранение |
| `framework/validaciya.html` | `0eef17dac90873d9` | 77331 | Валидация |
| `framework/validation.html` | `82a8f927011ee376` | 342447 | Валидация |
| `framework/zhiznennyy-cikl-zaprosa.html` | `db200a6077ffb592` | 20127 | Жизненный цикл запроса |

### get-started

| Путь | Хэш | Байт | Заголовок |
|---|---|---|---|
| `get-started/chto-dalshe.html` | `56cb45a5e98da88d` | 6011 | Что дальше |
| `get-started/composer.html` | `e9845707db29ab46` | 78069 | Composer |
| `get-started/create-component.html` | `a985b406d0d9df83` | 157051 | Создание компонента |
| `get-started/create-controller.html` | `d28f75310eefdc10` | 217522 | Создание контроллера |
| `get-started/create-module.html` | `a3b31f5f732b8912` | 153368 | Создание модуля |
| `get-started/create-page.html` | `f45bcc385b5363d1` | 75973 | Создание страницы |
| `get-started/directory-structure.html` | `7ab3d255ed524084` | 92388 | Структура директорий |
| `get-started/how-customize.html` | `8f826bc9097df821` | 93980 | Как правильно и безопасно кастомизировать коробочные продукты |
| `get-started/install-distr.html` | `07609b3d8a1de781` | 120866 | Установка дистрибутива |
| `get-started/install-env.html` | `ae8530b04599ba04` | 139330 | Установка окружения |
| `get-started/install-solution.html` | `f2060a6dbbac8c95` | 56776 | Установка решения |
| `get-started/next-steps.html` | `ca29f838822b7d36` | 45088 | Что дальше |
| `get-started/sozdanie-komponenta.html` | `403c9ee078e2d0d1` | 41116 | Создание компонента |
| `get-started/sozdanie-kontrollera.html` | `17bb936f934eec86` | 53592 | Создание контроллера |
| `get-started/sozdanie-modulya.html` | `fb333bd6f85a353c` | 32201 | Создание модуля |
| `get-started/sozdanie-stranicy.html` | `5ac8752ba564084e` | 17192 | Создание страницы |
| `get-started/struktura-direktoriy.html` | `67adb12b92cf3ffb` | 19724 | Структура директорий |
| `get-started/ustanovka-okruzheniya.html` | `6e36d76e76e36199` | 24562 | Установка окружения |
| `get-started/ustanovka-resheniya.html` | `452a8f83455d6056` | 11390 | Установка решения |
| `get-started/ustanovka-sayta.html` | `2732955ddd5f66f4` | 29977 | Установка дистрибутива |

### modules

| Путь | Хэш | Байт | Заголовок |
|---|---|---|---|
| `modules/architecture.html` | `5631f92ced08f6a0` | 356029 | Архитектура модулей |
| `modules/catalog/architecture.html` | `a3d6d3428478aa3e` | 396343 | Схема работы торгового каталога и основные объекты |
| `modules/catalog/availability-prices-subscription.html` | `98ed491629a28df1` | 270395 | Доступность, цены и подписка |
| `modules/catalog/bundles-sets-discounts.html` | `343ec53f7243b486` | 229928 | Комплекты, наборы и скидки |
| `modules/catalog/catalog-settings.html` | `69fcdedd31a1d35c` | 225910 | Базовые настройки каталога |
| `modules/catalog/choose-catalog-api.html` | `464c5506a5a69888` | 183873 | Как выбрать API торгового каталога |
| `modules/catalog/export-import.html` | `01c4bbf1cf116fb2` | 277292 | Экспорт и импорт |
| `modules/catalog/inventory-management.html` | `f7665aba8ba844ac` | 410316 | Складской учет |
| `modules/catalog/overview.html` | `560c625f5bef7ede` | 103224 | Введение и базовые концепции |
| `modules/catalog/performance.html` | `5467a7b8c01ab84d` | 252720 | Производительность и частые ошибки |
| `modules/catalog/products-and-offers.html` | `1e732de52383789c` | 313205 | Работа с товарами и торговыми предложениями |
| `modules/highloadblocks/architecture.html` | `c6d6a9329d28a8bf` | 121327 | Архитектура и основные объекты |
| `modules/highloadblocks/components.html` | `0bade20a6776629b` | 240883 | Вывод данных компонентами |
| `modules/highloadblocks/events.html` | `61ba8c6dc33caff2` | 231751 | События записей и права доступа |
| `modules/highloadblocks/manage-highloadblocks.html` | `f16e35a8f1687855` | 226329 | Создание, настройка и перенос Highload-блоков |
| `modules/highloadblocks/overview.html` | `bc85794b139cfdf1` | 104717 | Введение и базовые концепции |
| `modules/highloadblocks/performance.html` | `33dc42b92e4fbae1` | 235407 | Производительность и частые ошибки |
| `modules/highloadblocks/records.html` | `fa0bafcc2f37e304` | 282856 | Работа с записями |
| `modules/highloadblocks/relations-and-directories.html` | `111333b5d8909b97` | 269543 | Связи и справочники на Highload-блоках |
| `modules/iblocks/api.html` | `223c468fa7e53c0a` | 686556 | Работа с инфоблоками через API |
| `modules/iblocks/architecture.html` | `342eac3cf80cd770` | 391136 | Схема работы инфоблоков и основные объекты |
| `modules/iblocks/overview.html` | `3f86eb125d79c3dd` | 101005 | Введение и базовые концепции |
| `modules/iblocks/performance.html` | `ca1c9448e0377e20` | 234803 | Производительность и частые ошибки |
| `modules/sale/architecture.html` | `7fd29776f27126fc` | 564974 | Схема работы интернет-магазина и основные объекты |
| `modules/sale/archive.html` | `370673272e0f6f47` | 309081 | Архив заказов |
| `modules/sale/basket.html` | `ce6ad1da1f5a38d0` | 504238 | Работа с корзиной |
| `modules/sale/buyers-accounts.html` | `7f65b7b9f9f7e582` | 362173 | Покупатели и внутренние счета |
| `modules/sale/cashbox-checks.html` | `54ee81ff6fb1f11b` | 516093 | Кассы и чеки |
| `modules/sale/choose-sale-api.html` | `79a1a6e529b9865c` | 198868 | Как выбрать API интернет-магазина |
| `modules/sale/delivery-requests.html` | `559ec83361e4db63` | 331604 | Транспортные заявки |
| `modules/sale/delivery-shipments.html` | `d2e7feeeea24be29` | 515995 | Доставка и отгрузки |
| `modules/sale/discounts-coupons.html` | `44eb511293285d3f` | 520202 | Правила работы с корзиной |
| `modules/sale/exchange-import-export.html` | `f25b6ba39715aaea` | 197866 | Обмен, импорт и экспорт заказов |
| `modules/sale/locations-taxes.html` | `720a83040fe411c3` | 278902 | Местоположения и налоги |
| `modules/sale/order-checkout-component.html` | `33b46ac073682407` | 235447 | Оформление заказа и публичные сценарии |
| `modules/sale/order-create.html` | `7001643832bf6db0` | 380481 | Создание заказа |
| `modules/sale/order-documents.html` | `4b7a75bb89a4e411` | 187264 | Печатные формы заказа |
| `modules/sale/order-notifications.html` | `f62794fc763ea404` | 220047 | Уведомления по заказам |
| `modules/sale/order-update.html` | `17062e3450b665ae` | 588049 | Изменение и чтение заказа |
| `modules/sale/overview.html` | `ab251fb764208e0b` | 162019 | Введение и базовые концепции |
| `modules/sale/payments.html` | `867aa615835d6149` | 544815 | Оплаты и платежные системы |
| `modules/sale/performance.html` | `910ac9477665048c` | 324823 | Производительность и частые ошибки |
| `modules/sale/permissions.html` | `c466ca4e5916104a` | 294461 | Права доступа и ограничения |
| `modules/sale/properties.html` | `dd5803fd907af7bd` | 690836 | Свойства заказа |
| `modules/sale/reports.html` | `5337c08e58a8cca1` | 237932 | Отчеты и аналитические выборки |
| `modules/sale/reservation-deduct.html` | `0c8fad717ce19fb3` | 290045 | Резервирование и списание |
| `modules/sale/sale-settings.html` | `f042e97194480b1e` | 323198 | Базовые настройки интернет-магазина |
| `modules/sale/statuses-events.html` | `8bd4c249e08f7749` | 302195 | Статусы и события |

### orm

| Путь | Хэш | Байт | Заголовок |
|---|---|---|---|
| `orm/annotations.html` | `edee0b71d34c16f8` | 106315 | Аннотации классов |
| `orm/collections.html` | `2181a6217a843a71` | 310206 | Коллекции |
| `orm/entity-operations.html` | `95a18ad1e02a645e` | 268559 | Операции с сущностями и события |
| `orm/entity-relations.html` | `c2dcaa5a1a827007` | 485209 | Отношения между сущностями |
| `orm/kollekcii.html` | `451743627cc3ff8a` | 59270 | Коллекции |
| `orm/koncepciya.html` | `9ca346b87ab9a0f4` | 104150 | Концепция ORM |
| `orm/obekty.html` | `6592758da30df73e` | 124773 | Объекты |
| `orm/objects.html` | `592f722aeaf6384a` | 454271 | Объекты |
| `orm/orm-concepts.html` | `6ca187349c6e9a2d` | 391620 | Концепция ORM |
| `orm/otnosheniya.html` | `869a565b8d6b68e6` | 153194 | Отношения между сущностями |
| `orm/postroitel-zaprosov.html` | `503f3361ca60a5dd` | 17728 | Построитель запросов |
| `orm/query-builder.html` | `f1bae13ee780d491` | 90775 | Построитель запросов |
| `orm/querying-data.html` | `73ac4cd2bc04fc4f` | 568925 | Выборка данных |
| `orm/suschnosti.html` | `bb107b62e8ecac3f` | 65062 | Операции с сущностями |
| `orm/vyborka.html` | `32cb6baa1fc8a0c9` | 147462 | Выборка данных |

### performance

| Путь | Хэш | Байт | Заголовок |
|---|---|---|---|
| `performance/caching.html` | `f1827383be1948c8` | 314499 | Кеширование |
| `performance/composite-site.html` | `4b958217e6903472` | 382428 | Композитный сайт |
| `performance/hot-and-cold-session.html` | `8dcba639de782eac` | 55304 | Сессия в разделенном режиме |
| `performance/query-optimization.html` | `10a46b9b14976695` | 117684 | Как оптимизировать запросы к базе данных |
| `performance/replication-and-clustering.html` | `b36a5c0421f3d51c` | 79503 | Репликация и кластер |
| `performance/sharding.html` | `c644e0aa5774ef16` | 54126 | Шардинг |

### security

| Путь | Хэш | Байт | Заголовок |
|---|---|---|---|
| `security/access-control.html` | `851fe0372dcaa402` | 123637 | Права доступа |
| `security/captcha.html` | `0bd8e157101b0e05` | 125005 | CAPTCHA |
| `security/cipher.html` | `abcee0176ef5addb` | 96220 | Шифрование данных |
| `security/cryptofield.html` | `4361071ce200b1b3` | 160477 | Криптографические поля в ORM |
| `security/csrf-ssrf.html` | `6b3b59bd30f498f0` | 160397 | CSRF и SSRF |
| `security/firewall.html` | `55de05621c0a0806` | 66927 | Firewall |
| `security/frame-protection.html` | `ed758646fc49dc8c` | 65520 | Защита от фреймов |
| `security/jwt.html` | `2442a67163ca54e5` | 92219 | JWT |
| `security/proactive-security.html` | `19b59034f9457f60` | 338577 | Проактивная защита |
| `security/sanitizer.html` | `bd8f797ccf84c04b` | 147188 | Санитайзер |
| `security/secure-cookies.html` | `ce4c954ed8c181c8` | 77661 | Защищенные cookie |
| `security/sql-injection.html` | `ced48a18cdd7b1d1` | 124870 | SQL-инъекции |
| `security/two-factor-auth.html` | `a546e5fa99b79d02` | 105750 | Двухэтапная авторизация |
| `security/xss.html` | `72f1856d9f2b6011` | 108827 | XSS |

### ui

| Путь | Хэш | Байт | Заголовок |
|---|---|---|---|
| `ui/a11y/focus-monitor.html` | `8361a7d2ac71297f` | 210167 | Монитор фокуса FocusMonitor |
| `ui/a11y/focus-navigator.html` | `e48e4c14237abfc0` | 192603 | Навигация фокуса FocusNavigator |
| `ui/a11y/focus-trap.html` | `6114cfd1703ace1a` | 219282 | Ловушка фокуса FocusTrap |
| `ui/a11y/focus-zone.html` | `ea1859daab496d00` | 231023 | Зона фокуса FocusZone |
| `ui/a11y/index.html` | `1ce6153a4a41491e` | 67006 | Расширение ui.a11y |
| `ui/a11y/input-modality-tracker.html` | `b4b1ac18fe0e6aa2` | 170962 | Трекер способа ввода InputModalityTracker |
| `ui/a11y/interactivity-checker.html` | `63598559c3797255` | 162091 | Проверка интерактивности InteractivityChecker |
| `ui/a11y/live-announcer.html` | `10bf6df6ca8d34ec` | 165361 | Объявления для скринридера LiveAnnouncer |
| `ui/dialogs-messagebox.html` | `14bd2f81c863776f` | 229012 | Окно сообщения |
| `ui/emoji.html` | `7147f0f13949d4f9` | 97116 | Работа с эмодзи |
| `ui/entity-selector/data-providers.html` | `21276830f4fe9298` | 297850 | Провайдеры данных в ui.entity-selector |
| `ui/entity-selector/dialog.html` | `3a1766366730720c` | 700326 | Dialog в ui.entity-selector |
| `ui/entity-selector/index.html` | `95eec6af00019845` | 85839 | Обзор ui.entity-selector |
| `ui/entity-selector/options.html` | `b1f4c7149dd9e6a0` | 126851 | Общие параметры в ui.entity-selector |
| `ui/entity-selector/standard-providers.html` | `c743df82b44f5d3f` | 258065 | Стандартные провайдеры в ui.entity-selector |
| `ui/entity-selector/tag-selector.html` | `7ce55c69be98548c` | 279815 | TagSelector в ui.entity-selector |
| `ui/hint.html` | `52aa924cb1b2a385` | 154527 | Подсказка |
| `ui/icons.html` | `7369086b0fe35b15` | 560638 | Иконки |
| `ui/lottie.html` | `a5ad07c5d657e119` | 117373 | Анимации Lottie |
| `ui/main-popup.html` | `1517b2cb408016ac` | 322680 | Всплывающие окна и меню main.popup |
| `ui/main-sidepanel.html` | `0402c9cebca9d02f` | 229650 | Боковая панель main.sidepanel |
| `ui/main-ui-filter.html` | `93615200d974ff46` | 212060 | Фильтр main.ui.filter |
| `ui/main-ui-grid.html` | `61ca86b876d59606` | 206270 | Таблица main.ui.grid |
| `ui/notification-manager.html` | `b3899ef0b55d5d3c` | 139378 | Менеджер уведомлений |
| `ui/system-alert.html` | `597c64f6330b86df` | 133247 | Системный алерт |
| `ui/system-chip.html` | `babab8e715ad1a9b` | 211111 | Системный чип |
| `ui/system-dialog.html` | `15e9b4e2f914f94c` | 151020 | Системный диалог |
| `ui/system-highlighter.html` | `5613320775fb021a` | 123529 | Системный хайлайтер |
| `ui/system-input.html` | `9086b351ec556410` | 210107 | Системное поле ввода |
| `ui/system-label.html` | `504caf70220f97c0` | 152329 | Системная метка |
| `ui/system-menu.html` | `ec84d786412e781e` | 221273 | Системное меню |
| `ui/system-skeleton.html` | `99995f08582e8e01` | 146877 | Скелетон загрузки |
| `ui/typography.html` | `320874534a5948e7` | 147349 | Типографика |
| `ui/ui-buttons.html` | `b30a92d4819e5cd8` | 482790 | Кнопки ui.buttons |
