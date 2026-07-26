<?php

namespace App\Filament\Resources\EqubSubGroupDraws\Pages;

use App\Filament\Resources\EqubSubGroupDraws\EqubSubGroupDrawResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditEqubSubGroupDraw extends EditRecord
{
    protected static string $resource = EqubSubGroupDrawResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
