<?php

namespace App\Filament\Resources\ProcessedBooking\Pages;

use App\Filament\Resources\ProcessedBooking\ProcessedBookingResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\MaxWidth;

class ListProcessedBookings extends ListRecords
{
    protected static string $resource = ProcessedBookingResource::class;

    public function getMaxContentWidth(): MaxWidth
    {
        return MaxWidth::Full;
    }
}
