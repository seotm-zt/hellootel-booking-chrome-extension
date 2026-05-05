# Booking Saver

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
APP_NAME="Booking Saver"
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
│   ├── Http/Middleware/ApiTokenAuth.php      — авторизация по Bearer-токену
│   ├── Models/
│   │   ├── ExtensionBooking.php             — сохранённые брони
│   │   ├── ExtensionParser.php              — конфиги парсеров
│   │   ├── ExtensionParserRule.php          — правила доменов
│   │   └── ExtensionPageReport.php          — отчёты страниц
│   └── Filament/Resources/                  — интерфейс adminки
├── chrome-extension/                        — код Chrome-расширения
│   ├── manifest.json
│   ├── auth.js                              — URL API и авторизация
│   ├── background.js                        — service worker
│   ├── content.js / content.css             — инъекция кнопок Save на страницы
│   ├── popup.html / popup.js / popup.css    — попап расширения
│   └── parsers/
│       ├── registry.js                      — реестр парсеров
│       └── config-engine.js                 — движок DB-парсеров
├── database/migrations/
└── routes/
    ├── web.php                              — веб-маршруты
    └── api.php                              — API-маршруты
```

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

### Типы парсеров

**`card`** (по умолчанию) — список карточек броней:

```json
{
  "card": "article.booking-card",
  "button": ".card-footer",
  "fields": {
    "booking_code": { "sel": ".ref-code" },
    "hotel_name":   { "sel": "h3.hotel-name" },
    "total_price":  { "sel": ".price" },
    "statuses":     { "sel": ".status-badge", "multi": true }
  },
  "label_maps": [{
    "item": ".detail-row",
    "label": ".label",
    "value": ".value",
    "fields": {
      "stay_dates": ["stay", "date"],
      "guests":     ["guest"],
      "meal_plan":  ["meal", "board"],
      "transfer":   ["transfer"]
    }
  }]
}
```

**`form`** — страница одной брони (форма с подписями полей):

```json
{
  "type": "form",
  "container": ".booking-detail",
  "button": ".form-actions",
  "fields": {
    "booking_code": { "label_match": ["booking ref", "ref"] },
    "hotel_name":   { "label_match": ["hotel", "property"] },
    "stay_dates":   { "label_match": ["stay dates", "dates"] },
    "guests":       { "label_match": ["guests", "pax"] },
    "meal_plan":    { "label_match": ["meal plan", "board"] },
    "transfer":     { "label_match": ["transfer"] },
    "total_price":  { "label_match": ["total price", "amount"] },
    "statuses":     { "label_match": ["status"], "as_array": true }
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
    "stay_dates":   ["stay dates", "dates"],
    "guests":       ["guests", "pax"],
    "meal_plan":    ["meal plan", "meal"],
    "transfer":     ["transfer"],
    "total_price":  ["total price", "price", "total"],
    "statuses":     { "keywords": ["status"], "as_array": true }
  }
}
```

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

