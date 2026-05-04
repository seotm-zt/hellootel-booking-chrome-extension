<?php

namespace App\Filament\Resources\ExtensionBooking\Pages;

use App\Filament\Resources\ExtensionBooking\ExtensionBookingResource;
use Filament\Resources\Pages\ListRecords;

class ListExtensionBookings extends ListRecords
{
    protected static string $resource = ExtensionBookingResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
