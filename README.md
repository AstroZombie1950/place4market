# Place4Market

Доска объявлений для Сербии: сайт на трёх языках (сербский латиницей и кириллицей, английский) и админка (английский / русский).

PHP 8.5 без фреймворков, MariaDB 10.11, CSS на переменных, JS — vanilla. Отступы табами, классы в стиле БЭМ.

> **Пуш в `main` сразу выкатывается на боевой сайт** (GitHub Actions, `.github/workflows/deploy.yml`). Сломанное в `main` не пушим.

## Запуск локально

```
php -S localhost:8000 route.php
```

- Сайт: http://localhost:8000/ (латиница), `/cir/` (кириллица), `/en/` (английский); админка — http://localhost:8000/admin/ (вход через вход сайта).
- `route.php` разбирает человекочитаемые адреса (на сервере его подключает `.htaccess`); без него открывается только главная.
- Загрузка фото: `php -d upload_max_filesize=16M -d post_max_size=20M -S localhost:8000 route.php`.
- База — MariaDB 10.11. Настройки подключения — `config/local.php` (в `.gitignore`), формат — ниже.

```
php source/db/migrate.php          # применить новые миграции
php source/db/demo-listings.php    # тестовые объявления (--purge — убрать)
php source/db/password.php <email> # задать пароль пользователю
php source/db/cron.php             # регулярное обслуживание (на сервере — по cron)
```

## Как устроено

**Путь запроса.** Существующие файлы (CSS, JS, картинки) сервер отдаёт сам, остальное `.htaccess` направляет в `route.php`: он определяет язык по префиксу (`/`, `/cir/`, `/en/`) и страницу (`page_routes()` в `i18n.php`), кладёт параметры в `$_GET` и подключает скрипт страницы. Обработчики `fetch`-запросов (`favorite.php`, `phone.php`…) и админка открываются по своему `.php`-адресу.

**Начало каждой страницы** — `source/inc/bootstrap.php`: журнал и перехват ошибок (`log.php`, первым) → хелперы, SEO, заголовки безопасности → язык из адреса → БД (`db.php`, `repo.php`) → вход (`auth.php`) → почта. Дальше страница заполняет `$page` (title, description, canonical, robots, schema), подключает `header.php`, выводит своё и подключает `footer.php`. Админка начинается с `source/inc/admin/bootstrap.php`: то же ядро плюс проверка роли.

**Слои.** Данные — только `repo.php` (страницы запросов не делают). Логика сайта — `site.php`, `attrs.php`, `taxonomy.php`. SEO — `seo.php`. Вход — `auth.php`. Вывод — PHP-шаблоны страниц и `source/inc/partials/`, всегда через `e()`. JS только дополняет: ссылки настоящие, формы работают без него.

**Фоновая работа** — консольные скрипты по cron: `source/db/cron.php` (сроки объявлений, оповещения, чистка, курс евро) и `source/db/media-worker.php` (обработка видео). Веб-PHP внешние программы не запускает.

## Структура

```
route.php             маршрутизатор человекочитаемых адресов
index.php             главная
category.php          выдача: фильтры, сортировка, пагинация
listing.php           объявление
post.php              подача и редактирование объявления
seller.php            страница продавца
profile.php           профиль: мои объявления, настройки
favorites.php         избранное и сохранённые поиски
messages.php          сообщения
auth.php, reset.php, verify.php, logout.php
                      вход и регистрация, сброс пароля, подтверждение e-mail, выход
about.php, help.php, safety.php, terms.php, privacy.php, contact.php
                      информационные страницы
photo-upload.php, video-upload.php, favorite.php, phone.php, view.php, report.php,
review.php, notify.php, saved-search.php, js-error.php
                      обработчики fetch / sendBeacon
sitemap.php           карта сайта (/sitemap.xml)
404.php               страница 404
admin/                админка: дашборд, объявления, модерация, жалобы, пользователи, категории,
                      посадочные, обращения, настройки, журнал, миграции, хранилище
source/
	css/              variables.css (все токены), site.css, admin.css
	js/               main.js (сайт), admin.js, select.js (выпадающие списки), phone.js, vendor/
	fonts/            woff2 по наборам latin / latin-ext / cyrillic
	img/              логотип, иконки, заглушки, водяной знак, демо-фото
	inc/              ядро: bootstrap, log, config, db, repo, auth, i18n, seo, site, helpers,
	                  media, video, storage, mail, codes; partials/, content/, admin/
	lang/             тексты сайта (en, sr-latn, sr-cyrl — генерируется), admin/ (en, ru)
	db/               migrations/, migrate.php, cron.php, media-worker.php, служебные скрипты
```

## Языки и адреса

| Страница | Латиница | Кириллица | Английский |
|---|---|---|---|
| Главная | `/` | `/cir/` | `/en/` |
| Выдача | `/oglasi/vozila/automobili` | `/cir/oglasi/…` | `/en/listings/vehicles/cars` |
| Объявление | `/oglas/<слова>-<id>` | `/cir/oglas/…` | `/en/listing/…` |
| Продавец | `/prodavac/<id>` | `/cir/prodavac/…` | `/en/seller/…` |

- Язык задаётся только адресом. Ссылки — только `url('page.php', [...])` / `lang_link()`; новая страница — строка в `page_routes()` (`i18n.php`), смена слова адреса — старое в `route_aliases()`.
- Один адрес на страницу: старые адреса, другой регистр, слэш в конце, прежнее название объявления — 301 на точный адрес (`enforce_url()`).
- Тексты интерфейса — `t('key')`, множественное число — `tp('key', $n)`; тексты страниц вида `['en' => …, 'sr' => …]` — `tc()`.
- **Кириллицу не правим руками:** правим `source/lang/sr-latn.php`, затем `php source/lang/build-cyrillic.php`.
- Тексты пользователей хранятся как написаны и выводятся через `user_text()` (латиница ↔ кириллица по странице, без перевода).

## Данные и миграции

- Все запросы — функции `source/inc/repo.php`, только подготовленные запросы. Даты в БД — UTC.
- Схема меняется только новой миграцией `source/db/migrations/NNN_name.php` (файл возвращает SQL). Применённую миграцию не правим.
- Деплой применяет миграции **до** выпуска нового кода, поэтому миграция должна работать и с прежним кодом: новые таблицы и колонки со значением по умолчанию; удаление или переименование колонки — в два выката.

Файл настроек ищется по порядку: переменная `P4M_CONFIG` → `config/local.php` → `app-config.php` рядом с корнем сайта (вне webroot). Он возвращает массив:

```php
<?php
return [
	'db' => ['host' => 'localhost', 'port' => 3306, 'name' => '…', 'user' => '…', 'pass' => '…'],
	// необязательно:
	'alerts'    => ['email' => '…', 'telegram' => ['token' => '…', 'chat_id' => '…']], // критичные ошибки
	'mail'      => ['from' => '…', 'transport' => 'mail' /* | 'smtp' | 'log' */],
	'analytics' => ['ga4' => 'G-…'],
	'storage'   => ['driver' => 's3', /* endpoint, bucket, key, secret, public_url */], // без него — диск
	'sms'       => ['driver' => 'twilio', /* ключи Twilio Verify */],
];
```

Настройки сайта (модерация, сроки, лимиты фото, курс) — админка → Settings, таблица `settings`, чтение — `setting('name')`.

## Обязательные правила

- **Адаптив:** CSS от десктопа, брейкпоинты только `1439px`, `1023px`, `767px`; телефон рисуется под 360 и держится до 320. Без горизонтального скролла, зоны нажатия от 44×44, поля ввода на телефоне от 16 px. Админка — десктоп от 1024 и мобильный до 1023.
- **Скорость:** цель — 100 в Lighthouse. Без фреймворков и jQuery, JS с `defer`, на `data-`атрибутах. CSS, JS и картинки — через `asset()` (добавляет `?v=`, кэш на год); CSS и JS минифицируются при деплое. Шрифты свои, `font-display: swap`.
- **Фото** — только через `photo()` с `sizes` из `PHOTO_SIZES` (`site.php`): `width`/`height`, `srcset`, `lazy` ниже первого экрана, средний цвет до загрузки. Хранятся в WebP пяти размеров (1920 / 960 / 480 / 320 / 200), метаданные снимаются.
- **SEO:** у каждой страницы `$page['title']`, `description`, `canonical`, `robots`, `schema[]`; `<head>` собирает `seo_head()`. Ровно один `<h1>`. Служебные страницы, админка, выдача с поиском и фильтрами — `noindex`. Новая служебная страница — в `robots.txt` на трёх языках; индексируемая попадает в карту сайта.
- **Журнал:** `log_event($level, $message, $context, $channel)`, `log_exception($e)`, `log_action($action, $context)`. Код, который может упасть (БД, почта, файлы, внешние запросы), — в `try`/`catch` с `log_exception()`; значимые действия пользователей и персонала — `log_action()`. Пароли и токены в журнал не пишем. Посетитель видит только понятное сообщение и код ошибки.
- **Безопасность:** вывод через `e()`; каждая форма с POST — `csrf_field()` + `csrf_valid()`; служебные страницы начинаются с `require_login()`; изменения в `repo.php` проверяют владельца. Скрипты только файлами (CSP без встроенных скриптов); новый внешний сервис — добавить в `CSP` (`helpers.php`). Повторяемые действия — с лимитом (`RATE_LIMITS` в `auth.php`). Критичные заголовки и редиректы — в PHP, `.htaccess` — только перезапись адресов и закрытие папок.
- **Стили:** все цвета, шрифты, отступы и радиусы — токены в `source/css/variables.css`. У сайта и админки свои палитры (`.site` поверх `:root`).

## Частые задачи

**Новая страница сайта.** Скрипт в корне начинается с `require __DIR__ . '/source/inc/bootstrap.php';` (служебная — затем `require_login()`), заполняет `$page`, подключает `header.php` и `footer.php`. Строка в `page_routes()` — сербское и английское слово адреса. Ссылки на неё — `url('page.php')`.

**Новый текст интерфейса.** Ключ в `source/lang/en.php` и `sr-latn.php`, затем `php source/lang/build-cyrillic.php`. Текст админки — `source/lang/admin/en.php` и `ru.php`, вывод `at()` / `atp()`.

**Изменение схемы БД.** Новый файл в `source/db/migrations/`, совместимый с прежним кодом; локально — `php source/db/migrate.php`.

**Новая настройка сайта.** Строка в `SETTINGS` (`repo.php`), поле в `admin/settings.php`, подпись `set.<имя>` в словарях админки.

**Новый лимит попыток.** Строка в `RATE_LIMITS` (`auth.php`), в обработчике — `rate_limited()` до действия и `rate_hit()` после, превышение — в журнал.

**Новое место показа фото.** Ключ в `PHOTO_SIZES` и `PHOTO_MAX` (`site.php`) по замеренной ширине.

**Перед коммитом:** `git ls-files '*.php' | xargs -n1 php -l | grep -v 'No syntax'` — пусто; изменённые страницы локально на 360, 768, 1280 и 1440 без горизонтального скролла; изменилась схема — `php source/db/migrate.php`.
