<?php

namespace App\Filament\Resources\Extension;

use App\Filament\Resources\Extension\Pages\ListExtensionPageReports;
use App\Filament\Resources\Extension\Pages\ViewExtensionPageReport;
use App\Models\ExtensionPageReport;
use App\Models\ExtensionParser;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ExtensionPageReportResource extends Resource
{
    protected static ?string $model = ExtensionPageReport::class;
    protected static ?string $navigationIcon = 'heroicon-o-inbox-arrow-down';
    protected static ?string $navigationLabel = 'Page Reports';
    protected static ?string $pluralModelLabel = 'Page Reports';
    protected static ?int $navigationSort = 101;

    public static function getNavigationGroup(): ?string
    {
        return 'Chrome Extension';
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Section::make('Page info')
                ->columns(2)
                ->schema([
                    TextEntry::make('url')
                        ->label('URL')
                        ->columnSpanFull()
                        ->url(fn ($record) => $record->url, shouldOpenInNewTab: true)
                        ->limit(120),

                    TextEntry::make('title')
                        ->label('Page title'),

                    TextEntry::make('created_at')
                        ->label('Received')
                        ->dateTime('d M Y, H:i'),
                ]),

            Section::make('Page preview')
                ->schema([
                    ViewEntry::make('id')
                        ->label('')
                        ->columnSpanFull()
                        ->view('filament.extension.page-report-iframe'),
                ])
                ->collapsible(false),

            Section::make('Page source')
                ->description('Raw HTML captured from the browser.')
                ->schema([
                    ViewEntry::make('html')
                        ->label('')
                        ->columnSpanFull()
                        ->view('filament.extension.page-report-source'),
                ])
                ->collapsed()
                ->collapsible(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('ID')->sortable()->width('60px'),
                IconColumn::make('has_parser')
                    ->label('')
                    ->tooltip(fn ($record) => filled($record->matched_parser_at) ? 'Есть парсер' : 'Парсера нет')
                    ->getStateUsing(fn ($record) => filled($record->matched_parser_at))
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-minus')
                    ->trueColor('success')
                    ->falseColor('gray')
                    ->width('40px'),
                TextColumn::make('title')->label('Title')->searchable()->limit(50)->placeholder('—'),
                TextColumn::make('url')->label('URL')->searchable()->limit(70)
                    ->url(fn ($record) => $record->url, shouldOpenInNewTab: true)->color('info'),
                TextColumn::make('created_at')->label('Received')->dateTime('d M Y, H:i')->sortable(),
            ])
            ->modifyQueryUsing(function (Builder $query) {
                // Correlated subquery: most recent created_at of an active
                // parser whose domain appears in this report's URL. Powers
                // both the "has parser" icon column and the default sort.
                $matchedParser = ExtensionParser::query()
                    ->select('created_at')
                    ->where('is_active', true)
                    ->whereNotNull('domain')
                    ->where('domain', '!=', '')
                    ->whereRaw('extension_page_reports.url LIKE CONCAT(\'%\', extension_parsers.domain, \'%\')')
                    ->orderByDesc('created_at')
                    ->limit(1);

                $query->addSelect(['matched_parser_at' => $matchedParser]);
            })
            ->defaultSort(function (Builder $query) {
                // Reports with a matched parser come first, most recently
                // added parser first. Unmatched reports fall back to most
                // recently received first.
                return $query
                    ->orderByRaw('(matched_parser_at IS NULL) ASC')
                    ->orderByDesc('matched_parser_at')
                    ->orderByDesc('extension_page_reports.created_at');
            })
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListExtensionPageReports::route('/'),
            'view'  => ViewExtensionPageReport::route('/{record}'),
        ];
    }
}
