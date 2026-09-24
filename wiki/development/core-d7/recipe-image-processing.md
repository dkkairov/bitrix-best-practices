---
title: "Обработка изображений: Main\\File\\Image"
type: recipe
module: core-d7
edition: box
status: verified
provenance: mixed
verified: "2026-09-24 / коробка в Docker, main 26.750.0: класс Main\\File\\Image и все перечисленные методы есть, константы RESIZE_PROPORTIONAL_ALT=0, RESIZE_PROPORTIONAL=1, RESIZE_EXACT=2, форматы включая FORMAT_WEBP=18, движки Gd и Imagick (оба расширения загружены); текст — документация фреймворка (docs.1c-bitrix.ru, «Работа с изображениями»)"
tags: [изображения, ресайз, водяной-знак, exif, webp, cfile]
sources: []
related: ["[[concept-bitrix-naming-conventions]]", "[[concept-box-caching]]", "[[recipe-http-client]]"]
aliases: []
updated: "2026-09-24"
---

# Обработка изображений: `Main\File\Image`

**Результат:** картинка нужного размера, повёрнутая по EXIF, с водяным знаком — без сторонних
библиотек.

**Когда применять:** свой импорт каталога, генерация превью для нестандартного хранилища, водяные
знаки на фото. Для обычных инфоблоков и карточек хватает штатного ресайза компонентов.

## Ресайз

```php
use Bitrix\Main\File\Image;

$image = new Image($absolutePath);

if ($image->load())
{
    $image->resize(
        new Image\Rectangle(1200, 1200),   // источник — ограничивающий прямоугольник
        Image::RESIZE_PROPORTIONAL          // режим
    );
    $image->saveAs($destination, 85, Image::FORMAT_WEBP);
    $image->clear();
}
```

| Режим | Что делает |
|---|---|
| `Image::RESIZE_PROPORTIONAL` (1) | вписать в габариты, пропорции сохранены |
| `Image::RESIZE_PROPORTIONAL_ALT` (0) | описать габариты: меньшая сторона равна заданной |
| `Image::RESIZE_EXACT` (2) | точный размер, лишнее обрезается |

Форматы: `FORMAT_JPEG`, `FORMAT_PNG`, `FORMAT_GIF`, `FORMAT_BMP`, **`FORMAT_WEBP`**. Качество —
1…100, по умолчанию 95.

## Остальное из коробки

| Задача | Метод |
|---|---|
| поворот, отражение | `rotate($angle, $bgColor)`, `flipVertical()`, `flipHorizontal()` |
| поворот по EXIF | `autoRotate($orientation)`, `setOrientation()` — снимки с телефона иначе лежат боком |
| размытие, фильтры | `blur($sigma)`, `filter(Image\Mask)` — матрица 3×3 |
| водяной знак | `drawWatermark(Image\ImageWatermark|Image\TextWatermark)`, прозрачность 0…100 |
| метаданные | `getInfo()` → `Image\Info`, `getExifData()` |
| габариты | `getWidth()`, `getHeight()`, `getDimensions()` |
| сохранение | `saveAs($file, $quality, $format)`, `save($quality)` — поверх исходника |
| освободить память | `clear()` |

Движков два: `Image\Gd` (по умолчанию) и `Image\Imagick`. На стенде доступны оба — загружены и
`gd`, и `imagick`; у клиента это не гарантировано, перед использованием `Imagick` проверяем
`extension_loaded('imagick')`.

## Старое API никуда не делось

`CFile::ResizeImageGet()` с константами `BX_RESIZE_IMAGE_PROPORTIONAL`,
`BX_RESIZE_IMAGE_PROPORTIONAL_ALT`, `BX_RESIZE_IMAGE_EXACT` работает и остаётся единственным
удобным путём, когда файл лежит в файловой таблице (`b_file`) и нужен кэш превью. В новом коде
поверх собственных файлов используем `Main\File\Image` ([[concept-bitrix-naming-conventions]]).

## Ловушки

- **Память.** Большой JPEG в GD разворачивается в несжатый растр: 6000×4000 — это сотни мегабайт.
  Проверяем `getInfo()` до `load()` и отказываем, если картинка неадекватная.
- **`save()` пишет поверх оригинала.** Для превью только `saveAs()`.
- **EXIF-ориентация теряется при ресайзе** — сначала `autoRotate()`, потом `resize()`.
- **WebP поддерживает не каждый старый браузер** — отдаём через `<picture>` с запасным JPEG.
- **Результат кэшируем сами.** Ресайз на каждый показ — верный способ уложить сервер
  ([[concept-box-caching]]).
- **Картинка из интернета — не доверенный файл.** Скачали [[recipe-http-client|HttpClient]] —
  проверяем тип и размеры до обработки.

## Связанные страницы
- [[concept-box-caching]] — где хранить готовые превью
- [[recipe-http-client]] — если картинку забираем по ссылке

[← Ядро D7](_index-core-d7.md)
