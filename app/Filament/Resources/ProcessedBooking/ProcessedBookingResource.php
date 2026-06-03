<?php

namespace App\Filament\Resources\ProcessedBooking;

use App\Filament\Resources\ProcessedBooking\Pages\EditProcessedBooking;
use App\Filament\Resources\ProcessedBooking\Pages\ListProcessedBookings;
use App\Filament\Resources\ProcessedBooking\Pages\ViewProcessedBooking;
use App\Models\ProcessedBooking;
use App\Services\HellOotelLookupService;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Section as InfoSection;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ProcessedBookingResource extends Resource
{
    protected static ?string $model = ProcessedBooking::class;
    protected static ?string $navigationIcon = 'heroicon-o-check-badge';
    protected static ?string $navigationLabel = 'Processed Bookings';
    protected static ?int $navigationSort = 201;

    /** Per-request memoized [hotel_id => name] map for the table column. */
    protected static ?array $hotelMap = null;

    protected static function hotelName(?int $id, ProcessedBooking $record): string
    {
        if (!$id) {
            return '—';
        }
        if (static::$hotelMap === null) {
            static::$hotelMap = app(HellOotelLookupService::class)->getHotels();
        }
        return static::$hotelMap[$id] ?? $record->hotel_name ?? "#{$id}";
    }

    /** Per-request memoized [operator_id => name] map for the table column. */
    protected static ?array $operatorMap = null;

    protected static function operatorName(?string $name, ProcessedBooking $record): string
    {
        if (filled($name)) {
            return $name;
        }
        if (!$record->operator_id) {
            return '—';
        }
        if (static::$operatorMap === null) {
            static::$operatorMap = app(HellOotelLookupService::class)->getOperators();
        }
        return static::$operatorMap[$record->operator_id] ?? "#{$record->operator_id}";
    }

    public static function getNavigationGroup(): ?string
    {
        return 'HellOotel';
    }

    public static function getModelLabel(): string
    {
        return 'Processed Booking';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Processed Bookings';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (\Illuminate\Database\Eloquent\Builder $query) {
                if (!auth()->user()?->hasRole('admin')) {
                    $query->where('saved_by_user_id', auth()->id());
                }
            })
            ->columns([
                TextColumn::make('id')->label('ID')->sortable(),
                TextColumn::make('booking_code')->label('Code')->badge()->color('success')->searchable()->placeholder('—'),
                TextColumn::make('source')
                    ->label('Source')
                    ->badge()
                    ->getStateUsing(fn (ProcessedBooking $r) => $r->source_booking_id ? 'Parser' : 'Manual')
                    ->color(fn (string $state) => $state === 'Parser' ? 'info' : 'warning')
                    ->icon(fn (string $state) => $state === 'Parser'
                        ? 'heroicon-o-cpu-chip'
                        : 'heroicon-o-pencil-square'),
                IconColumn::make('hotel_id')
                    ->label('Matched')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('gray')
                    ->getStateUsing(fn (ProcessedBooking $r) => (bool) $r->hotel_id),
                TextColumn::make('hotel_id')
                    ->label('Hotel')
                    ->placeholder('—')
                    ->searchable()
                    ->formatStateUsing(fn (?int $state, ProcessedBooking $r) => static::hotelName($state, $r)),
                TextColumn::make('hotel_vote')->label('Rating')->alignCenter()->placeholder('—'),
                TextColumn::make('hotel_name')->label('Hotel (text)')->limit(30)->placeholder('—')->searchable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('room_type_name')->label('Room type')->limit(25)->placeholder('—'),
                TextColumn::make('hellootel_reservation_id')->label('Reservation ID')->badge()->color('success')->placeholder('—'),
                TextColumn::make('hellootel_status')
                    ->label('Sent')
                    ->badge()
                    ->getStateUsing(fn (ProcessedBooking $r) => $r->hellootel_reservation_id
                        ? 'Sent'
                        : ($r->hellootel_response ? 'Error' : '—'))
                    ->color(fn (string $state) => match ($state) {
                        'Sent'  => 'success',
                        'Error' => 'danger',
                        default => 'gray',
                    })
                    ->tooltip(fn (ProcessedBooking $r) => $r->hellootel_reservation_id ? null : ($r->hellootel_response ?: null)),
                IconColumn::make('confirmed_at')
                    ->label('Conf.')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-badge')
                    ->falseIcon('heroicon-o-clock')
                    ->trueColor('success')
                    ->falseColor('gray')
                    ->getStateUsing(fn (ProcessedBooking $r) => (bool) $r->confirmed_at)
                    ->tooltip(fn (ProcessedBooking $r) => $r->confirmed_at
                        ? 'Confirmed: ' . $r->confirmed_at->format('d.m.Y H:i')
                        : 'Not confirmed'),
                TextColumn::make('confirmed_by_user_id')
                    ->label('Confirmed by')
                    ->placeholder('—')
                    ->formatStateUsing(fn ($state) => $state
                        ? \App\Models\User::find($state)?->name ?? "User #{$state}"
                        : '—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('operator_id')->label('operator_id')->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('operator_name')
                    ->label('Operator')
                    ->placeholder('—')
                    ->searchable()
                    ->getStateUsing(fn (ProcessedBooking $r) => static::operatorName($r->operator_name, $r)),
                TextColumn::make('agency_id')->label('agency_id')->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('agency_name')->label('Agency')->placeholder('—')->searchable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('reservation_date')->label('Res. date')->date('d.m.Y')->sortable()->placeholder('—'),
                TextColumn::make('arrival_at')->label('Check-in')->date('d.m.Y')->sortable()->placeholder('—'),
                TextColumn::make('departure_at')->label('Check-out')->date('d.m.Y')->placeholder('—'),
                TextColumn::make('person_count_adults')->label('Adt.')->alignCenter(),
                TextColumn::make('person_count_children')->label('Chd.')->alignCenter(),
                TextColumn::make('person_count_teens')->label('Inf.')->alignCenter(),
                TextColumn::make('price')->label('Price')->placeholder('—')
                    ->formatStateUsing(fn ($state, ProcessedBooking $r) => $state
                        ? number_format($state, 2, '.', ' ') . ($r->currency_code ? ' ' . $r->currency_code : '')
                        : '—'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('id', 'desc');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Identifiers')->columns(1)->schema([
                TextInput::make('booking_code')->label('booking_code')->columnSpanFull(),

                \Filament\Forms\Components\Grid::make(2)->schema([
                    Select::make('hotel_id')
                        ->label('hotel_id')
                        ->options(fn() => app(HellOotelLookupService::class)->getHotels())
                        ->searchable()
                        ->live()
                        ->afterStateUpdated(fn(Set $set) => $set('room_type_id', null))
                        ->placeholder('— select hotel —'),
                    TextInput::make('hotel_name')->label('Hotel name (raw from booking)'),
                ]),

                TextInput::make('hotel_vote')
                    ->label('Hotel Rating (0-100)')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(100)
                    ->columnSpanFull(),

                \Filament\Forms\Components\Grid::make(2)->schema([
                    Select::make('room_type_id')
                        ->label('room_type_id')
                        ->options(function (Get $get) {
                            $hotelId = $get('hotel_id');
                            if (!$hotelId) return [];
                            return app(HellOotelLookupService::class)->getRoomTypes((int) $hotelId);
                        })
                        ->searchable()
                        ->live()
                        ->placeholder('— select room type —'),
                    TextInput::make('room_type_name')->label('Room type (raw from booking)'),
                ]),
                \Filament\Forms\Components\Grid::make(2)->schema([
                    Select::make('operator_id')
                        ->label('operator_id')
                        ->options(fn() => app(HellOotelLookupService::class)->getOperators())
                        ->searchable()
                        ->placeholder('— select operator —'),
                    TextInput::make('operator_name')->label('Operator (raw from booking)'),
                ]),

                \Filament\Forms\Components\Grid::make(2)->schema([
                    TextInput::make('agency_id')->label('agency_id')->numeric(),
                    TextInput::make('agency_name')->label('Agency (raw from booking)'),
                ]),

                TextInput::make('status')->label('status')->columnSpanFull(),
            ]),

            Section::make('Dates')->columns(3)->schema([
                DatePicker::make('reservation_date')->label('Reservation date')->displayFormat('d.m.Y'),
                DatePicker::make('arrival_at')->label('Check-in')->displayFormat('d.m.Y'),
                DatePicker::make('departure_at')->label('Check-out')->displayFormat('d.m.Y'),
                TextInput::make('nights')->label('Nights')->numeric()->minValue(0),
            ]),

            Section::make('Guests')->columns(2)->schema([
                TextInput::make('guest_info')->label('guest_info'),
                TextInput::make('person_count_adults')->label('person_count_adults')->numeric()->minValue(0),
                TextInput::make('person_count_children')->label('person_count_children')->numeric()->minValue(0),
                TextInput::make('person_count_teens')->label('person_count_teens')->numeric()->minValue(0),
            ]),

            Section::make('Tourists (detailed)')->schema([
                Repeater::make('tourists')->label('')->columns(3)
                    ->schema([
                        TextInput::make('last_name')->label('Last name'),
                        TextInput::make('first_name')->label('First name'),
                        DatePicker::make('dob')->label('Date of birth')->displayFormat('d.m.Y'),
                    ])
                    ->defaultItems(0)
                    ->addActionLabel('Add tourist'),
            ]),

            Section::make('Price')->columns(2)->schema([
                TextInput::make('price')->label('price')->numeric(),
                TextInput::make('currency_code')->label('currency_code')->maxLength(3)->placeholder('EUR'),
            ]),
        ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            InfoSection::make('Identifiers')->columns(3)->schema([
                TextEntry::make('booking_code')->label('booking_code')->badge()->color('success')->placeholder('—'),
                TextEntry::make('hotel_id')->label('hotel_id')->placeholder('—'),
                TextEntry::make('hotel_vote')
                    ->label('Hotel Rating')
                    ->placeholder('—')
                    ->formatStateUsing(fn (?int $state) => $state !== null ? "{$state}/100" : '—'),
                TextEntry::make('hotel_name')->label('Hotel (text)')->placeholder('—'),
                TextEntry::make('room_type_id')->label('room_type_id')->placeholder('—'),
                TextEntry::make('room_type_name')->label('Room type')->placeholder('—'),
                TextEntry::make('status')->label('Status')->badge()->color('warning')->placeholder('—'),
                TextEntry::make('operator_id')->label('operator_id')->placeholder('—'),
                TextEntry::make('operator_name')->label('operator_name')->placeholder('—'),
                TextEntry::make('agency_id')->label('agency_id')->placeholder('—'),
                TextEntry::make('agency_name')->label('Agency')->placeholder('—'),
            ]),

            InfoSection::make('Dates')->columns(4)->schema([
                TextEntry::make('reservation_date')->label('Reservation date')->date('d.m.Y')->placeholder('—'),
                TextEntry::make('arrival_at')->label('Check-in')->date('d.m.Y')->placeholder('—'),
                TextEntry::make('departure_at')->label('Check-out')->date('d.m.Y')->placeholder('—'),
                TextEntry::make('nights')->label('Nights')->placeholder('—'),
            ]),

            InfoSection::make('Guests')->columns(2)->schema([
                TextEntry::make('guest_info')->label('guest_info')->placeholder('—'),
                TextEntry::make('person_count_adults')->label('Adults'),
                TextEntry::make('person_count_children')->label('Children'),
                TextEntry::make('person_count_teens')->label('Infants'),
            ]),

            InfoSection::make('Tourists (detailed)')
                ->hidden(fn (ProcessedBooking $r): bool => empty($r->tourists))
                ->schema([
                    RepeatableEntry::make('tourists')->label('')->columns(3)->schema([
                        TextEntry::make('last_name')->label('Last name')->placeholder('—'),
                        TextEntry::make('first_name')->label('First name')->placeholder('—'),
                        TextEntry::make('dob')->label('Date of birth')->placeholder('—'),
                    ]),
                ]),

            InfoSection::make('Price')->columns(2)->schema([
                TextEntry::make('price')->label('Price')
                    ->formatStateUsing(fn ($state, ProcessedBooking $r) => $state
                        ? number_format($state, 2, '.', ' ') . ($r->currency_code ? ' ' . $r->currency_code : '')
                        : '—'),
                TextEntry::make('currency_code')->label('Currency')->placeholder('—'),
            ]),

            InfoSection::make('Confirmation')->columns(2)->schema([
                TextEntry::make('confirmed_at')
                    ->label('Confirmed at')
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('Not confirmed')
                    ->badge()
                    ->color(fn ($state) => $state ? 'success' : 'gray'),
                TextEntry::make('confirmed_by_user_id')
                    ->label('Confirmed by')
                    ->placeholder('—')
                    ->formatStateUsing(fn ($state) => $state
                        ? \App\Models\User::find($state)?->name ?? "User #{$state}"
                        : '—'),
            ]),

            InfoSection::make('HellOotel')->columns(3)->schema([
                TextEntry::make('hellootel_reservation_id')
                    ->label('Reservation ID')
                    ->placeholder('—')
                    ->badge()
                    ->color(fn ($state) => $state ? 'success' : 'gray'),
                TextEntry::make('hellootel_sent_at')
                    ->label('Sent at')
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('Not sent'),
                TextEntry::make('hellootel_response')
                    ->label('API response')
                    ->placeholder('—')
                    ->columnSpanFull()
                    ->extraAttributes(['style' => 'font-family: monospace; font-size: 12px; white-space: pre-wrap; word-break: break-all;']),
            ]),

            InfoSection::make('Source')->columns(2)->schema([
                TextEntry::make('sourceBooking.booking_code')->label('Source booking')
                    ->url(fn (ProcessedBooking $r) => $r->source_booking_id
                        ? \App\Filament\Resources\ExtensionBooking\ExtensionBookingResource::getUrl('view', ['record' => $r->source_booking_id])
                        : null)
                    ->openUrlInNewTab()
                    ->placeholder('—'),
                TextEntry::make('created_at')->label('Processed at')->dateTime('d.m.Y H:i'),
            ]),
        ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListProcessedBookings::route('/'),
            'view'   => ViewProcessedBooking::route('/{record}'),
            'edit'   => EditProcessedBooking::route('/{record}/edit'),
        ];
    }
}
