<?php

namespace App\Filament\Resources\Extension\Pages;

use App\Filament\Resources\Extension\ExtensionParserResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\MaxWidth;

class ListExtensionParsers extends ListRecords
{
    protected static string $resource = ExtensionParserResource::class;

    public function getMaxContentWidth(): MaxWidth
    {
        return MaxWidth::Full;
    }

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
