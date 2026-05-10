<?php

namespace App\Filament\Resources\Extension;

use App\Filament\Resources\Extension\Pages\CreateExtensionParserRule;
use App\Filament\Resources\Extension\Pages\EditExtensionParserRule;
use App\Filament\Resources\Extension\Pages\ListExtensionParserRules;
use App\Models\ExtensionParserRule;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ExtensionParserRuleResource extends Resource
{
    protected static ?string $model = ExtensionParserRule::class;
    protected static ?string $navigationIcon = 'heroicon-o-code-bracket';
    protected static ?string $navigationLabel = 'Parser Rules';
    protected static ?string $pluralModelLabel = 'Parser Rules';
    protected static ?int $navigationSort = 102;

    public static function getNavigationGroup(): ?string
    {
        return 'Chrome Extension';
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('domain')
                ->label('Domain')
                ->placeholder('e.g. booking.com')
                ->helperText('Hostname only — no https:// or trailing slash.')
                ->required()
                ->maxLength(300)
                ->columnSpanFull(),

            TextInput::make('path_match')
                ->label('Path prefix (optional)')
                ->placeholder('e.g. /orders or /bookings/list')
                ->helperText('Leave empty to match all pages on this domain.')
                ->maxLength(300)
                ->default('')
                ->columnSpanFull(),

            Select::make('parser')
                ->label('Parser')
                ->options(fn () => \App\Models\ExtensionParser::query()
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->pluck('name', 'name')
                    ->toArray()
                )
                ->required()
                ->searchable()
                ->columnSpanFull(),

            Textarea::make('notes')
                ->label('Notes')
                ->rows(3)
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('domain')->label('Domain')->searchable()->sortable()->copyable()->weight('bold'),
                TextColumn::make('path_match')->label('Path')->placeholder('—'),
                TextColumn::make('parser')->label('Parser')->badge()->color('info'),
                TextColumn::make('notes')->label('Notes')->limit(60)->placeholder('—'),
                TextColumn::make('created_at')->label('Added')->dateTime('d M Y')->sortable(),
            ])
            ->defaultSort('domain')
            ->actions([
                Tables\Actions\EditAction::make(),
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
            'index'  => ListExtensionParserRules::route('/'),
            'create' => CreateExtensionParserRule::route('/create'),
            'edit'   => EditExtensionParserRule::route('/{record}/edit'),
        ];
    }
}
