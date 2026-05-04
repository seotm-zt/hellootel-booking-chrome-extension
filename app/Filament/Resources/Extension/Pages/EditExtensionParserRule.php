<?php

namespace App\Filament\Resources\Extension\Pages;

use App\Filament\Resources\Extension\ExtensionParserRuleResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditExtensionParserRule extends EditRecord
{
    protected static string $resource = ExtensionParserRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
