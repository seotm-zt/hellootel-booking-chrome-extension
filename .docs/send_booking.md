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

## GET /hotel/bonus-room-types?hotel_id={id}

Возвращает список типов номеров для отеля.  
Используется в форме подтверждения брони (поле **Room type**).  
`/hotel/room-types` **не используется**.

## Удалённые поля

| Поле | Статус |
|------|--------|
| `reservation_time` | Удалено из формы, API, БД (миграция 2026_05_21) |
