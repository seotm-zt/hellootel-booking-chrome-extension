<?php

namespace Database\Seeders;

use App\Models\ExtensionParser;
use App\Models\ExtensionParserRule;
use Illuminate\Database\Seeder;

// Сгенерировано командой: php artisan parsers:generate-seeder
// Дата: 2026-05-10 22:30:20
class ParserDataSeeder extends Seeder
{
    public function run(): void
    {
        $parsers = array (
  0 => 
  array (
    'name' => 'demo-bookings',
    'domain' => '57.128.254.23',
    'path_match' => '/demo/bookings-demo',
    'config' => 
    array (
      'card' => 'article.booking-card',
      'fields' => 
      array (
        'guests' => 
        array (
          'br_map' => 'p:nth-of-type(2)',
          'key_match' => 
          array (
            0 => 'guest',
          ),
        ),
        'statuses' => 
        array (
          'sel' => 'strong > span',
          'multi' => true,
        ),
        'subtitle' => 
        array (
          'seps' => 
          array (
            0 => ' · ',
          ),
          'p_subtitle' => 'p:first-of-type',
        ),
        'transfer' => 
        array (
          'br_map' => 'p:nth-of-type(2)',
          'key_match' => 
          array (
            0 => 'transfer',
          ),
        ),
        'meal_plan' => 
        array (
          'br_map' => 'p:nth-of-type(2)',
          'key_match' => 
          array (
            0 => 'meal',
          ),
        ),
        'thumbnail' => 
        array (
          'sel' => 'img',
          'attr' => 'src',
        ),
        'hotel_name' => 
        array (
          'seps' => 
          array (
            0 => ' · ',
          ),
          'h_hotel' => 'h3',
          'location_p' => 'p:first-of-type',
        ),
        'stay_dates' => 
        array (
          'br_map' => 'p:nth-of-type(2)',
          'key_match' => 
          array (
            0 => 'date',
          ),
        ),
        'total_price' => 
        array (
          'sel' => 'p:last-of-type strong',
          'strip_prefix' => 'Total Cost:',
        ),
        'booking_code' => 
        array (
          'h_code' => 'h3',
        ),
        'details_link' => 
        array (
          'sel' => 'a.btn-detail',
          'attr' => 'href',
        ),
      ),
    ),
    'is_active' => true,
    'notes' => 'Demo page — h3 code/hotel split, br-line details, · separator',
  ),
  1 => 
  array (
    'name' => 'demo-bookings2',
    'domain' => '57.128.254.23',
    'path_match' => '/demo/bookings-demo2',
    'config' => 
    array (
      'card' => '#app-root article.card',
      'fields' => 
      array (
        'guests' => 
        array (
          'br_map' => 'p:nth-of-type(2)',
          'key_match' => 
          array (
            0 => 'guest',
          ),
        ),
        'statuses' => 
        array (
          'sel' => 'strong > span',
          'multi' => true,
        ),
        'subtitle' => 
        array (
          'seps' => 
          array (
            0 => ' · ',
          ),
          'p_subtitle' => 'p:first-of-type',
        ),
        'transfer' => 
        array (
          'br_map' => 'p:nth-of-type(2)',
          'key_match' => 
          array (
            0 => 'transfer',
          ),
        ),
        'meal_plan' => 
        array (
          'br_map' => 'p:nth-of-type(2)',
          'key_match' => 
          array (
            0 => 'meal',
          ),
        ),
        'thumbnail' => 
        array (
          'sel' => 'img',
          'attr' => 'src',
        ),
        'hotel_name' => 
        array (
          'seps' => 
          array (
            0 => ' · ',
          ),
          'h_hotel' => 'h3',
          'location_p' => 'p:first-of-type',
        ),
        'stay_dates' => 
        array (
          'br_map' => 'p:nth-of-type(2)',
          'key_match' => 
          array (
            0 => 'date',
          ),
        ),
        'total_price' => 
        array (
          'sel' => 'p:last-of-type strong',
          'strip_prefix' => 'Total Cost:',
        ),
        'booking_code' => 
        array (
          'h_code' => 'h3',
        ),
      ),
    ),
    'is_active' => true,
    'notes' => 'Demo page 2 — JS-rendered cards inside #app-root, no detail link',
  ),
  2 => 
  array (
    'name' => 'demo-bookings4',
    'domain' => '57.128.254.23',
    'path_match' => '/demo/bookings-demo4',
    'config' => 
    array (
      'card' => 'article.booking-card',
      'fields' => 
      array (
        'guests' => 
        array (
          'br_map' => 'p:nth-of-type(2)',
          'key_match' => 
          array (
            0 => 'guest',
          ),
        ),
        'statuses' => 
        array (
          'sel' => 'strong > span',
          'multi' => true,
        ),
        'subtitle' => 
        array (
          'seps' => 
          array (
            0 => '. ',
            1 => ' · ',
            2 => ' A ',
          ),
          'p_subtitle' => 'p:first-of-type',
        ),
        'transfer' => 
        array (
          'br_map' => 'p:nth-of-type(2)',
          'key_match' => 
          array (
            0 => 'transfer',
          ),
        ),
        'meal_plan' => 
        array (
          'br_map' => 'p:nth-of-type(2)',
          'key_match' => 
          array (
            0 => 'meal',
          ),
        ),
        'thumbnail' => 
        array (
          'sel' => 'img',
          'attr' => 'src',
        ),
        'hotel_name' => 
        array (
          'seps' => 
          array (
            0 => '. ',
            1 => ' · ',
            2 => ' A ',
          ),
          'h_hotel' => 'h3',
          'location_p' => 'p:first-of-type',
        ),
        'stay_dates' => 
        array (
          'br_map' => 'p:nth-of-type(2)',
          'key_match' => 
          array (
            0 => 'date',
          ),
        ),
        'total_price' => 
        array (
          'sel' => 'p:last-of-type strong',
          'strip_prefix' => 'Total Cost:',
        ),
        'booking_code' => 
        array (
          'h_code' => 'h3',
        ),
      ),
    ),
    'is_active' => true,
    'notes' => 'Demo page 4 — port 3003, base64-encoded page, ". " separator',
  ),
  3 => 
  array (
    'name' => 'demo-order2',
    'domain' => 'tour.localhost',
    'path_match' => '/order2.html',
    'config' => 
    array (
      'card' => 'article.bk-row',
      'button' => '.bk-row-foot',
      'fields' => 
      array (
        'statuses' => 
        array (
          'sel' => '.bk-status-chip',
          'multi' => true,
          'strip_icons' => true,
        ),
        'subtitle' => 
        array (
          'sel' => '.bk-tag-pill',
          'strip_icons' => true,
        ),
        'thumbnail' => 
        array (
          'sel' => '.bk-thumb',
          'attr' => 'src',
        ),
        'hotel_name' => 
        array (
          'sel' => '.bk-hotel',
          'append_location' => '.bk-location',
        ),
        'total_price' => 
        array (
          'sel' => '.bk-price-tag',
        ),
        'booking_code' => 
        array (
          'data' => 'ref',
          'fallback' => '.bk-ref',
        ),
        'details_link' => 
        array (
          'sel' => '.bk-btn-view',
          'attr' => 'href',
        ),
      ),
      'dl_maps' => 
      array (
        0 => 
        array (
          'key' => 'dt',
          'item' => '.bk-detail',
          'value' => 'dd',
          'fields' => 
          array (
            'guests' => 
            array (
              0 => 'guests',
              1 => 'guest',
            ),
            'transfer' => 
            array (
              0 => 'transfer',
            ),
            'meal_plan' => 
            array (
              0 => 'meal plan',
              1 => 'meal',
            ),
            'stay_dates' => 
            array (
              0 => 'stay dates',
              1 => 'stay',
            ),
          ),
          'container' => 'dl.bk-details',
        ),
      ),
    ),
    'is_active' => true,
    'notes' => 'Demo order2 — dl/dt+dd details, strip_icons for status/tag, data-ref',
  ),
  4 => 
  array (
    'name' => 'demo-order3',
    'domain' => 'tour.localhost',
    'path_match' => '/order3.html',
    'config' => 
    array (
      'type' => 'form',
      'button' => '.ord3-actions',
      'fields' => 
      array (
        'guests' => 
        array (
          'label_match' => 
          array (
            0 => 'guests',
            1 => 'pax',
          ),
        ),
        'statuses' => 
        array (
          'as_array' => true,
          'label_match' => 
          array (
            0 => 'status',
            1 => 'booking status',
          ),
        ),
        'transfer' => 
        array (
          'label_match' => 
          array (
            0 => 'transfer',
          ),
        ),
        'meal_plan' => 
        array (
          'label_match' => 
          array (
            0 => 'meal plan',
            1 => 'meal',
            2 => 'board',
          ),
        ),
        'hotel_name' => 
        array (
          'label_match' => 
          array (
            0 => 'hotel',
            1 => 'property',
          ),
        ),
        'stay_dates' => 
        array (
          'label_match' => 
          array (
            0 => 'stay dates',
            1 => 'dates',
          ),
        ),
        'total_price' => 
        array (
          'label_match' => 
          array (
            0 => 'total price',
            1 => 'price',
            2 => 'amount',
          ),
        ),
        'booking_code' => 
        array (
          'label_match' => 
          array (
            0 => 'booking ref',
            1 => 'ref',
          ),
        ),
      ),
      'container' => '.ord3-page',
    ),
    'is_active' => true,
    'notes' => 'Demo order3 — single booking detail form, label→input matching',
  ),
  5 => 
  array (
    'name' => 'demo-order4',
    'domain' => 'tour.localhost',
    'path_match' => '/order4.html',
    'config' => 
    array (
      'type' => 'table',
      'table' => '.ord4-table',
      'fields' => 
      array (
        'guests' => 
        array (
          0 => 'guests',
          1 => 'pax',
        ),
        'statuses' => 
        array (
          'as_array' => true,
          'keywords' => 
          array (
            0 => 'status',
          ),
        ),
        'transfer' => 
        array (
          0 => 'transfer',
        ),
        'meal_plan' => 
        array (
          0 => 'meal plan',
          1 => 'meal',
          2 => 'board',
        ),
        'hotel_name' => 
        array (
          0 => 'hotel',
          1 => 'property',
        ),
        'stay_dates' => 
        array (
          0 => 'stay dates',
          1 => 'dates',
        ),
        'total_price' => 
        array (
          0 => 'total price',
          1 => 'price',
          2 => 'total',
        ),
        'booking_code' => 
        array (
          0 => 'booking ref',
          1 => 'ref',
          2 => 'code',
        ),
      ),
      'button_cell' => '.ord4-actions',
    ),
    'is_active' => true,
    'notes' => 'Demo order4 — table-based booking list, column header mapping',
  ),
  6 => 
  array (
    'name' => 'toptravel',
    'domain' => 'toptravel.tours',
    'path_match' => '/order.html',
    'config' => 
    array (
      'card' => 'article.order-card',
      'button' => '.order-card-foot',
      'fields' => 
      array (
        'statuses' => 
        array (
          'sel' => '.order-badge',
          'multi' => true,
          'strip_icons' => true,
        ),
        'subtitle' => 
        array (
          'sel' => '.order-card-top .text-15.text-light-1',
        ),
        'thumbnail' => 
        array (
          'sel' => '.order-thumb',
          'attr' => 'src',
        ),
        'hotel_name' => 
        array (
          'sel' => 'h2.text-22, h2.fw-600',
        ),
        'total_price' => 
        array (
          'sel' => '.order-price',
        ),
        'booking_code' => 
        array (
          'sel' => '.order-code',
        ),
        'details_link' => 
        array (
          'sel' => '.order-card-foot a',
          'attr' => 'href',
        ),
      ),
      'label_maps' => 
      array (
        0 => 
        array (
          'item' => '.order-meta-item',
          'label' => '.label',
          'value' => '.value',
          'fields' => 
          array (
            'guests' => 
            array (
              0 => 'guest',
            ),
            'transfer' => 
            array (
              0 => 'transfer',
            ),
            'meal_plan' => 
            array (
              0 => 'meal',
              1 => 'board',
            ),
            'stay_dates' => 
            array (
              0 => 'stay',
              1 => 'date',
            ),
          ),
        ),
      ),
    ),
    'is_active' => true,
    'notes' => 'toptravel.tours order page — label/value meta grid, strip_icons badges',
  ),
  7 => 
  array (
    'name' => 'hellootel-transfer-history',
    'domain' => 'demo.velikolepniy-vek.com',
    'path_match' => '/transfer/book/history',
    'config' => 
    array (
      'card' => '.transfer-reservation-history__item',
      'button' => '.transfer-reservation-history__item-header',
      'fields' => 
      array (
        'statuses' => 
        array (
          'sel' => '.transfer-reservation-history__item-header-col:nth-child(7) div:last-child',
          'multi' => true,
        ),
        'subtitle' => 
        array (
          'sel' => '.transfer-reservation-history__item-header-col:nth-child(2)',
        ),
        'hotel_name' => 
        array (
          'sel' => '.transfer-reservation-history__item-header-col:nth-child(5)',
        ),
        'stay_dates' => 
        array (
          'sel' => '.transfer-reservation-history__item-header-col:nth-child(4) div:last-child',
        ),
        'total_price' => 
        array (
          'sel' => '.transfer-reservation-history__item-header-col:nth-child(6)',
          'strip_prefix' => 'Стоимость / комиссия:',
        ),
        'booking_code' => 
        array (
          'sel' => '.transfer-reservation-history__item-header-col:first-child',
        ),
      ),
      'meta_maps' => 
      array (
        0 => 
        array (
          'item' => '.transfer-reservation-history__item-sub-header-col',
          'label' => 'label',
          'value' => 'span',
          'fields' => 
          array (
            'to' => 
            array (
              0 => 'куда',
            ),
            'from' => 
            array (
              0 => 'откуда',
            ),
            'vehicle' => 
            array (
              0 => 'автомобиль',
            ),
            'direction' => 
            array (
              0 => 'направление',
            ),
          ),
        ),
      ),
      'label_maps' => 
      array (
        0 => 
        array (
          'item' => '.transfer-reservation-history__item-sub-header-col',
          'label' => 'label',
          'value' => 'span',
          'fields' => 
          array (
            'adults' => 
            array (
              0 => 'взрослых',
            ),
            'infants' => 
            array (
              0 => 'младенцев',
            ),
            'children' => 
            array (
              0 => 'детей',
            ),
            'transfer' => 
            array (
              0 => 'время трансфера',
            ),
          ),
        ),
      ),
      'tourist_blocks' => 
      array (
        'item' => '.transfer-reservation-history__item-info-row',
        'label' => 'label',
        'value' => 'span',
        'fields' => 
        array (
          'dob' => 
          array (
            0 => 'дата рождения',
          ),
          'last_name' => 
          array (
            0 => 'фамилия',
          ),
          'first_name' => 
          array (
            0 => 'имя',
          ),
        ),
      ),
    ),
    'is_active' => true,
    'notes' => NULL,
  ),
  8 => 
  array (
    'name' => 'HellOotel — История бронирований',
    'domain' => 'demo.velikolepniy-vek.com',
    'path_match' => '/hotel/book/history',
    'config' => 
    array (
      'card' => '.hotel-reservation-history__item',
      'type' => 'card',
      'button' => '.hotel-reservation-history__item-header',
      'fields' => 
      array (
        'nights' => 
        array (
          'sel' => '.hotel-reservation-history__item-header .hotel-reservation-history__item-header-col:nth-child(5)',
          'strip_prefix' => 'Ночей:',
        ),
        'statuses' => 
        array (
          'sel' => '.hotel-reservation-history__item-header .hotel-reservation-history__item-header-col:nth-child(7)',
          'multi' => true,
          'strip_prefix' => 'Статус:',
        ),
        'subtitle' => 
        array (
          'sel' => '.hotel-reservation-history__item-body h2',
          'strip_prefix' => 'Тип номера:',
          'strip_pattern' => ' *[(][0-9]{2}[.][0-9]{2}[.][0-9]{4}[^)]*[)]$',
        ),
        'hotel_name' => 
        array (
          'sel' => '.hotel-reservation-history__item-header .hotel-reservation-history__item-header-col:nth-child(3) > div:nth-child(1)',
          'strip_prefix' => 'Отель:',
        ),
        'stay_dates' => 
        array (
          'sel' => '.hotel-reservation-history__item-header .hotel-reservation-history__item-header-col:nth-child(3) > div:nth-child(2)',
        ),
        'total_price' => 
        array (
          'sel' => '.hotel-reservation-history__item-header .hotel-reservation-history__item-header-col:nth-child(6)',
          'strip_prefix' => 'Стоимость без перелета / комиссия:',
        ),
        'booking_code' => 
        array (
          'sel' => '.hotel-reservation-history__item-header-col:nth-child(1)',
        ),
        'reservation_at' => 
        array (
          'sel' => '.hotel-reservation-history__item-header .hotel-reservation-history__item-header-col:nth-child(4)',
          'strip_prefix' => 'Забронирован:',
        ),
      ),
      'meta_maps' => 
      array (
        0 => 
        array (
          'item' => '.hotel-reservation-history__item-sub-header-col, .tour-reservation-history__item-sub-header-col',
          'label' => 'label',
          'value' => 'span',
          'fields' => 
          array (
            'agency' => 
            array (
              0 => 'агентство',
            ),
            'manager' => 
            array (
              0 => 'менеджер',
            ),
            'bed_type' => 
            array (
              0 => 'тип кровати',
            ),
            'price_to_pay' => 
            array (
              0 => 'цена к оплате',
            ),
          ),
        ),
      ),
      'label_maps' => 
      array (
        0 => 
        array (
          'item' => '.hotel-reservation-history__item-sub-header-col, .tour-reservation-history__item-sub-header-col',
          'label' => 'label',
          'value' => 'span',
          'fields' => 
          array (
            'adults' => 
            array (
              0 => 'взрослых',
            ),
            'infants' => 
            array (
              0 => 'младенцев',
            ),
            'children' => 
            array (
              0 => 'детей',
            ),
          ),
        ),
      ),
      'tourist_blocks' => 
      array (
        'item' => '.hotel-reservation-history__item-info-row',
        'label' => 'label',
        'value' => 'span',
        'fields' => 
        array (
          'dob' => 
          array (
            0 => 'дата рождения',
          ),
          'last_name' => 
          array (
            0 => 'фамилия',
          ),
          'first_name' => 
          array (
            0 => 'имя',
          ),
        ),
      ),
    ),
    'is_active' => true,
    'notes' => NULL,
  ),
  9 => 
  array (
    'name' => 'CoralAgency — Заявки',
    'domain' => 'coralagency.ru',
    'path_match' => '/reservation/search',
    'config' => 
    array (
      'card' => 'div.box[data-voucher]',
      'type' => 'card',
      'button' => '.content-box',
      'fields' => 
      array (
        'guests' => 
        array (
          'sel' => '.travel-line .name',
        ),
        'statuses' => 
        array (
          'sel' => '.travel-status',
          'multi' => true,
          'strip_icons' => true,
        ),
        'subtitle' => 
        array (
          'sel' => '.travel-line.text-description a',
          'strip_pattern' => ', .*$',
        ),
        'hotel_name' => 
        array (
          'sel' => '.travel-line.text-description a',
          'strip_pattern' => '^[^,]+, ',
        ),
        'stay_dates' => 
        array (
          'sel' => '.travel-line .date',
        ),
        'total_price' => 
        array (
          'sel' => '.price.wow',
        ),
        'booking_code' => 
        array (
          'data' => 'voucher',
        ),
        'details_link' => 
        array (
          'sel' => '.travel-line.text-description a',
          'attr' => 'href',
        ),
        'reservation_at' => 
        array (
          'sel' => '.reservation-date',
        ),
      ),
      'dl_maps' => 
      array (
        0 => 
        array (
          'key' => '.text1',
          'item' => 'li',
          'value' => '.text2',
          'fields' => 
          array (
            'guests' => 
            array (
              0 => 'состав',
            ),
            'subtitle' => 
            array (
              0 => 'страна',
            ),
            'meal_plan' => 
            array (
              0 => 'питание',
            ),
            'hotel_name' => 
            array (
              0 => 'отель',
            ),
          ),
          'container' => '.reservation-tab.residence .tab-list',
        ),
      ),
      'meta_fields' => 
      array (
        'agency' => 
        array (
          'sel' => '.reservation-info',
        ),
        'manager' => 
        array (
          'sel' => '.reservation-name',
        ),
        'end_date' => 
        array (
          'data' => 'enddate',
        ),
      ),
      'tourist_blocks' => 
      array (
        'item' => '.reservation-tab.tourists table tbody tr',
        'label' => 'span.head',
        'fields' => 
        array (
          'dob' => 
          array (
            0 => 'дата рождения',
          ),
          'gender' => 
          array (
            0 => 'пол',
          ),
          'full_name' => 
          array (
            0 => 'имя фамилия',
            1 => 'фамилия имя',
          ),
          'passport_exp' => 
          array (
            0 => 'срок действия',
          ),
          'passport_number' => 
          array (
            0 => 'номер',
          ),
          'passport_series' => 
          array (
            0 => 'серия',
          ),
        ),
        'td_text' => true,
      ),
      'button_placement' => 'after',
    ),
    'is_active' => true,
    'notes' => 'Список заявок агентства на coralagency.ru/reservation/search. Поля из data-атрибутов div.box и видимых span.',
  ),
);

        foreach ($parsers as $row) {
            ExtensionParser::updateOrCreate(
                ['name' => $row['name']],
                [
                    'domain'     => $row['domain'],
                    'path_match' => $row['path_match'],
                    'config'     => $row['config'],
                    'is_active'  => $row['is_active'],
                    'notes'      => $row['notes'],
                ]
            );
        }

        $rules = array (
  0 => 
  array (
    'domain' => 'demo.velikolepniy-vek.com',
    'path_match' => '/hotel/book/history',
    'parser' => 'HellOotel — История бронирований',
    'notes' => NULL,
  ),
  1 => 
  array (
    'domain' => 'coralagency.ru',
    'path_match' => '/reservation/search',
    'parser' => 'CoralAgency — Заявки',
    'notes' => 'Список заявок агентства',
  ),
);

        foreach ($rules as $row) {
            ExtensionParserRule::updateOrCreate(
                ['domain' => $row['domain'], 'path_match' => $row['path_match']],
                [
                    'parser' => $row['parser'],
                    'notes'  => $row['notes'],
                ]
            );
        }
    }
}