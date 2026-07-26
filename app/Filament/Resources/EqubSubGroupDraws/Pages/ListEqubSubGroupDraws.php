<?php

namespace App\Filament\Resources\EqubSubGroupDraws\Pages;

use App\Filament\Resources\EqubSubGroupDraws\EqubSubGroupDrawResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListEqubSubGroupDraws extends ListRecords
{
    protected static string $resource = EqubSubGroupDrawResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Execute New Draw'),
        ];
    }
}