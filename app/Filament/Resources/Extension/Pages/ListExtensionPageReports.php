<?php

namespace App\Filament\Resources\Extension\Pages;

use App\Filament\Resources\Extension\ExtensionPageReportResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\MaxWidth;

class ListExtensionPageReports extends ListRecords
{
    protected static string $resource = ExtensionPageReportResource::class;

    public function getMaxContentWidth(): MaxWidth
    {
        return MaxWidth::Full;
    }
}
