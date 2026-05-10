<?php

namespace App\Filament\Resources\Extension\Pages;

use App\Filament\Resources\Extension\ExtensionParserRuleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\MaxWidth;

class ListExtensionParserRules extends ListRecords
{
    protected static string $resource = ExtensionParserRuleResource::class;

    public function getMaxContentWidth(): MaxWidth
    {
        return MaxWidth::Full;
    }

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
