<?php

namespace App\Filament\Resources\ProcessedBooking\Pages;

use App\Filament\Resources\ProcessedBooking\ProcessedBookingResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditProcessedBooking extends EditRecord
{
    protected static string $resource = ProcessedBookingResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
