<?php

namespace App\Filament\Resources\EqubSubGroupPayments;

use App\Filament\Resources\EqubSubGroupPayments\Schemas\EqubSubGroupPaymentForm;
use App\Models\EqubSubGroupPayment;
use BackedEnum;
use Filament\Actions;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Filament\Tables;
use Illuminate\Support\Facades\Auth;

class EqubSubGroupPaymentResource extends Resource
{
    protected static ?string $model = EqubSubGroupPayment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    public static function getNavigationLabel(): string
    {
        return 'Sub Group Payments';
    }

    public static function getModelLabel(): string
    {
        return 'Sub Group Payment';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Sub Group Payments';
    }

    public static function getNavigationGroup(): ?string
    {
        return __('filament.nav.equb');
    }

    public static function form(Schema $schema): Schema
    {
        return EqubSubGroupPaymentForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('subGroup.name')
                    ->label('Sub Group')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('member.full_name')
                    ->label('Member Name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('amount')
                    ->money('ETB')
                    ->sortable(),
                Tables\Columns\TextColumn::make('payment_method')
                    ->badge(),
                Tables\Columns\TextColumn::make('status')
                    ->badge(),
                Tables\Columns\TextColumn::make('payment_date')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                Actions\EditAction::make(),
            ])
            ->toolbarActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEqubSubGroupPayments::route('/'),
            'create' => Pages\CreateEqubSubGroupPayment::route('/create'),
            'edit' => Pages\EditEqubSubGroupPayment::route('/{record}/edit'),
        ];
    }
}