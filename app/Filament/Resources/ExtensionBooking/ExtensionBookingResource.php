<?php

namespace App\Filament\Resources\ExtensionBooking;

use App\Filament\Resources\ExtensionBooking\Pages\ListExtensionBookings;
use App\Filament\Resources\ExtensionBooking\Pages\ViewExtensionBooking;
use App\Models\ExtensionBooking;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ExtensionBookingResource extends Resource
{
    protected static ?string $model = ExtensionBooking::class;
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';
    protected static ?int $navigationSort = 104;

    public static function getNavigationGroup(): ?string
    {
        return 'Chrome Extension';
    }

    public static function getModelLabel(): string
    {
        return 'Booking';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Bookings';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('booking_code')->label('Code')->searchable()->badge()->color('danger')->placeholder('—'),
                TextColumn::make('hotel_name')->label('Hotel / Tour')->searchable()->limit(40),
                TextColumn::make('total_price')->label('Price')->placeholder('—'),
                TextColumn::make('stay_dates')->label('Dates')->placeholder('—'),
                TextColumn::make('guests')->label('Guests')->placeholder('—'),
                TextColumn::make('source_domain')->label('Site')->placeholder('—'),
                TextColumn::make('saved_by')->label('User')->searchable()->placeholder('—'),
                TextColumn::make('created_at')->label('Saved')->dateTime('d.m.Y H:i')->sortable(),
            ])
            ->filters([
                Filter::make('saved_by')
                    ->form([
                        \Filament\Forms\Components\TextInput::make('saved_by')
                            ->label('User email')
                            ->placeholder('user@example.com'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder =>
                        $query->when(
                            $data['saved_by'] ?? null,
                            fn (Builder $q, string $v) => $q->where('saved_by', 'like', "%{$v}%")
                        )
                    ),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Section::make('Booking details')
                ->columns(2)
                ->schema([
                    TextEntry::make('booking_code')->label('Code')->badge()->color('danger')->placeholder('—'),
                    TextEntry::make('hotel_name')->label('Hotel / Tour')->placeholder('—'),
                    TextEntry::make('subtitle')->label('Subtitle')->placeholder('—'),
                    TextEntry::make('total_price')->label('Total price')->placeholder('—'),
                    TextEntry::make('stay_dates')->label('Stay dates')->placeholder('—'),
                    TextEntry::make('guests')->label('Guests')->placeholder('—'),
                    TextEntry::make('meal_plan')->label('Meal plan')->placeholder('—'),
                    TextEntry::make('transfer')->label('Transfer')->placeholder('—'),
                    TextEntry::make('statuses')->label('Statuses')
                        ->formatStateUsing(fn ($state) => is_array($state)
                            ? implode(', ', array_filter($state))
                            : ($state ?? '—')
                        ),
                    TextEntry::make('saved_by')->label('Saved by'),
                ]),

            Section::make('Source')
                ->columns(2)
                ->schema([
                    TextEntry::make('source_url')->label('Page URL')
                        ->url(fn (ExtensionBooking $record): ?string => $record->source_url ?: null)
                        ->openUrlInNewTab()->placeholder('—'),
                    TextEntry::make('source_domain')->label('Domain')->placeholder('—'),
                    TextEntry::make('details_link')->label('Details link')
                        ->url(fn (ExtensionBooking $record): ?string => $record->details_link ?: null)
                        ->openUrlInNewTab()->placeholder('—'),
                    TextEntry::make('page_title')->label('Page title')->placeholder('—'),
                    TextEntry::make('language')->label('Language')->badge()->placeholder('—'),
                    TextEntry::make('captured_at')->label('Captured')->dateTime('d.m.Y H:i')->placeholder('—'),
                    TextEntry::make('created_at')->label('Saved to DB')->dateTime('d.m.Y H:i'),
                ]),
        ]);
    }

    public static function form(\Filament\Forms\Form $form): \Filament\Forms\Form
    {
        return $form->schema([]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListExtensionBookings::route('/'),
            'view'  => ViewExtensionBooking::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
