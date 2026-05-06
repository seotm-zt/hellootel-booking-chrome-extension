<?php

namespace App\Filament\Resources\ProcessedBooking;

use App\Filament\Resources\ProcessedBooking\Pages\EditProcessedBooking;
use App\Filament\Resources\ProcessedBooking\Pages\ListProcessedBookings;
use App\Filament\Resources\ProcessedBooking\Pages\ViewProcessedBooking;
use App\Models\ProcessedBooking;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Section as InfoSection;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

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
                TextColumn::make('hotel_id')->label('hotel_id')->placeholder('—')->searchable(),
                TextColumn::make('room_type_id')->label('room_type_id')->placeholder('—'),
                TextColumn::make('room_type_name')->label('room_type_name')->placeholder('—'),
                TextColumn::make('operator_id')->label('operator_id')->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('operator_name')->label('operator_name')->placeholder('—')->searchable(),
                TextColumn::make('agency_id')->label('agency_id')->placeholder('—'),
                TextColumn::make('reservation_at')->label('reservation_at')->date('Y-m-d')->sortable()->placeholder('—'),
                TextColumn::make('arrival_at')->label('arrival_at')->date('Y-m-d')->sortable()->placeholder('—'),
                TextColumn::make('departure_at')->label('departure_at')->date('Y-m-d')->placeholder('—'),
                TextColumn::make('guest_info')->label('guest_info')->limit(30)->placeholder('—')->searchable(),
                TextColumn::make('person_count_adults')->label('adults')->alignCenter(),
                TextColumn::make('person_count_children')->label('children')->alignCenter(),
                TextColumn::make('person_count_teens')->label('teens')->alignCenter(),
                TextColumn::make('price')->label('price')->placeholder('—')
                    ->formatStateUsing(fn ($state, ProcessedBooking $r) => $state
                        ? number_format($state, 2, '.', ' ') . ($r->currency_code ? ' ' . $r->currency_code : '')
                        : '—'),
                TextColumn::make('total_bonus')->label('total_bonus')->alignCenter()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('hm_approval')->label('hm_approval')->alignCenter()->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('payment_status_ag')->label('pay_ag')->alignCenter()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('payment_status_rm')->label('pay_rm')->alignCenter()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('payment_status_cm')->label('pay_cm')->alignCenter()->toggleable(isToggledHiddenByDefault: true),
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
            Section::make('Идентификаторы')->columns(2)->schema([
                TextInput::make('booking_code')->label('booking_code'),
                TextInput::make('hotel_id')->label('hotel_id')->numeric(),
                TextInput::make('room_type_id')->label('room_type_id')->numeric(),
                TextInput::make('room_type_name')->label('room_type_name'),
                TextInput::make('operator_id')->label('operator_id')->numeric(),
                TextInput::make('operator_name')->label('operator_name'),
                TextInput::make('agency_id')->label('agency_id')->numeric(),
            ]),

            Section::make('Даты')->columns(3)->schema([
                DatePicker::make('reservation_at')->label('reservation_at'),
                DatePicker::make('arrival_at')->label('arrival_at'),
                DatePicker::make('departure_at')->label('departure_at'),
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
                TextEntry::make('room_type_id')->label('room_type_id')->placeholder('—'),
                TextEntry::make('room_type_name')->label('room_type_name')->placeholder('—'),
                TextEntry::make('operator_id')->label('operator_id')->placeholder('—'),
                TextEntry::make('operator_name')->label('operator_name')->placeholder('—'),
                TextEntry::make('agency_id')->label('agency_id')->placeholder('—'),
            ]),

            InfoSection::make('Даты')->columns(3)->schema([
                TextEntry::make('reservation_at')->label('reservation_at')->date('Y-m-d')->placeholder('—'),
                TextEntry::make('arrival_at')->label('arrival_at')->date('Y-m-d')->placeholder('—'),
                TextEntry::make('departure_at')->label('departure_at')->date('Y-m-d')->placeholder('—'),
            ]),

            InfoSection::make('Гости')->columns(2)->schema([
                TextEntry::make('guest_info')->label('guest_info')->placeholder('—'),
                TextEntry::make('person_count_adults')->label('person_count_adults'),
                TextEntry::make('person_count_children')->label('person_count_children'),
                TextEntry::make('person_count_teens')->label('person_count_teens'),
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
                TextEntry::make('price')->label('price')
                    ->formatStateUsing(fn ($state, ProcessedBooking $r) => $state
                        ? number_format($state, 2, '.', ' ') . ($r->currency_code ? ' ' . $r->currency_code : '')
                        : '—'),
                TextEntry::make('currency_code')->label('currency_code')->placeholder('—'),
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
