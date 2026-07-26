<?php

namespace App\Filament\Resources\EqubSubGroupPayments\Pages;

use App\Filament\Resources\EqubSubGroupPayments\EqubSubGroupPaymentResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditEqubSubGroupPayment extends EditRecord
{
    protected static string $resource = EqubSubGroupPaymentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
