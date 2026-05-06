<?php

namespace App\Filament\Resources\ProcessedBooking\Pages;

use App\Filament\Resources\ProcessedBooking\ProcessedBookingResource;
use Filament\Resources\Pages\ListRecords;

class ListProcessedBookings extends ListRecords
{
    protected static string $resource = ProcessedBookingResource::class;
}
