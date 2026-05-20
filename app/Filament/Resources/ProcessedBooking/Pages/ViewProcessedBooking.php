<?php

namespace App\Filament\Resources\ProcessedBooking\Pages;

use App\Filament\Resources\ProcessedBooking\ProcessedBookingResource;
use App\Models\ProcessedBooking;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

class ViewProcessedBooking extends ViewRecord
{
    protected static string $resource = ProcessedBookingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('get_vote')
                ->label('Get Vote')
                ->icon('heroicon-o-star')
                ->color('warning')
                ->visible(fn () => (bool) $this->record->hotel_id)
                ->action(function (): void {
                    ProcessedBookingResource::runGetVote($this->record);
                    $this->refreshFormData(['hotel_vote']);
                }),

            Action::make('send_vote')
                ->label('Send Vote')
                ->icon('heroicon-o-paper-airplane')
                ->color('success')
                ->visible(fn () => $this->record->hotel_id && $this->record->hotel_vote !== null)
                ->requiresConfirmation()
                ->modalHeading('Send Vote to HellOotel')
                ->modalDescription(fn () => 'Send rating ' . str_repeat('★', (int) $this->record->hotel_vote) . " for hotel #{$this->record->hotel_id}?")
                ->action(function (): void {
                    ProcessedBookingResource::runSendVote($this->record);
                }),

            EditAction::make(),
            DeleteAction::make(),
        ];
    }
}
