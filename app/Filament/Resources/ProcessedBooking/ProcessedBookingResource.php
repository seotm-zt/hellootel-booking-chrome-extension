<?php

namespace App\Filament\Resources\ProcessedBooking;

use App\Filament\Resources\ProcessedBooking\Pages\EditProcessedBooking;
use App\Filament\Resources\ProcessedBooking\Pages\ListProcessedBookings;
use App\Filament\Resources\ProcessedBooking\Pages\ViewProcessedBooking;
use App\Models\ProcessedBooking;
use App\Services\BookingProcessorService;
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
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class ProcessedBookingResource extends Resource
{
    protected static ?string $model = ProcessedBooking::class;
    protected static ?string $navigationIcon = 'heroicon-o-check-badge';
    protected static ?string $navigationLabel = 'Processed Bookings';
    protected static ?int $navigationSort = 201;

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
            ->columns([
                TextColumn::make('id')->label('ID')->sortable(),
                TextColumn::make('booking_code')->label('Code')->badge()->color('success')->searchable()->placeholder('—'),
                IconColumn::make('hotel_id')
                    ->label('Matched')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('gray')
                    ->getStateUsing(fn (ProcessedBooking $r) => (bool) $r->hotel_id),
                TextColumn::make('hotel_id')->label('hotel_id')->placeholder('—')->searchable(),
                TextColumn::make('hotel_name')->label('Hotel (text)')->limit(30)->placeholder('—')->searchable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('room_type_id')->label('room_type_id')->placeholder('—'),
                TextColumn::make('room_type_name')->label('Room type')->limit(25)->placeholder('—'),
                TextColumn::make('status')->label('Status')->placeholder('—')->badge()->color('warning'),
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
                TextColumn::make('operator_name')->label('operator_name')->placeholder('—')->searchable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('agency_id')->label('agency_id')->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('agency_name')->label('Agency')->placeholder('—')->searchable(),
                TextColumn::make('reservation_date')->label('Res. date')->date('d.m.Y')->sortable()->placeholder('—'),
                TextColumn::make('reservation_time')->label('Time')->placeholder('—'),
                TextColumn::make('arrival_at')->label('Check-in')->date('d.m.Y')->sortable()->placeholder('—'),
                TextColumn::make('departure_at')->label('Check-out')->date('d.m.Y')->placeholder('—'),
                TextColumn::make('nights')->label('Nights')->alignCenter()->placeholder('—'),
                TextColumn::make('guest_info')->label('Guests')->limit(25)->placeholder('—')->searchable(),
                TextColumn::make('person_count_adults')->label('Adt.')->alignCenter(),
                TextColumn::make('person_count_children')->label('Chd.')->alignCenter(),
                TextColumn::make('person_count_teens')->label('Inf.')->alignCenter(),
                TextColumn::make('price')->label('Price')->placeholder('—')
                    ->formatStateUsing(fn ($state, ProcessedBooking $r) => $state
                        ? number_format($state, 2, '.', ' ') . ($r->currency_code ? ' ' . $r->currency_code : '')
                        : '—'),
                TextColumn::make('commission')->label('Commission')->placeholder('—')
                    ->formatStateUsing(fn ($state, ProcessedBooking $r) => $state
                        ? number_format($state, 2, '.', ' ') . ($r->currency_code ? ' ' . $r->currency_code : '')
                        : '—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('total_bonus')->label('Bonus')->alignCenter()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('hm_approval')->label('hm_approval')->alignCenter()->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('payment_status_ag')->label('pay_ag')->alignCenter()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('payment_status_rm')->label('pay_rm')->alignCenter()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('payment_status_cm')->label('pay_cm')->alignCenter()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->actions([
                Action::make('match')
                    ->label('Match')
                    ->icon('heroicon-o-magnifying-glass')
                    ->color('info')
                    ->action(function (ProcessedBooking $record): void {
                        static::runMatch($record);
                    }),
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    BulkAction::make('match_all')
                        ->label('Match with API')
                        ->icon('heroicon-o-magnifying-glass')
                        ->color('info')
                        ->requiresConfirmation()
                        ->modalHeading('Match with HellOotel API')
                        ->modalDescription('The selected records will be matched against the HellOotel hotel and room type directories.')
                        ->modalSubmitActionLabel('Match')
                        ->action(function (Collection $records): void {
                            $matched = 0;
                            foreach ($records as $record) {
                                if (static::runMatch($record)) $matched++;
                            }
                            Notification::make()
                                ->title('Matching complete')
                                ->body("Updated: {$matched} of {$records->count()}")
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
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

            Section::make('Dates')->columns(4)->schema([
                DatePicker::make('reservation_date')->label('Reservation date')->displayFormat('d.m.Y'),
                TextInput::make('reservation_time')->label('Reservation time')->placeholder('HH:MM')->maxLength(5),
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
                        TextInput::make('dob')->label('Date of birth'),
                    ])
                    ->defaultItems(0)
                    ->addActionLabel('Add tourist'),
            ]),

            Section::make('Price & statuses')->columns(3)->schema([
                TextInput::make('price')->label('price')->numeric(),
                TextInput::make('commission')->label('commission')->numeric(),
                TextInput::make('currency_code')->label('currency_code')->maxLength(3)->placeholder('EUR'),
                TextInput::make('total_bonus')->label('total_bonus')->numeric(),
                TextInput::make('hm_approval')->label('hm_approval')->numeric(),
                TextInput::make('payment_status_ag')->label('payment_status_ag')->numeric(),
                TextInput::make('payment_status_rm')->label('payment_status_rm')->numeric(),
                TextInput::make('payment_status_cm')->label('payment_status_cm')->numeric(),
            ]),
        ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            InfoSection::make('Identifiers')->columns(3)->schema([
                TextEntry::make('booking_code')->label('booking_code')->badge()->color('success')->placeholder('—'),
                TextEntry::make('hotel_id')->label('hotel_id')->placeholder('—'),
                TextEntry::make('hotel_name')->label('Hotel (text)')->placeholder('—'),
                TextEntry::make('room_type_id')->label('room_type_id')->placeholder('—'),
                TextEntry::make('room_type_name')->label('Room type')->placeholder('—'),
                TextEntry::make('status')->label('Status')->badge()->color('warning')->placeholder('—'),
                TextEntry::make('operator_id')->label('operator_id')->placeholder('—'),
                TextEntry::make('operator_name')->label('operator_name')->placeholder('—'),
                TextEntry::make('agency_id')->label('agency_id')->placeholder('—'),
                TextEntry::make('agency_name')->label('Agency')->placeholder('—'),
            ]),

            InfoSection::make('Dates')->columns(5)->schema([
                TextEntry::make('reservation_date')->label('Reservation date')->date('d.m.Y')->placeholder('—'),
                TextEntry::make('reservation_time')->label('Reservation time')->placeholder('—'),
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

            InfoSection::make('Price & statuses')->columns(4)->schema([
                TextEntry::make('price')->label('Price')
                    ->formatStateUsing(fn ($state, ProcessedBooking $r) => $state
                        ? number_format($state, 2, '.', ' ') . ($r->currency_code ? ' ' . $r->currency_code : '')
                        : '—'),
                TextEntry::make('commission')->label('Commission')
                    ->formatStateUsing(fn ($state, ProcessedBooking $r) => $state
                        ? number_format($state, 2, '.', ' ') . ($r->currency_code ? ' ' . $r->currency_code : '')
                        : '—'),
                TextEntry::make('currency_code')->label('Currency')->placeholder('—'),
                TextEntry::make('total_bonus')->label('total_bonus'),
                TextEntry::make('hm_approval')->label('hm_approval')->placeholder('—'),
                TextEntry::make('payment_status_ag')->label('payment_status_ag'),
                TextEntry::make('payment_status_rm')->label('payment_status_rm'),
                TextEntry::make('payment_status_cm')->label('payment_status_cm'),
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

    // Returns true if hotel_id was resolved
    private static function runMatch(ProcessedBooking $record): bool
    {
        $source = $record->sourceBooking;
        if (!$source) return false;

        $service = app(BookingProcessorService::class);
        [$hotelId, $roomTypeId, $roomTypeName] = $service->matchHotelAndRoom($source);

        if (!$hotelId) {
            Notification::make()
                ->title('Hotel not found')
                ->body("Could not match: \"{$source->hotel_name}\"")
                ->warning()
                ->send();
            return false;
        }

        $record->update([
            'hotel_id'      => $hotelId,
            'room_type_id'  => $roomTypeId,
            'room_type_name' => $roomTypeName,
        ]);

        $roomMsg = $roomTypeName ? ", room type: {$roomTypeName} (#{$roomTypeId})" : ', room type not found';
        Notification::make()
            ->title('Matched')
            ->body("Hotel #{$hotelId}{$roomMsg}")
            ->success()
            ->send();

        return true;
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
