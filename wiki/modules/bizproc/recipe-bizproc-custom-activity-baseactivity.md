---
title: "Своё действие БП на BaseActivity: форма по карте, проверка, результат, ошибки"
type: recipe
module: bizproc
edition: box
status: draft
provenance: mixed
verified: ""
tags: [bizproc, активити, BaseActivity, свои-действия, робот, результат, ошибки]
sources: ["[[source-devbook-bizproc]]", "[[source-course57-developer]]"]
related: ["[[entity-cbp-activity]]", "[[entity-bizproc-activity-description]]", "[[recipe-bizproc-custom-task-activity]]", "[[antipattern-bizproc-php-code-activity]]", "[[entity-bizproc-field-type]]", "[[recipe-module-structure-and-install]]", "[[recipe-bizproc-debugging]]", "[[pattern-robots-vs-bizproc-decision]]"]
aliases: []
updated: "2026-09-22"
---

# Своё действие БП на `BaseActivity`

**Результат:** действие и робот «Номер договора» собирают номер из префикса и ID элемента CRM и
отдают его следующим шагам. Форму настроек рисует ядро по карте полей, обязательные поля
проверяются при сохранении и импорте шаблона, к запуску значения уже разобраны и приведены к типу.

> **Черновик.** Проверено на стенде (коробка, bizproc 26.1075.0, 2026-09-22): ядро находит действие в
> `/local/activities/custom/`, берёт название и результат из `RETURN`; импорт шаблона
> (`validateTemplate`) без обязательного поля отклоняется с текстом «Не заполнено обязательное поле:
> Префикс»; `FILTER` скрывает действие в процессах списков. Отдельным прогоном процесса проверено, что
> ошибки и исключения делают с процессом (шаг 5). Вид формы в дизайнере и роботах и работа этого
> действия на реальной сделке — по коду ядра, не проверялись.

## Когда это, а не другое
- **Сначала — «модулем или нет?»** (`CLAUDE.md` §9). Действие для нескольких порталов поставляет
  модуль: при установке копирует папку в `/local/activities/custom/`
  ([[recipe-module-structure-and-install]]). Разовое для одного портала — папка прямо в
  `/local/activities/custom/` ([[entity-local-directory|структура `/local`]]), а логика объёмнее
  нескольких строк — в классе решения ([[pattern-local-solution-structure]]), действие его только
  вызывает.
- **Действие выполняется сразу и людей не ждёт** — этот рецепт. Ждёт ответа человека — задание
  ([[recipe-bizproc-custom-task-activity]]).
- **Вместо «PHP кода» в шаблоне** — [[antipattern-bizproc-php-code-activity]].
- **Облако:** своих PHP-действий нет, только действия приложений по REST (`bizproc.activity.add`,
  [[entity-bizproc-template-rest-methods]]).

## Предусловия
- Коробка. `Bitrix\Bizproc\Activity\BaseActivity` и `setProperty()` есть в bizproc 26.1075.0; с какой
  версии — не выясняли.
- Имя папки — с префиксом вендора. Ядро берёт первую найденную папку с этим именем, `/local` — раньше
  `/bitrix`: папка с именем штатного действия подменит его во всех шаблонах
  ([[entity-bizproc-activity-description]]).

## Шаги

### 1. Файлы

```text
/local/activities/custom/vendorcontractnumberactivity/
├── .description.php
├── vendorcontractnumberactivity.php      класс CBPVendorContractNumberActivity
└── lang/ru/
    ├── .description.php
    └── vendorcontractnumberactivity.php
```

Папка и файл класса — имя класса без `CBP` строчными. `properties_dialog.php` не нужен: если в папке
нет ни его, ни `robot_properties_dialog.php`, форму строит ядро по карте полей — строки таблицы в
дизайнере, блоки в настройках робота (`BaseActivity::getPropertiesDialog`, код ядра). Есть свой файл —
ядро выводит его.

### 2. `.description.php`

```php
<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true)
{
	die();
}

use Bitrix\Main\Localization\Loc;

$arActivityDescription = [
	'NAME' => Loc::getMessage('VENDOR_CN_NAME'),
	'DESCRIPTION' => Loc::getMessage('VENDOR_CN_DESCR'),
	'TYPE' => ['activity', 'robot_activity'],            // и действие дизайнера, и робот
	'CLASS' => 'VendorContractNumberActivity',            // без CBP
	'JSCLASS' => 'BizProcActivity',
	'CATEGORY' => ['ID' => 'document'],                   // «Обработка документа»
	'ROBOT_SETTINGS' => [
		'GROUP' => ['modificationData'],                  // «Хранение и изменение данных»
		'SORT' => 3000,
	],
	'FILTER' => [
		'INCLUDE' => [
			['crm'],                                      // неполный тип — вся CRM
		],
	],
	'RETURN' => [
		'Number' => [
			'NAME' => Loc::getMessage('VENDOR_CN_NUMBER'),
			'TYPE' => 'string',
		],
	],
];
```

- **`FILTER`** сравнивается с типом документа по префиксу: `['crm']` — вся CRM,
  `['crm', 'CCrmDocumentDeal']` — только сделки. Действие, которое фильтр не пускает, не пропадает из
  выборки: ядро помечает его флагом `EXCLUDED`, и панель его не показывает (стенд).
- **`RETURN`** — результаты для «Вставки значения»; тип держать таким же, как в `SetPropertiesTypes()`.
- Разделы `CATEGORY` и группы `ROBOT_SETTINGS` — в [[entity-bizproc-activity-description]].

`lang/ru/.description.php`:

```php
<?php
$MESS['VENDOR_CN_NAME'] = 'Номер договора';
$MESS['VENDOR_CN_DESCR'] = 'Собирает номер договора из префикса и ID элемента';
$MESS['VENDOR_CN_NUMBER'] = 'Номер договора';
```

### 3. Класс

```php
<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true)
{
	die();
}

use Bitrix\Bizproc\Activity\BaseActivity;
use Bitrix\Bizproc\Activity\PropertiesDialog;
use Bitrix\Bizproc\FieldType;
use Bitrix\Main\Error;
use Bitrix\Main\ErrorCollection;
use Bitrix\Main\Localization\Loc;

class CBPVendorContractNumberActivity extends BaseActivity
{
	// модули подключаются сами перед запуском; список не дополняет родительский, а заменяет
	protected static $requiredModules = ['crm'];

	public function __construct($name)
	{
		parent::__construct($name);
		$this->arProperties = [
			'Title' => '',
			'Prefix' => '',
			'Number' => null,   // результат: null, иначе вернётся значение по умолчанию
		];
		$this->SetPropertiesTypes([
			'Number' => ['Type' => FieldType::STRING],
		]);
	}

	protected static function getFileName(): string
	{
		return __FILE__;
	}

	public static function getPropertiesDialogMap(?PropertiesDialog $dialog = null): array
	{
		return [
			'Prefix' => [
				'Name' => Loc::getMessage('VENDOR_CN_PREFIX'),
				'FieldName' => 'prefix',
				'Type' => FieldType::STRING,
				'Required' => true,   // импорт отклонит шаблон без значения
				'Default' => 'DOG',
			],
		];
	}

	// проверки во время выполнения: значения уже вычислены и приведены к типу
	protected function checkProperties(): ErrorCollection
	{
		$errors = parent::checkProperties();
		if (!preg_match('/^[A-Z]{1,5}$/', (string)$this->Prefix))
		{
			$errors->setError(new Error(Loc::getMessage('VENDOR_CN_BAD_PREFIX')));
		}

		return $errors;
	}

	protected function internalExecute(): ErrorCollection
	{
		$errors = parent::internalExecute();

		$documentId = (string)$this->getDocumentId()[2];   // например, DYNAMIC_1000_15
		$number = $this->Prefix . '-' . preg_replace('/\D+/', '', $documentId);

		$this->setProperty('Number', $number);   // результат для следующих шагов
		$this->log(Loc::getMessage('VENDOR_CN_DONE', ['#NUMBER#' => $number]));

		return $errors;   // ошибки только пишутся в журнал, процесс идёт дальше
	}
}
```

`lang/ru/vendorcontractnumberactivity.php`:

```php
<?php
$MESS['VENDOR_CN_PREFIX'] = 'Префикс';
$MESS['VENDOR_CN_BAD_PREFIX'] = 'Префикс — от одной до пяти латинских заглавных букв';
$MESS['VENDOR_CN_DONE'] = 'Номер договора: #NUMBER#';
```

### 4. Что делает ядро

По коду `BaseActivity` (bizproc 26.1075.0):

| Когда | Что происходит |
|---|---|
| Сохранение настроек, импорт `.bpt` | `validateProperties()`: поле карты с `Required` должно быть заполнено, иначе «Не заполнено обязательное поле: <Name>» (стенд); затем проверки родителя |
| Открытие настроек | своего файла формы нет — поля карты выводит ядро; значения извлекаются по типам полей |
| Запуск: модули | подключаются модули из `$requiredModules`; не подключился — действие закрывается молча, `internalExecute()` не вызывается |
| Запуск: значения | каждое свойство из `arProperties`: выражения разобраны, тип приведён — из `SetPropertiesTypes()`, иначе из карты. `int` из одного значения — само значение, из нескольких — `0`; `bool` — через `CBPHelper::getBool()` |
| Запуск: логика | `checkProperties()`, при пустой коллекции ошибок — `internalExecute()`; `$this->Prefix` внутри — уже готовое значение, не `{=…}` |
| После | ошибки обоих методов пишутся в журнал, действие закрывается, процесс идёт дальше |

`validateProperties()` работает при сохранении шаблона, `checkProperties()` — при каждом запуске, с
вычисленными значениями. Проверку формата, которая зависит от выражений, делать во втором.

### 5. Ошибки: что останавливает процесс

Стенд (bizproc 26.1075.0, 2026-09-22): процесс «проверяемое действие → запись в отчёт»:

| Что сделало действие | Шаг | Журнал процесса | Процесс |
|---|---|---|---|
| вернуло ошибку в `ErrorCollection` | выполнен | ошибка с текстом | идёт дальше |
| бросило `Exception` | закрыт с ошибкой | ошибка с текстом исключения | идёт дальше |
| `CBPActivity`: вернуло `Faulting` из `Execute()` | закрыт с ошибкой | `InvalidExecutionStatus` | идёт дальше |
| упало с фатальной ошибкой PHP (`Error`; на стенде — вызов метода у `null`) | не закрыт | ничего | хит падает, процесс остаётся «Выполняется» |

- **Остановка — решение шаблона.** Верните признак ошибки результатом (например, `ErrorText` в
  `RETURN`) и поставьте после действия условие и «Прерывание процесса». Автор процесса видит остановку
  на схеме.
- Из кода так делает штатное «Прерывание процесса»: `CBPDocument::TerminateWorkflow()` для текущего
  процесса, затем исключение, чтобы свернуть выполнение (код ядра, не проверялось).
- **Фатальные ошибки ловить самим:** тело `internalExecute()` — в `try/catch (\Throwable $e)`, ошибку —
  в `ErrorCollection`. Иначе процесс зависнет без следа в журнале: движок ловит только `Exception`.

Подробно и для `CBPActivity` — [[entity-cbp-activity]].

### 6. Результат для следующих шагов
- Объявить в конструкторе со значением `null`, тип — через `SetPropertiesTypes()`, описать в `RETURN`.
- Записать через `setProperty('Number', $number)`: метод пишет и в свойства, и в подготовленные
  значения. Вариант книги — `$this->preparedProperties['Number'] = …` — тоже работает: чтение свойства
  сначала смотрит в подготовленные значения (код ядра).
- В следующих шагах — `{=A1:Number}`, где `A1` — ID действия в шаблоне; в дизайнере — «Вставка значения
  → Дополнительные результаты».

## Проверка результата
- Действие есть в разделе «Обработка документа» дизайнера сделки и в группе роботов «Хранение и
  изменение данных»; в процессах списков его нет.
- Сохранить настройки без префикса нельзя.
- Прогон на тестовой сделке: в журнале «Номер договора: DOG-<ID>», следующий шаг получает тот же номер
  через `{=A1:Number}`.
- Префикс `dog` строчными: в журнале ошибка про формат префикса, процесс идёт дальше.

## Откат и проблемы
- **Удаляя действие, сначала уберите его из шаблонов:** шаблон с отсутствующим действием не
  загрузится — загрузчик бросит «Activity … is not found» (код ядра).

| Симптом | Причина | Решение |
|---|---|---|
| Действия нет в панели | `FILTER` не пускает этот тип документа; неизвестный `CATEGORY['ID']` | правило фильтра по префиксу; код раздела из [[entity-bizproc-activity-description]] |
| Действие «ничего не сделало», журнал пуст | модуль из `$requiredModules` не подключился | проверить модуль; наследник перечисляет модули полностью |
| После ошибки процесс пошёл дальше | так устроено (шаг 5) | признак-результат, условие, «Прерывание процесса» |
| Процесс висит «Выполняется», в журнале пусто | фатальная ошибка PHP в действии | лог ошибок PHP; `catch (\Throwable)`; процесс удалить и запустить заново ([[recipe-bizproc-debugging]]) |
| Следующий шаг получает пустой результат | ключа нет в `RETURN` или значение не записано | `RETURN` + `setProperty()` |

## Источники и связанное
- Книга: [Свои действия](https://bx24devbook.website.yandexcloud.net/Modul_Biznes_processy/Dejstvia/Svoi_dejstvia.html)
  — [[source-devbook-bizproc]]
- Курс 57: [урок 23034](https://dev.1c-bitrix.ru/learning/course/?COURSE_ID=57&LESSON_ID=23034) —
  алгоритм своего действия; каталог в уроке устарел (`/bitrix/activities/custom/`) —
  [[source-course57-developer]]
- [[entity-cbp-activity]] — классы действия, окружение, ошибки
- [[entity-bizproc-activity-description]] — паспорт `.description.php`
- [[entity-bizproc-field-type]] — типы полей карты и результатов
- [[recipe-bizproc-custom-task-activity]] — действие, которое ждёт человека

[← Бизнес-процессы](_index-bizproc.md)
