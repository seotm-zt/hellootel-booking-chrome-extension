<?php

namespace App\Filament\Resources\Extension;

use App\Filament\Resources\Extension\Pages\CreateExtensionParser;
use App\Filament\Resources\Extension\Pages\EditExtensionParser;
use App\Filament\Resources\Extension\Pages\ListExtensionParsers;
use App\Models\ExtensionParser;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ExtensionParserResource extends Resource
{
    protected static ?string $model = ExtensionParser::class;
    protected static ?string $navigationIcon = 'heroicon-o-cpu-chip';
    protected static ?string $navigationLabel = 'Parsers';
    protected static ?string $pluralModelLabel = 'Parsers';
    protected static ?int $navigationSort = 103;

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

            Section::make('Identity')->columns(2)->schema([
                TextInput::make('name')
                    ->label('Parser name')
                    ->placeholder('e.g. booking-com')
                    ->helperText('Unique identifier used in Parser Rules. Lowercase, hyphens ok.')
                    ->required()
                    ->maxLength(100)
                    ->unique(ignoreRecord: true),

                TextInput::make('domain')
                    ->label('Domain')
                    ->placeholder('e.g. booking.com')
                    ->helperText('Hostname to match (without port).')
                    ->maxLength(300),

                TextInput::make('path_match')
                    ->label('URL path prefix')
                    ->placeholder('e.g. /orders/list')
                    ->helperText('Optional. Parser activates only on URLs whose path starts with this string.')
                    ->maxLength(500),

                Toggle::make('is_active')
                    ->label('Active')
                    ->default(true)
                    ->columnSpanFull(),

                Textarea::make('notes')
                    ->label('Notes')
                    ->rows(2)
                    ->columnSpanFull(),
            ]),

            Section::make('Parser config (JSON)')->schema([
                Textarea::make('config')
                    ->label('')
                    ->rows(24)
                    ->required()
                    ->columnSpanFull()
                    ->extraAttributes(['style' => 'font-family: monospace; font-size: 13px;'])
                    ->helperText('Valid JSON. See schema reference below.')
                    ->formatStateUsing(fn ($state) => is_array($state)
                        ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                        : $state
                    )
                    ->dehydrateStateUsing(fn ($state) => json_decode($state, true) ?? $state)
                    ->rules(['json']),
            ]),

            Section::make('Config schema reference')
                ->collapsed()
                ->collapsible()
                ->schema([
                    Placeholder::make('schema_docs')
                        ->label('')
                        ->columnSpanFull()
                        ->content(<<<'TEXT'
PARSER TYPES
  type: "card"   (default) — booking card list
  type: "form"   — single booking form page
  type: "table"  — bookings in an HTML table

CARD TYPE — top-level keys
  card       {string}  CSS selector for booking card elements
  button     {string?} CSS selector (relative to card) for button injection

FORM TYPE
  container  {string?} CSS selector for form wrapper (default: body)
  button     {string?} CSS selector for button injection area
  fields     {object}  fieldName → { label_match: ["keyword", ...] }

TABLE TYPE
  table       {string}  CSS selector for <table>
  button_cell {string?} CSS selector (relative to <tr>) for button injection
  fields      {object}  fieldName → ["header keyword", ...]
                        or { keywords: [...], as_array: true }

FIELDS (card type)
  { "sel": ".css" }                   text content
  { "sel": "img", "attr": "src" }     attribute value
  { "sel": ".b", "multi": true }      array of all matches
  { "sel": ".el", "strip_icons": true }
  { "sel": ".el", "strip_prefix": "Label:" }
  { "sel": ".hotel", "append_location": ".loc" }
  { "data": "ref", "fallback": ".ref" }
  { "h_code": "h3" }  { "h_hotel": "h3" }
  { "p_subtitle": "p", "seps": [" · "] }
  { "br_map": "p", "key_match": ["date"] }

label_maps  — [{ item, label, value, fields: { field: ["kw"] } }]
dl_maps     — [{ container?, item, key, value, fields: { field: ["kw"] } }]
TEXT),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Name')->searchable()->sortable()->weight('bold'),
                TextColumn::make('domain')->label('Domain')->searchable()->placeholder('—'),
                TextColumn::make('path_match')->label('Path')->placeholder('—'),
                IconColumn::make('is_active')->label('Active')->boolean(),
                TextColumn::make('notes')->label('Notes')->limit(50)->placeholder('—'),
                TextColumn::make('updated_at')->label('Updated')->dateTime('d M Y')->sortable(),
            ])
            ->defaultSort('name')
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
            'index'  => ListExtensionParsers::route('/'),
            'create' => CreateExtensionParser::route('/create'),
            'edit'   => EditExtensionParser::route('/{record}/edit'),
        ];
    }
}
