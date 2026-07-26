<?php

namespace App\Filament\Resources\EqubSubGroups\Tables;

use App\Models\EqubSubGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class EqubSubGroupsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('members_count')
                    ->label('Total Members')
                    ->badge()
                    ->color('info')
                    ->sortable(),

                // 1. PAYMENT TRACKING COLUMN
                TextColumn::make('payment_status')
                    ->label('Payment Tracking')
                    ->getStateUsing(function (EqubSubGroup $record): string {
                        $totalMembers = $record->members_count;

                        if ($totalMembers === 0) {
                            return 'No Members';
                        }

                        $equbGroup = $record->equbGroup;
                        $contributionAmount = $equbGroup?->fixed_contribution_amount ?? 0;
                        $durationValue = (int) ($equbGroup?->duration_value ?? 1);

                        // Financial totals
                        $totalExpected = $totalMembers * $durationValue * $contributionAmount;
                        $totalPaid = $record->payments()
                            ->where('status', \App\Enums\EqubPaymentStatus::Paid)
                            ->sum('amount');

                        $totalUnpaid = max(0, $totalExpected - $totalPaid);

                        // If the full package amount is completely paid off
                        if ($totalExpected > 0 && $totalUnpaid == 0) {
                            return 'All Paid';
                        }

                        // Otherwise display the total unpaid balance
                        $formattedUnpaid = number_format($totalUnpaid);

                        return "Unpaid (Birr {$formattedUnpaid})";
                    })
                    ->badge()
                    ->color(fn (string $state): string => match (true) {
                        $state === 'All Paid' => 'success',
                        str_starts_with($state, 'Unpaid') => 'danger',
                        $state === 'No Members' => 'gray',
                        default => 'warning',
                    }),

                // 2. TOTAL CONTRIBUTED COLUMN
                TextColumn::make('total_contributed')
                    ->label('Total Contributed')
                    ->getStateUsing(function (EqubSubGroup $record): string {
                        $totalPaid = $record->payments()
                            ->where('status', \App\Enums\EqubPaymentStatus::Paid)
                            ->sum('amount');

                        return 'Birr '.number_format($totalPaid);
                    })
                    ->badge()
                    ->color('success'),

                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->label('Group Name'),

                TextColumn::make('equbGroup.name')
                    ->sortable()
                    ->label('Equb Group'),

                TextColumn::make('inviter')
                    ->label('Inviter Phone')
                    ->formatStateUsing(function ($state, $record) {
                        if (! $record->inviter) {
                            return '—';
                        }

                        $member = $record->inviter;
                        $phone = $member->user?->phone ?? 'N/A';
                        $name = $member->full_name ?? '';

                        return $name ? "{$phone} ({$name})" : $phone;
                    }),

                IconColumn::make('has_won')
                    ->boolean()
                    ->label('Won?'),

                TextColumn::make('win_date')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}