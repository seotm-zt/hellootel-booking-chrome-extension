# Поля payload при отправке брони в HellOotel

## POST /reservation/create?hotel_id={id}

| Поле | Источник в processed_bookings |
|------|-------------------------------|
| `arrival_at` | `arrival_at` (формат `Y-m-d`) |
| `departure_at` | `departure_at` (формат `Y-m-d`) |
| `room_type` | `room_type_id` |
| `person_count_adults` | `person_count_adults` |
| `person_count_teens` | `person_count_teens` |
| `person_count_children` | `person_count_children` |
| `guest_name` | `guest_info` → если пусто: собирается из массива `tourists` (ФАМИЛИЯ ИМЯ через запятую) |
| `operator_id` | `operator_id` → если пусто: автосинхронизируется из парсера перед отправкой |
| `operator_reservation_at` | `reservation_date` |
| `service_number` | `booking_code` |
| `tour_price_native` | `price` |
| `tour_price_native_currency` | `currency_code` |

Поля со значением `null` или `""` не включаются в payload.

При успехе ответ содержит `id` — сохраняется в `hellootel_reservation_id`.  
Повторный вызов `send()` при уже заполненном `hellootel_reservation_id` не делает запрос к API.

## POST /hotel/vote?hotel_id={id}

| Поле | Источник |
|------|----------|
| `vote` | `hotel_vote` (целое 0–100) |

Отправляется автоматически после успешного `send()` если `hotel_vote` заполнен.  
Можно отправить вручную кнопкой **Send Vote** в админке.

## GET /hotel/vote?hotel_id={id}

Возвращает текущий рейтинг (целое число 0–100) для отеля.  
Вызывается автоматически при выборе отеля в попап-форме расширения.

> **Шкала**: API использует 0–100. В форме расширения показывается 10 звёзд —
> при получении значение делится на 10, при отправке умножается на 10.

## GET /hotel/bonus-room-types?hotel_id={id}&arrival_at={Y-m-d}&departure_at={Y-m-d}

Возвращает список типов номеров для отеля.  
Используется в форме подтверждения брони (поле **Room type**).  
`/hotel/room-types` **не используется**.

> **Важно:** HellOotel возвращает только типы номеров, доступные на указанный период. Если `arrival_at`/`departure_at` не переданы, `HellOotelLookupService::getRoomTypes()` подставляет «вчера/сегодня» как fallback, что может скрыть нужные варианты. В Confirm-модале расширения даты пробрасываются автоматически: при первой загрузке — из распарсенных `pre.arrivalAt/departureAt`, при изменении полей `#ttb-arrival`/`#ttb-departure` — список перезагружается с новыми датами.

## GET /country/list, /city/list

Справочники стран и городов. Расширение получает их один раз при `boot()` через прокси-роуты `/api/v1/extension/countries` и `/api/v1/extension/cities`, кэширует в памяти как `{id: name}` и резолвит `country_id`/`city_id` из ответа `/hotels?q=…` в подсветку «Страна, Город» под полем выбора отеля (цвет `#f0592b`).

## Direct mode — когда парсер не нашёл туристов

Если `raw.tourists.length === 0` после `parseCard`:
1. `POST /bookings` **не вызывается** — сырая бронь не сохраняется
2. Открывается confirm-модалка в direct-режиме: «Guests» помечен `*` обязательным, кнопка `Cancel Send` (delete from DB) скрыта
3. Пользователь вручную добавляет туристов; `Confirm` активна только когда есть ≥1 заполненная строка
4. На Confirm → **`POST /api/v1/extension/processed-bookings/direct`** с тем же payload что и обычный confirm
5. Сервер создаёт `ProcessedBooking` с `source_booking_id = null`, ставит `confirmed_at = now()`, шлёт в HelloOtel через `HellOotelReservationService::send()`

## Повторное сохранение брони со статусом «Hotel not found»

Если после первого сохранения отель не нашёлся в HellOotel — пользователь может завести его в HellOotel и кликнуть кнопку повторно. `BookingProcessorService::process()` в ветке «уже обработано» теперь ретраит `matchHotelAndRoom` если `processed.hotel_id` всё ещё null: успешный матч → запись `hotel_id`/`room_type_*` обновляется → ответ возвращает заполненный `hotel_match` → кнопка переходит в состояние «Confirm & send to HelloOtel».

## Удалённые поля

| Поле | Статус |
|------|--------|
| `reservation_time` | Удалено из формы, API, БД (миграция 2026_05_21) |
| `meal_plan` | Удалено из формы, API, БД (миграция 2026_05_28). План питания не отправляется в HelloOtel, поле было пыли-собирателем |
