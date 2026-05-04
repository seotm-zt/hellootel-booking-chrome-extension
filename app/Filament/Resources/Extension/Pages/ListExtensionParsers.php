<?php

namespace App\Filament\Resources\Extension\Pages;

use App\Filament\Resources\Extension\ExtensionParserResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListExtensionParsers extends ListRecords
{
    protected static string $resource = ExtensionParserResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
