<?php

namespace App\Filament\Resources\EqubSubGroups\Pages;

use App\Filament\Resources\EqubSubGroups\EqubSubGroupResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListEqubSubGroups extends ListRecords
{
    protected static string $resource = EqubSubGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
