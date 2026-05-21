<?php

namespace Database\Seeders;

use App\Models\ExtensionParser;
use App\Models\ExtensionParserRule;
use Illuminate\Database\Seeder;

// Сгенерировано командой: php artisan parsers:generate-seeder
// Дата: 2026-05-21 11:07:55
class ParserDataSeeder extends Seeder
{
    public function run(): void
    {
        $parsers = array (
  0 => 
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
  1 => 
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