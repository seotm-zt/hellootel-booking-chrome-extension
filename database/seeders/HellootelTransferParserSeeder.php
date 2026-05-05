<?php

namespace Database\Seeders;

use App\Models\ExtensionParser;
use Illuminate\Database\Seeder;

class HellootelTransferParserSeeder extends Seeder
{
    public function run(): void
    {
        ExtensionParser::updateOrCreate(
            ['name' => 'hellootel-transfer-history'],
            [
                'domain'     => 'demo.velikolepniy-vek.com',
                'path_match' => '/transfer/book/history',
                'is_active'  => true,
                'config'     => [
                    'card'   => '.transfer-reservation-history__item',
                    'button' => '.transfer-reservation-history__item-header',

                    'fields' => [
                        'booking_code' => ['sel' => '.transfer-reservation-history__item-header-col:first-child'],
                        'subtitle'     => ['sel' => '.transfer-reservation-history__item-header-col:nth-child(2)'],
                        'stay_dates'   => ['sel' => '.transfer-reservation-history__item-header-col:nth-child(4) div:last-child'],
                        'hotel_name'   => ['sel' => '.transfer-reservation-history__item-header-col:nth-child(5)'],
                        'total_price'  => [
                            'sel'          => '.transfer-reservation-history__item-header-col:nth-child(6)',
                            'strip_prefix' => 'Стоимость / комиссия:',
                        ],
                        'statuses' => [
                            'sel'   => '.transfer-reservation-history__item-header-col:nth-child(7) div:last-child',
                            'multi' => true,
                        ],
                    ],

                    'label_maps' => [
                        [
                            'item'   => '.transfer-reservation-history__item-sub-header-col',
                            'label'  => 'label',
                            'value'  => 'span',
                            'fields' => [
                                'adults'   => ['взрослых'],
                                'children' => ['детей'],
                                'infants'  => ['младенцев'],
                                'transfer' => ['время трансфера'],
                            ],
                        ],
                    ],

                    'meta_maps' => [
                        [
                            'item'   => '.transfer-reservation-history__item-sub-header-col',
                            'label'  => 'label',
                            'value'  => 'span',
                            'fields' => [
                                'from'      => ['откуда'],
                                'to'        => ['куда'],
                                'direction' => ['направление'],
                                'vehicle'   => ['автомобиль'],
                            ],
                        ],
                    ],

                    'tourist_blocks' => [
                        'item'   => '.transfer-reservation-history__item-info-row',
                        'label'  => 'label',
                        'value'  => 'span',
                        'fields' => [
                            'last_name'  => ['фамилия'],
                            'first_name' => ['имя'],
                            'dob'        => ['дата рождения'],
                        ],
                    ],
                ],
            ]
        );
    }
}
