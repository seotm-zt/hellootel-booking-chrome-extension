<?php

namespace App\Filament\Resources\ExtensionBooking\Pages;

use App\Filament\Resources\ExtensionBooking\ExtensionBookingResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\MaxWidth;

class ListExtensionBookings extends ListRecords
{
    protected static string $resource = ExtensionBookingResource::class;

    public function getMaxContentWidth(): MaxWidth
    {
        return MaxWidth::Full;
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
