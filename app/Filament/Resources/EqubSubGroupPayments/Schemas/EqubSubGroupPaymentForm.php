<?php

namespace App\Filament\Resources\EqubSubGroupPayments\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DateTimePicker;
use Filament\Schemas\Schema;
use App\Enums\EqubPaymentMethod;
use App\Enums\EqubPaymentStatus;
use App\Models\EqubSubGroup;
use App\Models\Member;

class EqubSubGroupPaymentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('equb_sub_group_id')
                    ->label('Equb Sub Group')
                    ->relationship('subGroup', 'name')
                    ->required()
                    ->searchable()
                    ->preload()
                    ->live()
                    ->afterStateUpdated(fn ($state, callable $set) => $set('member_id', null)),

                Select::make('member_id')
                    ->label('Member')
                    ->required()
                    ->searchable()
                    ->disabled(fn ($get) => ! $get('equb_sub_group_id'))
                    ->options(function ($get) {
                        $subGroupId = $get('equb_sub_group_id');

                        if (! $subGroupId) {
                            return [];
                        }

                        // Finds the selected SubGroup and retrieves its linked members
                        $subGroup = EqubSubGroup::find($subGroupId);

                        if (! $subGroup) {
                            return [];
                        }

                        return $subGroup->members()
                            ->with('user')
                            ->get()
                            ->mapWithKeys(fn ($member) => [
                                $member->id => trim(($member->user?->phone ?? '') . ' — ' . ($member->full_name ?? ''))
                            ]);
                    }),

                TextInput::make('amount')
                    ->required()
                    ->numeric()
                    ->prefix('Birr'),

                DateTimePicker::make('payment_date')
                    ->required()
                    ->default(now()),

                Select::make('payment_method')
                    ->options(EqubPaymentMethod::class)
                    ->required(),

                Select::make('status')
                    ->options(EqubPaymentStatus::class)
                    ->default(EqubPaymentStatus::Pending)
                    ->required(),

                TextInput::make('reference')
                    ->maxLength(255)
                    ->placeholder('Auto-generated if Chapa'),
            ]);
    }
}