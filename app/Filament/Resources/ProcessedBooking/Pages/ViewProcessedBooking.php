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

            EditAction::make(),
            DeleteAction::make(),
        ];
    }
}
