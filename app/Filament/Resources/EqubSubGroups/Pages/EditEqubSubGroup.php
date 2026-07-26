<?php

namespace App\Filament\Resources\EqubSubGroups\Pages;

use App\Filament\Resources\EqubSubGroups\EqubSubGroupResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditEqubSubGroup extends EditRecord
{
    protected static string $resource = EqubSubGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
