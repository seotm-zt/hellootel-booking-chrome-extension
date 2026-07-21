<?php

namespace Database\Seeders;

use App\Models\ExtensionParser;
use App\Models\ExtensionParserRule;
use Illuminate\Database\Seeder;

// Сгенерировано командой: php artisan parsers:generate-seeder
// Дата: 2026-07-21 12:03:02
// Полная замена: парсеры/правила, которых нет в этом сидере, удаляются.
class ParserDataSeeder extends Seeder
{
    public function run(): void
    {
        $parsers = array (
  0 => 
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
            'subtitle' => 
            array (
              0 => 'страна',
            ),
            'hotel_name' => 
            array (
              0 => 'отель',
            ),
          ),
          'container' => '.reservation-tab.residence .tab-list',
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
    'operator_id' => 15,
    'operator_name' => NULL,
    'notes' => 'Список заявок агентства на coralagency.ru/reservation/search. Поля из data-атрибутов div.box и видимых span.',
  ),
  1 => 
  array (
    'name' => 'Пегас тур',
    'domain' => 'agency.pegast.ru',
    'path_match' => '/MyAccount/Bookings',
    'config' => 
    array (
      'card' => 'li.bookings-list__item',
      'button' => '.booking-head__left-block-top',
      'fields' => 
      array (
        'subtitle' => 
        array (
          'sel' => '.main-column__accommodation .inplace-tooltip',
        ),
        'hotel_name' => 
        array (
          'sel' => '.booking-hotel-service__hotel-name a',
        ),
        'stay_dates' => 
        array (
          'sel' => '.main-cell--tour-duration .main-cell__primary',
        ),
        'total_price' => 
        array (
          'sel' => '.main-cell__price .text-nowrap',
        ),
        'booking_code' => 
        array (
          'sel' => '.booking-number__number',
        ),
        'reservation_at' => 
        array (
          'sel' => '.booking-head__left-block-dates-value',
          'strip_prefix' => 'от',
        ),
      ),
      'label_maps' => 
      array (
        0 => 
        array (
          'item' => '.payment-value',
          'label' => '.payment-value-title',
          'value' => '.payment-value-text',
          'fields' => 
          array (
            'total_price' => 
            array (
              0 => 'цена тура',
            ),
          ),
        ),
      ),
      'tourist_blocks' => 
      array (
        'item' => '.booking-persons-list__item',
        'fields' => 
        array (
          'dob' => 
          array (
            'sel' => '.row__birth',
          ),
          'last_name' => 
          array (
            'sel' => '.person-name',
            'strip_pattern' => '[ ].*$',
          ),
          'first_name' => 
          array (
            'sel' => '.person-name',
            'strip_pattern' => '^[^ ]+[ ]+',
          ),
        ),
      ),
      'button_placement' => 'after',
    ),
    'is_active' => true,
    'operator_id' => 41,
    'operator_name' => 'Pegas Touristik',
    'notes' => 'Pegas Touristik bookings list (agency.pegast.ru/MyAccount/Bookings/). Seeded from page report #11 on 2026-05-27. Operator name placeholder — verify against HelloOtel /operator/list.',
  ),
  2 => 
  array (
    'name' => 'Velikolepniy Vek',
    'domain' => 'demo.velikolepniy-vek.com',
    'path_match' => '/hotel/book/history',
    'config' => 
    array (
      'card' => '.hotel-reservation-history__item',
      'type' => 'card',
      'button' => '.hotel-reservation-history__item-header',
      'fields' => 
      array (
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
    'operator_id' => 60,
    'operator_name' => NULL,
    'notes' => NULL,
  ),
  3 => 
  array (
    'name' => 'FunSun Russia — Просмотр заявок',
    'domain' => 'b2b.fstravel.com',
    'path_match' => '/default.php',
    'config' => 
    array (
      'card' => '.modalTitle',
      'type' => 'card',
      'fields' => 
      array (
        'subtitle' => 
        array (
          'sel' => '.order-block__data.hotel-room',
          'strip_icons' => true,
        ),
        'hotel_name' => 
        array (
          'sel' => '.order-block__data.hotel-name a',
        ),
        'stay_dates' => 
        array (
          'sel' => '.order-block__data.hotel-dates',
          'strip_icons' => true,
        ),
        'booking_code' => 
        array (
          'data' => 'claim',
        ),
        'reservation_at' => 
        array (
          'sel' => 'td.status',
          'strip_icons' => true,
        ),
      ),
      'card_root' => '#modalContainer',
      'data_root' => 
      array (
        'code_source' => 
        array (
          'self' => true,
          'strip_pattern' => '^[^0-9]*',
        ),
        'selector_template' => '#cl_{code}',
      ),
      'card_fields' => 
      array (
        'total_price' => 
        array (
          'sel' => '.samo_container > table:nth-of-type(2) tbody tr:nth-child(2) td.cl-cost.claim-currency',
          'append_location' => '.samo_container > table:nth-of-type(2) thead th.cl-cost.claim-currency',
        ),
      ),
      'meta_fields' => 
      array (
        'flights' => 
        array (
          'sel' => '.order-block__data.freight-name',
          'multi' => true,
        ),
        'hotel_stars' => 
        array (
          'sel' => '.order-block__data.hotel-stars',
        ),
        'payment_status' => 
        array (
          'sel' => '.claim-status',
          'strip_icons' => true,
        ),
      ),
      'tourist_blocks' => 
      array (
        'item' => '.tbl_peoples tbody tr[data-people]',
        'fields' => 
        array (
          'dob' => 
          array (
            'sel' => '.born',
          ),
          'gender' => 
          array (
            'sel' => '.human',
          ),
          'last_name' => 
          array (
            'sel' => '.tourist-latin-name',
            'strip_pattern' => '[ ].*$',
          ),
          'first_name' => 
          array (
            'sel' => '.tourist-latin-name',
            'strip_pattern' => '^[^ ]+[ ]+',
          ),
        ),
      ),
      'button_placement' => 'after',
    ),
    'is_active' => true,
    'operator_id' => NULL,
    'operator_name' => NULL,
    'notes' => NULL,
  ),
  4 => 
  array (
    'name' => 'Anex Tour — Просмотр заявок',
    'domain' => 'samo.anextour.ru',
    'path_match' => '/cl_refer',
    'config' => 
    array (
      'card' => '.modalTitle',
      'type' => 'card',
      'fields' => 
      array (
        'subtitle' => 
        array (
          'sel' => '.order-block__data.hotel-room',
          'strip_icons' => true,
        ),
        'hotel_name' => 
        array (
          'sel' => '.order-block__data.hotel-name a',
        ),
        'stay_dates' => 
        array (
          'sel' => '.order-block__data.hotel-dates',
          'strip_icons' => true,
        ),
        'booking_code' => 
        array (
          'data' => 'claim',
        ),
        'reservation_at' => 
        array (
          'sel' => 'td.status',
          'strip_icons' => true,
        ),
      ),
      'card_root' => '#modalContainer',
      'data_root' => 
      array (
        'code_source' => 
        array (
          'self' => true,
          'strip_pattern' => '^[^0-9]*',
        ),
        'selector_template' => '#cl_{code}',
      ),
      'card_fields' => 
      array (
        'total_price' => 
        array (
          'sel' => '.samo_container > table:nth-of-type(2) tbody tr:nth-child(2) td.cl-cost.claim-currency',
          'append_location' => '.samo_container > table:nth-of-type(2) thead th.cl-cost.claim-currency',
        ),
      ),
      'meta_fields' => 
      array (
        'flights' => 
        array (
          'sel' => '.order-block__data.freight-name',
          'multi' => true,
        ),
        'hotel_stars' => 
        array (
          'sel' => '.order-block__data.hotel-stars',
        ),
        'payment_status' => 
        array (
          'sel' => '.claim-status',
          'strip_icons' => true,
        ),
      ),
      'tourist_blocks' => 
      array (
        'item' => '.tbl_peoples tbody tr[data-people]',
        'fields' => 
        array (
          'dob' => 
          array (
            'sel' => '.born',
          ),
          'gender' => 
          array (
            'sel' => '.human',
          ),
          'last_name' => 
          array (
            'sel' => '.tourist-latin-name',
            'strip_pattern' => '[ ].*$',
          ),
          'first_name' => 
          array (
            'sel' => '.tourist-latin-name',
            'strip_pattern' => '^[^ ]+[ ]+',
          ),
        ),
      ),
      'button_placement' => 'after',
    ),
    'is_active' => true,
    'operator_id' => 32,
    'operator_name' => NULL,
    'notes' => NULL,
  ),
  5 => 
  array (
    'name' => 'Anex Tour Agent — Заявки',
    'domain' => 'agent.anextour.ru',
    'path_match' => '/orders',
    'config' => 
    array (
      'card' => '#modalAbout',
      'type' => 'card',
      'button' => '#modalAbout > div.mb-24.flex.flex-wrap.items-center',
      'fields' => 
      array (
        'hotel_name' => 
        array (
          'sel' => 'li > div.flex.flex-col.rounded-8.bg-fog a.text-16.font-medium.text-black',
          'strip_icons' => true,
        ),
        'stay_dates' => 
        array (
          'sel' => 'time[data-field="datebeg"]',
          'join' => ' - ',
          'multi' => true,
        ),
        'booking_code' => 
        array (
          'sel' => 'p.text-16.leading-22.font-bold',
        ),
        'reservation_at' => 
        array (
          'sel' => 'p.text-14.leading-22.mr-6',
          'strip_prefix' => 'Заявка от ',
        ),
      ),
      'card_root' => '[role="tabpanel"]',
      'label_maps' => 
      array (
        0 => 
        array (
          'item' => 'li > div.flex.flex-col.rounded-8.bg-fog div.relative.flex',
          'label' => 'div.text-carbon',
          'value' => 'div.font-medium.pl-2',
          'fields' => 
          array (
            'subtitle' => 
            array (
              0 => 'номер',
            ),
          ),
        ),
      ),
      'meta_fields' => 
      array (
        'payment_status' => 
        array (
          'sel' => 'p.text-14.leading-22.font-medium.text-black',
        ),
      ),
      'tourist_blocks' => 
      array (
        'item' => 'ul > li > div.rounded.bg-fog.p-16',
        'fields' => 
        array (
          'dob' => 
          array (
            'sel' => 'p.text-carbon + p.text-14.font-medium',
            'strip_icons' => true,
          ),
          'gender' => 
          array (
            'sel' => 'span.text-12.font-medium.w-40.h-28.rounded-24',
          ),
          'last_name' => 
          array (
            'sel' => 'p.flex.items-center.text-14.font-medium',
            'strip_pattern' => '[ ].*$',
          ),
          'first_name' => 
          array (
            'sel' => 'p.flex.items-center.text-14.font-medium',
            'strip_pattern' => '^[^ ]+[ ]+',
          ),
        ),
      ),
      'card_label_maps' => 
      array (
        0 => 
        array (
          'item' => 'footer > div > div',
          'label' => 'div.text-carbon',
          'value' => 'div.text-24.font-bold',
          'fields' => 
          array (
            'total_price' => 
            array (
              0 => 'каталогу',
              1 => 'стоимост',
            ),
          ),
        ),
      ),
      'button_placement' => 'after',
    ),
    'is_active' => true,
    'operator_id' => 32,
    'operator_name' => NULL,
    'notes' => NULL,
  ),
  6 => 
  array (
    'name' => 'BG-operator — Заявка',
    'domain' => 'www.bgoperator.ru',
    'path_match' => '/tozaya',
    'config' => 
    array (
      'card' => '#Form',
      'type' => 'card',
      'button' => '.claim_no',
      'fields' => 
      array (
        'subtitle' => 
        array (
          'sel' => '.full-info-z table tr:nth-of-type(2) td:nth-of-type(4)',
        ),
        'hotel_name' => 
        array (
          'sel' => '.full-info-z table tr:nth-of-type(2) td:nth-of-type(3)',
          'strip_pattern' => '[ ]+[0-9]\\*$',
        ),
        'stay_dates' => 
        array (
          'sel' => '.full-info-z table tr:nth-of-type(2) td:nth-of-type(2)',
          'strip_pattern' => '[ ]-',
          'strip_replace' => ' - ',
        ),
        'total_price' => 
        array (
          'sel' => 'table.line tr:nth-of-type(2) td.line:nth-of-type(1)',
        ),
        'booking_code' => 
        array (
          'sel' => '.claim_no',
        ),
        'reservation_at' => 
        array (
          'sel' => 'input[name="tck"]',
          'attr' => 'value',
        ),
      ),
      'meta_fields' => 
      array (
        'payment_status' => 
        array (
          'sel' => 'table.line tr:nth-of-type(2) td.line:nth-of-type(4)',
          'strip_pattern' => '[ ]*[CС]рок.*$',
        ),
        'status_voucher' => 
        array (
          'sel' => '.full-info-z table tr:nth-of-type(2) td:nth-of-type(6)',
        ),
      ),
      'tourist_blocks' => 
      array (
        'item' => '.full-info-z table tr:nth-of-type(2) td:nth-of-type(8) a',
        'fields' => 
        array (
          'dob' => 
          array (
            'self' => true,
            'strip_pattern' => '^.*[ ]',
          ),
          'gender' => 
          array (
            'self' => true,
            'strip_pattern' => '[ ].*$',
          ),
          'last_name' => 
          array (
            'self' => true,
            'strip_flags' => 'g',
            'strip_pattern' => '^[A-Za-z]+[ ]+|[ ]+[^ ]+[ ]+[0-9].*$',
          ),
          'first_name' => 
          array (
            'self' => true,
            'strip_flags' => 'g',
            'strip_pattern' => '^[A-Za-z]+[ ]+[A-Z]+[ ]+|[ ]+[0-9].*$',
          ),
        ),
      ),
      'button_placement' => 'after',
    ),
    'is_active' => true,
    'operator_id' => 38,
    'operator_name' => NULL,
    'notes' => NULL,
  ),
  7 => 
  array (
    'name' => 'Join UP! — Просмотр заявок',
    'domain' => 'online.joinup.ua',
    'path_match' => '/cl_refer',
    'config' => 
    array (
      'card' => '.modalTitle',
      'type' => 'card',
      'fields' => 
      array (
        'subtitle' => 
        array (
          'sel' => '.order-block__data.hotel-room',
          'strip_icons' => true,
        ),
        'hotel_name' => 
        array (
          'sel' => '.order-block__data.hotel-name a',
        ),
        'stay_dates' => 
        array (
          'sel' => '.order-block__data.hotel-dates',
          'strip_icons' => true,
        ),
        'booking_code' => 
        array (
          'data' => 'claim',
        ),
        'reservation_at' => 
        array (
          'sel' => 'td.status',
          'strip_icons' => true,
          'strip_pattern' => '^[^0-9]*',
        ),
      ),
      'card_root' => '#modalContainer',
      'data_root' => 
      array (
        'code_source' => 
        array (
          'self' => true,
          'strip_pattern' => '^[^0-9]*',
        ),
        'selector_template' => '#cl_{code}',
      ),
      'card_fields' => 
      array (
        'total_price' => 
        array (
          'sel' => '.samo_container > table:nth-of-type(2) tbody tr:nth-child(2) td.cl-cost.claim-currency',
          'append_location' => '.samo_container > table:nth-of-type(2) thead th.cl-cost.claim-currency',
        ),
      ),
      'meta_fields' => 
      array (
        'flights' => 
        array (
          'sel' => '.order-block__data.freight-name',
          'multi' => true,
        ),
        'hotel_stars' => 
        array (
          'sel' => '.order-block__data.hotel-stars',
        ),
        'payment_status' => 
        array (
          'sel' => '.claim-status',
          'strip_icons' => true,
        ),
      ),
      'tourist_blocks' => 
      array (
        'item' => '.tbl_peoples tbody tr[data-people]',
        'fields' => 
        array (
          'dob' => 
          array (
            'sel' => '.born',
          ),
          'gender' => 
          array (
            'sel' => '.human',
          ),
          'last_name' => 
          array (
            'sel' => '.tourist-latin-name',
            'strip_pattern' => '[ ].*$',
          ),
          'first_name' => 
          array (
            'sel' => '.tourist-latin-name',
            'strip_pattern' => '^[^ ]+[ ]+',
          ),
        ),
      ),
      'button_placement' => 'after',
    ),
    'is_active' => true,
    'operator_id' => NULL,
    'operator_name' => NULL,
    'notes' => 'SAMO-based (as fstravel/anextour) — card anchored to .modalTitle, not the conditional pay button.',
  ),
);

        $keepNames = array_column($parsers, 'name');
        ExtensionParser::whereNotIn('name', $keepNames)->delete();

        foreach ($parsers as $row) {
            ExtensionParser::updateOrCreate(
                ['name' => $row['name']],
                [
                    'domain'        => $row['domain'],
                    'path_match'    => $row['path_match'],
                    'config'        => $row['config'],
                    'is_active'     => $row['is_active'],
                    'operator_id'   => $row['operator_id']   ?? null,
                    'operator_name' => $row['operator_name'] ?? null,
                    'notes'         => $row['notes'],
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

        $keepRules = array_map(fn($r) => $r['domain'] . '|' . ($r['path_match'] ?? ''), $rules);
        ExtensionParserRule::all()->each(function ($rule) use ($keepRules) {
            $key = $rule->domain . '|' . ($rule->path_match ?? '');
            if (!in_array($key, $keepRules, true)) $rule->delete();
        });

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