<?php

namespace App\Filament\Resources\Extension\Pages;

use App\Filament\Resources\Extension\ExtensionPageReportResource;
use Filament\Resources\Pages\ListRecords;

class ListExtensionPageReports extends ListRecords
{
    protected static string $resource = ExtensionPageReportResource::class;
}
