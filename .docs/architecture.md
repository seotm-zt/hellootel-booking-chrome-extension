# Архитектура расширения

## Схема компонентов

```
┌─────────────────────────────────────────────────────────────┐
│  Chrome Extension                                           │
│                                                             │
│  ┌──────────────┐     сообщения     ┌─────────────────────┐ │
│  │  content.js  │ ◄────────────────► │  background.js      │ │
│  │              │                   │  (service worker)   │ │
│  │  - сканирует │                   │  - fetch API        │ │
│  │    страницу  │                   │  - хранит токен     │ │
│  │  - инжектит  │                   └──────────┬──────────┘ │
│  │    кнопки    │                              │            │
│  └──────┬───────┘                              │            │
│         │                                      │            │
│  ┌──────▼───────┐                              │ HTTP       │
│  │  registry.js │                              │            │
│  │              │                              ▼            │
│  │  - список    │                   ┌─────────────────────┐ │
│  │    парсеров  │                   │   booking_saver API │ │
│  │  - правила   │                   │   /api/v1/extension │ │
│  └──────┬───────┘                   └─────────────────────┘ │
│         │                                                   │
│  ┌──────▼───────┐                                           │
│  │config-engine │                                           │
│  │    .js       │                                           │
│  │  - строит    │                                           │
│  │    парсер из │                                           │
│  │    JSON      │                                           │
│  └──────────────┘                                           │
└─────────────────────────────────────────────────────────────┘
```

## Жизненный цикл при загрузке страницы

```
1. content.js загружен браузером
         │
2. boot() — параллельно:
   ├── loadParsers()  → background → GET /parsers  → регистрирует DB-парсеры
   ├── loadRules()    → background → GET /parser-rules → загружает правила доменов
   └── refreshConfirmedCodes() → background → GET /bookings → строит Set подтв./сохранённых
         │
3. queueScan()
   ├── ParserRegistry.find(location) — ищет подходящий парсер
   │   ├── проверяет remoteRules (DB-правила + правила доменов)
   │   └── fallback: hardcoded matches() в каждом парсере
   ├── isAuthorized() — проверяет токен в chrome.storage.local
   └── для каждой карточки: injectButton(card, parser)
         │
4. MutationObserver — повторяет queueScan() при изменении DOM
   (для SPA-сайтов с динамической загрузкой)
```

## Файлы расширения

| Файл | Роль |
|------|------|
| `manifest.json` | Конфигурация расширения: разрешения, content scripts |
| `auth.js` | URL API, функции `getToken()`, `isAuthorized()`, login/logout |
| `background.js` | Service worker: проксирует fetch к API, обрабатывает все типы сообщений |
| `content.js` | Инжектирует кнопки на страницу; управляет модалом подтверждения |
| `content.css` | Стили кнопок, модала и тоста |
| `popup.html/js/css` | Попап: вход в аккаунт + логин-номер, список броней (только чтение + Remove) |
| `parsers/registry.js` | Реестр парсеров, матчинг URL |
| `parsers/config-engine.js` | Движок: строит парсер из JSON-конфига |

## Ключевой принцип: изменения только на стороне сервера

**Расширение установлено у ~1000 пользователей.** Любое изменение в JS-коде расширения (`config-engine.js`, `content.js`, `background.js` и т.д.) требует полного обновления расширения у всех пользователей — это долго и неконтролируемо.

**Правило:** добавление парсера для нового сайта или изменение правил парсинга должно достигаться **исключительно через изменение JSON-конфига в БД** (adminка → Parsers). Если для нового сайта недостаточно возможностей `config-engine.js` — сначала убедитесь, что задачу нельзя решить комбинацией существующих операций (`sel`, `label_maps`, `dl_maps`, `br_map`, `tourist_blocks`, `meta_maps` и т.д.).

Если расширение действительно нужно обновить — это отдельная плановая задача с осознанным деплоем.

## Конечная цель системы

Получить из сырых данных (спарсенных с сайтов турагентств) **полноценную информацию о бронировании номера в отеле** в стандартизованном формате, совместимом с HellOotel API:

```
extension_bookings (сырые данные)
        │
        ▼  BookingProcessorService
        │  + HellOotelLookupService (матчинг hotel_id, room_type_id)
        ▼
processed_bookings (чистовые данные)
  - hotel_id, room_type_id          ← из справочника HellOotel API (/hotel/bonus-room-types)
  - hotel_vote                      ← рейтинг отеля 10–100 (звёзды×10), min:10 max:100
  - operator_id, agency_id          ← из справочника HellOotel API
  - reservation_date, arrival_at, departure_at  ← reservation_time удалено
  - tourists (ФИО, дата рождения)
  - person_count_adults/children/teens
  - price decimal(15,2), currency_code  ← расширено с decimal(10,2) для крупных сумм
  - commission decimal(15,2)
  - confirmed_by_user_id, confirmed_at  ← отметка подтверждения оператором
  - hellootel_reservation_id        ← ID брони в HelloOtel (заполняется после успешной отправки)
  - hellootel_sent_at               ← дата/время отправки в HelloOtel
  - hellootel_response              ← сырой ответ HelloOtel API (JSON)
  - payment_status_ag/rm/cm
```

## Два типа парсеров

### 1. Bundled (встроенные)
Прописаны в коде расширения в виде JS-файлов в `parsers/`. Регистрируются через `ParserRegistry.register()`. Их `matches()` содержит hardcoded URL-логику.

### 2. DB-парсеры
Хранятся в базе данных, редактируются в adminке → **Parsers**. При загрузке страницы расширение загружает их через API. `ConfigParserEngine.build(entry)` превращает JSON-конфиг в полноценный объект парсера. DB-парсер с тем же именем заменяет bundled-парсер.

## Состояния кнопки

Цвет кнопки определяется состоянием брони, загруженным с сервера при старте. Ключ сопоставления — только `booking_code` (домен не используется, так как бронь может быть сохранена с превью-страницы):

| Цвет | Текст | Смысл |
|------|-------|-------|
| Синий (активная) | Send to HelloOtel | Бронь ещё не сохранена в БД |
| Жёлтый (активная) | Update in database | Сохранена, но не подтверждена оператором |
| Оранжевый (активная) | Confirm & send to HelloOtel | Подтверждена, но ещё не отправлена в HelloOtel |
| Зелёный (disabled) | Sent to HelloOtel ✓ | Принята HelloOtel (`hellootel_reservation_id` заполнен) |

Кнопка **Cancel Send** в модале подтверждения удаляет бронь из БД и возвращает кнопку в исходное синее состояние.

## Приоритет матчинга

При вызове `ParserRegistry.find(location)`:

1. **Remote rules** — сначала проверяются правила из `remoteRules`:
   - Правила DB-парсеров (домен парсера)
   - Правила из таблицы `extension_parser_rules` (Parser Rules в adminке)
   - Более длинный `path_match` имеет приоритет над коротким/пустым

2. **Hardcoded matches()** — если правило не нашлось, проверяется `matches()` каждого зарегистрированного парсера по порядку регистрации.

## HellOotel интеграция

### Токен

Токен для HellOotel API — это `access_token` вошедшего пользователя (хранится в `users.access_token`). Он же используется как Bearer-токен для авторизации в нашем API. В CLI/Artisan контексте (нет авторизованного пользователя) используется `HELLOOTEL_API_TOKEN` из `.env` как fallback.

### Отправка брони

При подтверждении (`PATCH /bookings/{id}/confirm`) происходит:

1. Данные из формы сохраняются в `processed_bookings`
2. `HellOotelReservationService::send()` отправляет бронь на `POST /reservation/create?hotel_id=X`
3. При успехе — сохраняется `hellootel_reservation_id` и `hellootel_sent_at`
4. **Повторная отправка заблокирована**: если `hellootel_reservation_id` уже заполнен — запрос к HellOotel не делается (защита от дублей)
5. После отправки брони автоматически отправляется рейтинг (`hotel_vote`), если он выставлен

### Рейтинг отеля (hotel_vote)

- HellOotel API возвращает и принимает рейтинг в диапазоне **0–100**
- В форме расширения показывается **10 звёзд** (data-vote 1–10)
- При выборе отеля подгружается текущий рейтинг (`GET /hotel/vote?hotel_id=X`) — значение делится на 10 для отображения звёзд
- Оператор выбирает 1–10 звёзд; при подтверждении значение умножается на 10 (7 звёзд → `hotel_vote = 70`)
- Обязательное поле: подтверждение заблокировано пока не выбрана хотя бы 1 звезда
- Хранится в `processed_bookings.hotel_vote` как 0–100; Laravel-валидация: `min:10|max:100`
- В админке: **Get Vote** (обновить из HelloOtel), **Send Vote** (отправить вручную)

### Типы номеров

Загружаются через `GET /hotel/bonus-room-types?hotel_id=X` (не `/hotel/room-types`).  
Цепочка: расширение → `GET_ROOM_TYPES` → background.js → `GET /api/v1/extension/hotels/{id}/room-types` → `HellOotelLookupService::getRoomTypes()` → HellOotel API.

### Сервисы

| Класс | Роль |
|-------|------|
| `HellOotelLookupService` | Чтение: список отелей (`/hotel/search`), типы номеров (`/hotel/bonus-room-types`), рейтинг (`/hotel/vote`) |
| `HellOotelReservationService` | Запись: создание резервации (`/reservation/create`), отправка рейтинга (`/hotel/vote`) |

### Оператор

`operator_id` обязателен для HellOotel. Берётся из поля **Operator** на парсере. Если при отправке `operator_id` пустой — сервис автоматически ищет парсер по домену исходной брони (с учётом превью-страниц `booking.localhost` через `ExtensionPageReport`) и синхронизирует оператора.

## Авторизация

Расширение хранит Bearer-токен в `chrome.storage.local`. При клике на кнопку Save проверяется `isAuthorized()`. Если токен отсутствует — показывается тост с предложением войти в аккаунт через попап.

После входа в попапе отображается: `Имя Фамилия (логин)` — логин берётся из поля username формы входа (`request->username`), не из ответа HellOotel API.

## Попап расширения

Попап — только просмотр. Доступные функции:
- Вход / выход из аккаунта
- Список сохранённых броней с кнопкой Remove (удаляет из БД)
- Счётчик броней в шапке (красный бейдж)
- Отображение имени пользователя и логин-номера

## Page Report Preview

Adminка позволяет открыть сохранённую HTML-страницу в iframe и тестировать парсер прямо на ней. Чтобы content script знал, для какого URL предназначена страница, сервер вставляет `<meta name="ttb-preview-url" content="...">` в `<head>`. `content.js` читает эту мета-тег через `getEffectiveLocation()` и использует URL из неё вместо реального `window.location`.
