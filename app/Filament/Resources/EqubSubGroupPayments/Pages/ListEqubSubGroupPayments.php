<?php

namespace App\Filament\Resources\EqubSubGroupPayments\Pages;

use App\Filament\Resources\EqubSubGroupPayments\EqubSubGroupPaymentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListEqubSubGroupPayments extends ListRecords
{
    protected static string $resource = EqubSubGroupPaymentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
