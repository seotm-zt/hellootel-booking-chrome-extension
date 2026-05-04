<?php

namespace App\Filament\Resources\ExtensionBooking\Pages;

use App\Filament\Resources\ExtensionBooking\ExtensionBookingResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\ViewRecord;

class ViewExtensionBooking extends ViewRecord
{
    protected static string $resource = ExtensionBookingResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
