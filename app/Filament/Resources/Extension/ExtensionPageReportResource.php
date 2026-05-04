<?php

namespace App\Filament\Resources\Extension;

use App\Filament\Resources\Extension\Pages\ListExtensionPageReports;
use App\Filament\Resources\Extension\Pages\ViewExtensionPageReport;
use App\Models\ExtensionPageReport;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

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
                TextColumn::make('title')->label('Title')->searchable()->limit(50)->placeholder('—'),
                TextColumn::make('url')->label('URL')->searchable()->limit(70)
                    ->url(fn ($record) => $record->url, shouldOpenInNewTab: true)->color('info'),
                TextColumn::make('created_at')->label('Received')->dateTime('d M Y, H:i')->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
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
