# Booking Saver for HelloOtel

Система для сохранения броней через Chrome-расширение.

Состоит из двух частей:
- **Laravel-приложение** — adminка (Filament) + REST API для расширения
- **Chrome-расширение** (`chrome-extension/`) — парсит страницы бронирований и сохраняет данные через API

---

## Требования к серверу

| Компонент | Версия |
|-----------|--------|
| PHP       | >= 8.2 (рекомендуется 8.4) |
| MySQL     | >= 8.0 |
| Apache    | >= 2.4 |
| Composer  | >= 2.x |

PHP-расширения: `pdo_mysql`, `mbstring`, `xml`, `curl`, `zip`, `bcmath`, `intl`

> **Хостинг Hellootel (booking-configurator.hellootel.com):**
> Системный PHP — 8.1, PHP 8.4 находится по пути `/opt/php84/bin/php`.
> Во всех командах ниже заменить `php` на `/opt/php84/bin/php`,
> а `composer` на `/opt/php84/bin/php /opt/php84/bin/composer`.

---

## Установка на новом сервере

### 1. Клонировать / распаковать проект

```bash
git clone <repo-url> /var/www/booking_saver
# или
unzip booking_saver.zip -d /var/www/booking_saver
```

### 2. Установить PHP-зависимости

```bash
cd /var/www/booking_saver
composer install --no-dev --optimize-autoloader

# На Hellootel (PHP 8.1 по умолчанию):
/opt/php84/bin/php /opt/php84/bin/composer install --no-dev --optimize-autoloader
```

### 3. Настроить окружение

```bash
cp .env.example .env
php artisan key:generate

# На Hellootel:
/opt/php84/bin/php artisan key:generate
```

Отредактировать `.env`:

```env
APP_NAME="Booking Saver for HelloOtel"
APP_ENV=production
APP_URL=https://your-domain.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=booking_saver
DB_USERNAME=your_db_user
DB_PASSWORD=your_db_password
```

### 4. Создать базу данных

```sql
CREATE DATABASE booking_saver CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 5. Запустить миграции

```bash
php artisan migrate

# На Hellootel:
/opt/php84/bin/php artisan migrate
```

### 6. Создать первого администратора

Создать файл `create_admin.php` в корне проекта:

```php
<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Spatie\Permission\Models\Role;
use App\Models\User;

Role::firstOrCreate(['name' => 'admin',    'guard_name' => 'web']);
Role::firstOrCreate(['name' => 'operator', 'guard_name' => 'web']);

$user = User::firstOrCreate(
    ['email' => 'admin@your-domain.com'],
    ['name' => 'Admin', 'password' => bcrypt('your-password')]
);
$user->assignRole('admin');
echo "Done: " . $user->email . PHP_EOL;
```

```bash
php create_admin.php
rm create_admin.php
```

### 7. Настроить Apache

Создать файл `/etc/apache2/sites-available/booking_saver.conf`:

```apache
<VirtualHost *:80>
    ServerName your-domain.com
    DocumentRoot /var/www/booking_saver/public

    <Directory /var/www/booking_saver/public>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/booking_saver_error.log
    CustomLog ${APACHE_LOG_DIR}/booking_saver_access.log combined
</VirtualHost>
```

```bash
a2enmod rewrite
a2ensite booking_saver.conf
systemctl reload apache2
```

### 8. Права на папки

```bash
chown -R www-data:www-data /var/www/booking_saver/storage
chown -R www-data:www-data /var/www/booking_saver/bootstrap/cache
chmod -R 775 /var/www/booking_saver/storage
chmod -R 775 /var/www/booking_saver/bootstrap/cache
```

### 9. Кэш для production

```bash
php artisan optimize

# На Hellootel:
/opt/php84/bin/php artisan optimize
```

Очистить кэш (если что-то пошло не так):
```bash
php artisan optimize:clear

# На Hellootel:
/opt/php84/bin/php artisan optimize:clear
```

---

## Адреса

| URL | Описание |
|-----|----------|
| `https://your-domain.com/` | Редирект на `/admin` |
| `https://your-domain.com/admin` | Вход в adminку |
| `https://your-domain.com/api/v1/extension/...` | API для расширения |

---

## Структура проекта

```
booking_saver/
├── app/
│   ├── Enums/User/Role.php                  — роли (admin, operator)
│   ├── Http/Controllers/Api/
│   │   └── ExtensionController.php          — API для расширения
│   ├── Http/Middleware/ApiTokenAuth.php     — авторизация по Bearer-токену
│   ├── Models/
│   │   ├── ExtensionBooking.php             — сырые брони
│   │   ├── ProcessedBooking.php             — обработанные брони (для HelloOtel)
│   │   ├── ExtensionParser.php              — конфиги парсеров
│   │   ├── ExtensionParserRule.php          — правила доменов
│   │   └── ExtensionPageReport.php          — HTML-снимки страниц
│   ├── Services/
│   │   ├── BookingProcessorService.php      — сырая бронь → ProcessedBooking
│   │   ├── HellOotelLookupService.php       — справочники HelloOtel (отели, типы номеров)
│   │   ├── HellOotelReservationService.php  — отправка брони в HelloOtel
│   │   └── ParserEngineSimulator.php        — PHP-порт config-engine для тестов
│   ├── Console/Commands/
│   │   └── TestParserCommand.php            — artisan parser:test
│   └── Filament/Resources/                  — интерфейс adminки
├── chrome-extension/                        — публичная сборка для Chrome Web Store
│   ├── manifest.json
│   ├── auth.js                              — URL API и авторизация
│   ├── background.js                        — service worker
│   ├── content.js / content.css             — инъекция кнопок на страницы
│   ├── popup.html / popup.js / popup.css    — попап расширения
│   └── parsers/
│       ├── registry.js                      — реестр парсеров
│       └── config-engine.js                 — движок DB-парсеров
├── chrome-extension-dev/                    — dev-сборка (<all_urls>, "Send to Developer")
│   ├── ... (всё что у prod, синхронизируется через cp)
│   ├── dev-reporter.css
│   ├── dev-reporter-popup.js                — кнопка «📤 Send to Developer»
│   └── dev-reporter-bg.js                   — handler SEND_PAGE_REPORT в SW
├── database/migrations/
├── routes/
│   ├── web.php                              — веб-маршруты
│   └── api.php                              — API-маршруты
└── .docs/                                   — внутренняя документация (architecture, парсеры, CWS)
```

См. [.docs/architecture.md](.docs/architecture.md) — про разделение prod/dev сборок и поток обработки брони.

---

## API расширения

Базовый URL: `https://your-domain.com/api/v1/extension`

| Метод | Эндпоинт | Авторизация | Описание |
|-------|----------|-------------|----------|
| POST | `/login` | — | Вход, возвращает Bearer-токен |
| GET | `/parsers` | — | Список активных парсеров (JSON) |
| GET | `/parser-rules` | — | Правила доменов |
| POST | `/page-report` | — | Отправить HTML страницы разработчику |
| GET | `/bookings` | Bearer | Брони текущего пользователя |
| POST | `/bookings` | Bearer | Сохранить бронь |
| DELETE | `/bookings/{id}` | Bearer | Удалить бронь |

---

## Настройка Chrome-расширения

### 1. Указать URL сервера

Открыть `chrome-extension/auth.js` и заменить адрес API:

```js
const API_BASE = "https://your-domain.com/api/v1/extension";
```

### 2. Установить расширение в Chrome

1. Открыть `chrome://extensions`
2. Включить **Developer mode** (переключатель в правом верхнем углу)
3. Нажать **Load unpacked**
4. Выбрать папку `chrome-extension/`

### 3. Войти в расширение

Открыть попап расширения → ввести email и пароль администратора.

### 4. Обновление расширения после изменений кода

1. Открыть `chrome://extensions`
2. Нажать кнопку **Reload** (⟳) на карточке расширения
3. Обновить страницу с бронями

---

## Управление парсерами

Adminка → раздел **Chrome Extension**

### Parsers — конфигурация парсеров

Каждый парсер описывает как извлечь данные брони с конкретного сайта.

| Поле | Описание |
|------|----------|
| Name | Уникальный идентификатор парсера |
| Domain | Домен сайта без `https://` (например `booking.com`) |
| Path prefix | Необязательно. Активирует парсер только на страницах с этим путём |
| Config | JSON-конфиг (см. ниже) |
| Active | Включить / выключить без удаления |

### Parser Rules — применить парсер к другому домену

Позволяют использовать существующий парсер на другом домене с такой же вёрсткой без дублирования конфига.

| Поле | Описание |
|------|----------|
| Domain | Домен нового сайта |
| Path prefix | Необязательно, только конкретный путь |
| Parser | Выбрать существующий парсер из списка |

### Bookings — все сохранённые брони

Можно фильтровать по email пользователя, просматривать детали, удалять.

### Page Reports — HTML страниц

Страницы, отправленные кнопкой **Send to Developer** в попапе расширения. Используются для разработки новых парсеров — можно открыть страницу в iframe прямо в adminке и протестировать парсер на ней.

---

## Добавление парсера для нового сайта

1. Открыть нужную страницу с бронями в браузере
2. В попапе расширения нажать **Send to Developer** — страница сохранится в Page Reports
3. В adminке открыть Page Report → изучить структуру HTML через кнопку **Open HTML**
4. Создать новый парсер в **Parsers** с нужным JSON-конфигом
5. Расширение подхватит парсер автоматически при следующей загрузке страницы 

### Поля брони

> **Правило:** парсер извлекает **только** поля, которые отправляются в HelloOtel. Ничего «на всякий случай», ничего «для контекста в админке» — `meta_fields` блок не нужен, пока для него нет явно сформулированной задачи. Лишние поля не помогают — они увеличивают шанс валидационных ошибок (как было с `nights: "9 ночей"`, не integer) и усложняют отладку.

Обязательный набор ([.docs/send_booking.md](.docs/send_booking.md)):

| Поле | Назначение |
|------|-----------|
| `booking_code` | Номер заявки → `service_number` в HelloOtel |
| `hotel_name` | Используется для авто-сопоставления `hotel_id` |
| `subtitle` | Название типа номера → авто-сопоставление `room_type_id` |
| `stay_dates` | Парсится в `arrival_at` / `departure_at` |
| `reservation_at` | Дата создания брони → `operator_reservation_at` |
| `total_price` | Сумма (валюта определяется по символу) → `tour_price_native` + `tour_price_native_currency` |
| `tourists` | Массив `{ last_name, first_name, dob }` → `guest_name` + person_count_* |

`nights`, `transfer`, `statuses`, `details_link`, `thumbnail`, `guests` как текст и `meta_fields` блок не нужны — сервер не отправляет их в HelloOtel, person_count_* считаются из массива `tourists`, nights вычисляется из дат.

### Типы парсеров

**`card`** (по умолчанию) — список карточек броней:

```json
{
  "card": "article.booking-card",
  "button": ".card-footer",
  "fields": {
    "booking_code":   { "sel": ".ref-code" },
    "hotel_name":     { "sel": "h3.hotel-name" },
    "subtitle":       { "sel": ".room-type" },
    "total_price":    { "sel": ".price" },
    "reservation_at": { "sel": ".booked-on" }
  },
  "label_maps": [{
    "item": ".detail-row",
    "label": ".label",
    "value": ".value",
    "fields": {
      "stay_dates": ["stay", "date"]
    }
  }],
  "tourist_blocks": {
    "item": ".passenger-row",
    "fields": {
      "last_name":  { "sel": ".surname" },
      "first_name": { "sel": ".name" },
      "dob":        { "sel": ".birth-date" }
    }
  }
}
```

`tourist_blocks` поддерживает два режима. Выше — **CSS-режим** (новый): значение каждого поля туриста берётся CSS-селектором, поддерживает `sel`, `attr`, `strip_prefix`, `strip_pattern` и т.д. Старый **label-режим** (`fields: { last_name: ["фамилия"], ... }`) тоже работает — выбирается автоматически по типу spec'а.

**`form`** — страница одной брони (форма с подписями полей):

```json
{
  "type": "form",
  "container": ".booking-detail",
  "button": ".form-actions",
  "fields": {
    "booking_code": { "label_match": ["booking ref", "ref"] },
    "hotel_name":   { "label_match": ["hotel", "property"] },
    "subtitle":     { "label_match": ["room type", "room"] },
    "stay_dates":   { "label_match": ["stay dates", "dates"] },
    "total_price":  { "label_match": ["total price", "amount"] }
  }
}
```

**`table`** — таблица броней (заголовки колонок → данные):

```json
{
  "type": "table",
  "table": "table.bookings-table",
  "button_cell": "td.actions",
  "fields": {
    "booking_code": ["booking ref", "ref", "code"],
    "hotel_name":   ["hotel", "property"],
    "subtitle":     ["room type", "room"],
    "stay_dates":   ["stay dates", "dates"],
    "total_price":  ["total price", "price", "total"]
  }
}
```

---

## Тестирование парсеров

Команда `php artisan parser:test` прогоняет парсер из БД против сохранённых **Page Reports** (HTML-снимков) без браузера. Использует PHP-порт `config-engine.js` в [app/Services/ParserEngineSimulator.php](app/Services/ParserEngineSimulator.php).

```bash
# Один отчёт (по id из таблицы extension_page_reports)
php artisan parser:test 11

# Все отчёты в БД
php artisan parser:test --all

# Принудительно использовать конкретный парсер
php artisan parser:test 11 --parser=pegast

# Только заголовки, без распечатки броней (быстрая проверка кол-ва)
php artisan parser:test --all --limit=0

# Полный JSON-результат
php artisan parser:test 11 --json
```

Полезно после правок JSON-конфига: сразу видно, какие поля извлеклись из реальной страницы, без необходимости перезагружать расширение и кликать в браузере. Поддерживается тип `card` и все shared-секции конфига (`fields`, `meta_fields`, `label_maps`, `dl_maps`, `tourist_blocks` в обоих режимах). Типы `form` и `table` пока не реализованы в симуляторе — для них используйте реальное расширение.

Подбор парсера в команде идентичен `ParserRegistry.find()` в JS: ищется по `domain` среди активных, выбирается самый длинный совпадающий `path_match`; если не нашлось — fallback в `extension_parser_rules`.

---

## Роли пользователей

| Роль | Доступ к adminке | Доступ к API расширения |
|------|-----------------|------------------------|
| `admin` | Полный | Да |
| `operator` | Полный | Да |

Создать оператора (через `create_admin.php` или tinker):

```php
$user = App\Models\User::create([
    'name'     => 'Operator',
    'email'    => 'operator@your-domain.com',
    'password' => bcrypt('password'),
]);
$user->assignRole('operator');
```

---

## Локальная разработка (WSL / Ubuntu)

```bash
# Добавить локальный домен
echo "127.0.0.1    booking.localhost" | sudo tee -a /etc/hosts

# Запустить Apache
sudo systemctl start apache2

# Очистить кэш после изменений
php artisan cache:clear && php artisan view:clear
```

- Adminка: `http://booking.localhost/admin`
- API: `http://booking.localhost/api/v1/extension`

В `chrome-extension/auth.js` для локальной разработки:

```js
const API_BASE = "http://booking.localhost/api/v1/extension";
```

