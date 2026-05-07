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
    protected static ?string $navigationLabel = 'Обработанные брони';
    protected static ?int $navigationSort = 201;

    public static function getNavigationGroup(): ?string
    {
        return 'HellOotel';
    }

    public static function getModelLabel(): string
    {
        return 'Обработанная бронь';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Обработанные брони';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('ID')->sortable(),
                TextColumn::make('booking_code')->label('Код брони')->badge()->color('success')->searchable()->placeholder('—'),
                IconColumn::make('hotel_id')
                    ->label('Сопост.')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('gray')
                    ->getStateUsing(fn (ProcessedBooking $r) => (bool) $r->hotel_id),
                TextColumn::make('hotel_id')->label('hotel_id')->placeholder('—')->searchable(),
                TextColumn::make('hotel_name')->label('Отель (текст)')->limit(30)->placeholder('—')->searchable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('room_type_id')->label('room_type_id')->placeholder('—'),
                TextColumn::make('room_type_name')->label('Тип номера')->limit(25)->placeholder('—'),
                TextColumn::make('status')->label('Статус')->placeholder('—')->badge()->color('warning'),
                TextColumn::make('operator_id')->label('operator_id')->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('operator_name')->label('operator_name')->placeholder('—')->searchable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('agency_id')->label('agency_id')->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('agency_name')->label('Агентство')->placeholder('—')->searchable(),
                TextColumn::make('reservation_date')->label('Дата брони')->date('d.m.Y')->sortable()->placeholder('—'),
                TextColumn::make('reservation_time')->label('Время')->placeholder('—'),
                TextColumn::make('arrival_at')->label('Заезд')->date('d.m.Y')->sortable()->placeholder('—'),
                TextColumn::make('departure_at')->label('Выезд')->date('d.m.Y')->placeholder('—'),
                TextColumn::make('nights')->label('Ночей')->alignCenter()->placeholder('—'),
                TextColumn::make('guest_info')->label('Гости')->limit(25)->placeholder('—')->searchable(),
                TextColumn::make('person_count_adults')->label('Взр.')->alignCenter(),
                TextColumn::make('person_count_children')->label('Дети')->alignCenter(),
                TextColumn::make('person_count_teens')->label('Млад.')->alignCenter(),
                TextColumn::make('price')->label('Стоимость')->placeholder('—')
                    ->formatStateUsing(fn ($state, ProcessedBooking $r) => $state
                        ? number_format($state, 2, '.', ' ') . ($r->currency_code ? ' ' . $r->currency_code : '')
                        : '—'),
                TextColumn::make('commission')->label('Комиссия')->placeholder('—')
                    ->formatStateUsing(fn ($state, ProcessedBooking $r) => $state
                        ? number_format($state, 2, '.', ' ') . ($r->currency_code ? ' ' . $r->currency_code : '')
                        : '—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('total_bonus')->label('Бонус')->alignCenter()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('hm_approval')->label('hm_approval')->alignCenter()->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('payment_status_ag')->label('pay_ag')->alignCenter()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('payment_status_rm')->label('pay_rm')->alignCenter()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('payment_status_cm')->label('pay_cm')->alignCenter()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->actions([
                Action::make('match')
                    ->label('Сопоставить')
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
                        ->label('Сопоставить с API')
                        ->icon('heroicon-o-magnifying-glass')
                        ->color('info')
                        ->requiresConfirmation()
                        ->modalHeading('Сопоставить с HellOotel API')
                        ->modalDescription('Для выбранных записей будет выполнен поиск отеля и типа номера по справочникам API.')
                        ->modalSubmitActionLabel('Сопоставить')
                        ->action(function (Collection $records): void {
                            $matched = 0;
                            foreach ($records as $record) {
                                if (static::runMatch($record)) $matched++;
                            }
                            Notification::make()
                                ->title('Сопоставление завершено')
                                ->body("Обновлено: {$matched} из {$records->count()}")
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
            Section::make('Идентификаторы')->columns(1)->schema([
                TextInput::make('booking_code')->label('booking_code')->columnSpanFull(),

                \Filament\Forms\Components\Grid::make(2)->schema([
                    Select::make('hotel_id')
                        ->label('hotel_id')
                        ->options(fn() => app(HellOotelLookupService::class)->getHotels())
                        ->searchable()
                        ->live()
                        ->afterStateUpdated(fn(Set $set) => $set('room_type_id', null))
                        ->placeholder('— выберите отель —'),
                    TextInput::make('hotel_name')->label('Название отеля (текст из брони)'),
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
                        ->placeholder('— выберите тип номера —'),
                    TextInput::make('room_type_name')->label('Тип номера (текст из брони)'),
                ]),
                \Filament\Forms\Components\Grid::make(2)->schema([
                    Select::make('operator_id')
                        ->label('operator_id')
                        ->options(fn() => app(HellOotelLookupService::class)->getOperators())
                        ->searchable()
                        ->placeholder('— выберите оператора —'),
                    TextInput::make('operator_name')->label('Оператор (текст из брони)'),
                ]),

                \Filament\Forms\Components\Grid::make(2)->schema([
                    TextInput::make('agency_id')->label('agency_id')->numeric(),
                    TextInput::make('agency_name')->label('Агентство (текст из брони)'),
                ]),

                TextInput::make('status')->label('status')->columnSpanFull(),
            ]),

            Section::make('Даты')->columns(4)->schema([
                DatePicker::make('reservation_date')->label('Дата брони')->displayFormat('d.m.Y'),
                TextInput::make('reservation_time')->label('Время брони')->placeholder('HH:MM')->maxLength(5),
                DatePicker::make('arrival_at')->label('Дата заезда')->displayFormat('d.m.Y'),
                DatePicker::make('departure_at')->label('Дата выезда')->displayFormat('d.m.Y'),
                TextInput::make('nights')->label('Ночей')->numeric()->minValue(0),
            ]),

            Section::make('Гости')->columns(2)->schema([
                TextInput::make('guest_info')->label('guest_info'),
                TextInput::make('person_count_adults')->label('person_count_adults')->numeric()->minValue(0),
                TextInput::make('person_count_children')->label('person_count_children')->numeric()->minValue(0),
                TextInput::make('person_count_teens')->label('person_count_teens')->numeric()->minValue(0),
            ]),

            Section::make('Туристы (детально)')->schema([
                Repeater::make('tourists')->label('')->columns(3)
                    ->schema([
                        TextInput::make('last_name')->label('Фамилия'),
                        TextInput::make('first_name')->label('Имя'),
                        TextInput::make('dob')->label('Дата рождения'),
                    ])
                    ->defaultItems(0)
                    ->addActionLabel('Добавить туриста'),
            ]),

            Section::make('Стоимость и статусы')->columns(3)->schema([
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
            InfoSection::make('Идентификаторы')->columns(3)->schema([
                TextEntry::make('booking_code')->label('booking_code')->badge()->color('success')->placeholder('—'),
                TextEntry::make('hotel_id')->label('hotel_id')->placeholder('—'),
                TextEntry::make('hotel_name')->label('Отель (текст)')->placeholder('—'),
                TextEntry::make('room_type_id')->label('room_type_id')->placeholder('—'),
                TextEntry::make('room_type_name')->label('Тип номера')->placeholder('—'),
                TextEntry::make('status')->label('Статус')->badge()->color('warning')->placeholder('—'),
                TextEntry::make('operator_id')->label('operator_id')->placeholder('—'),
                TextEntry::make('operator_name')->label('operator_name')->placeholder('—'),
                TextEntry::make('agency_id')->label('agency_id')->placeholder('—'),
                TextEntry::make('agency_name')->label('Агентство')->placeholder('—'),
            ]),

            InfoSection::make('Даты')->columns(5)->schema([
                TextEntry::make('reservation_date')->label('Дата брони')->date('d.m.Y')->placeholder('—'),
                TextEntry::make('reservation_time')->label('Время брони')->placeholder('—'),
                TextEntry::make('arrival_at')->label('Заезд')->date('d.m.Y')->placeholder('—'),
                TextEntry::make('departure_at')->label('Выезд')->date('d.m.Y')->placeholder('—'),
                TextEntry::make('nights')->label('Ночей')->placeholder('—'),
            ]),

            InfoSection::make('Гости')->columns(2)->schema([
                TextEntry::make('guest_info')->label('guest_info')->placeholder('—'),
                TextEntry::make('person_count_adults')->label('Взрослых'),
                TextEntry::make('person_count_children')->label('Детей'),
                TextEntry::make('person_count_teens')->label('Младенцев'),
            ]),

            InfoSection::make('Туристы (детально)')
                ->hidden(fn (ProcessedBooking $r): bool => empty($r->tourists))
                ->schema([
                    RepeatableEntry::make('tourists')->label('')->columns(3)->schema([
                        TextEntry::make('last_name')->label('Фамилия')->placeholder('—'),
                        TextEntry::make('first_name')->label('Имя')->placeholder('—'),
                        TextEntry::make('dob')->label('Дата рождения')->placeholder('—'),
                    ]),
                ]),

            InfoSection::make('Стоимость и статусы')->columns(4)->schema([
                TextEntry::make('price')->label('Стоимость')
                    ->formatStateUsing(fn ($state, ProcessedBooking $r) => $state
                        ? number_format($state, 2, '.', ' ') . ($r->currency_code ? ' ' . $r->currency_code : '')
                        : '—'),
                TextEntry::make('commission')->label('Комиссия')
                    ->formatStateUsing(fn ($state, ProcessedBooking $r) => $state
                        ? number_format($state, 2, '.', ' ') . ($r->currency_code ? ' ' . $r->currency_code : '')
                        : '—'),
                TextEntry::make('currency_code')->label('Валюта')->placeholder('—'),
                TextEntry::make('total_bonus')->label('total_bonus'),
                TextEntry::make('hm_approval')->label('hm_approval')->placeholder('—'),
                TextEntry::make('payment_status_ag')->label('payment_status_ag'),
                TextEntry::make('payment_status_rm')->label('payment_status_rm'),
                TextEntry::make('payment_status_cm')->label('payment_status_cm'),
            ]),

            InfoSection::make('Источник')->columns(2)->schema([
                TextEntry::make('sourceBooking.booking_code')->label('Исходная бронь')
                    ->url(fn (ProcessedBooking $r) => $r->source_booking_id
                        ? \App\Filament\Resources\ExtensionBooking\ExtensionBookingResource::getUrl('view', ['record' => $r->source_booking_id])
                        : null)
                    ->openUrlInNewTab()
                    ->placeholder('—'),
                TextEntry::make('created_at')->label('Дата обработки')->dateTime('d.m.Y H:i'),
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
                ->title('Отель не найден')
                ->body("Не удалось сопоставить: «{$source->hotel_name}»")
                ->warning()
                ->send();
            return false;
        }

        $record->update([
            'hotel_id'      => $hotelId,
            'room_type_id'  => $roomTypeId,
            'room_type_name' => $roomTypeName,
        ]);

        $roomMsg = $roomTypeName ? ", тип номера: {$roomTypeName} (#{$roomTypeId})" : ', тип номера не найден';
        Notification::make()
            ->title('Сопоставлено')
            ->body("Отель #{$hotelId}{$roomMsg}")
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
