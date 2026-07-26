<?php

namespace App\Filament\Resources\EqubSubGroupDraws\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class EqubSubGroupDrawForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('draw_type')
                    ->required(),
                TextInput::make('target_members')
                    ->numeric(),
                DateTimePicker::make('draw_date')
                    ->required(),
                TextInput::make('executed_by_admin_id')
                    ->numeric(),
            ]);
    }
}
