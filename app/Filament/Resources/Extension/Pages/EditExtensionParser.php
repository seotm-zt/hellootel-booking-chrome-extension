<?php

namespace App\Filament\Resources\Extension\Pages;

use App\Filament\Resources\Extension\ExtensionParserResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditExtensionParser extends EditRecord
{
    protected static string $resource = ExtensionParserResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
