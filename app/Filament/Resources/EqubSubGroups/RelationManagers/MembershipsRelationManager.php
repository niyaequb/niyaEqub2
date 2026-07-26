<?php

namespace App\Filament\Resources\EqubSubGroups\RelationManagers;

use App\Models\Member;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Validation\ValidationException;

class MembersRelationManager extends RelationManager
{
    protected static string $relationship = 'members';

    protected static ?string $recordTitleAttribute = 'full_name';

    protected static ?string $title = 'Sub Group Members';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.phone')
                    ->label('Phone Number')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('full_name')
                    ->label('Member Name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->label('Joined Date')
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Action::make('addMember')
                    ->label('Add Member to Sub Group')
                    ->modalHeading('Assign Member to Sub Group')
                    ->form([
                        Select::make('member_id')
                            ->label('Select Member')
                            ->options(fn () => Member::with('user')->get()->mapWithKeys(fn ($member) => [
                                $member->id => trim(($member->user?->phone ?? 'No Phone') . ' — ' . ($member->full_name ?? ''))
                            ]))
                            ->searchable()
                            ->getSearchResultsUsing(fn (string $search) => Member::with('user')
                                ->whereHas('user', fn ($q) => $q->where('phone', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%"))
                                ->orWhere('full_name', 'like', "%{$search}%")
                                ->limit(50)
                                ->get()
                                ->mapWithKeys(fn ($member) => [
                                    $member->id => trim(($member->user?->phone ?? 'No Phone') . ' — ' . ($member->full_name ?? ''))
                                ]))
                            ->required(),
                    ])
                    ->action(function (array $data): void {
                        $subGroup = $this->getOwnerRecord();

                        // Prevent duplicates gracefully within the same sub-group
                        if ($subGroup->members()->where('member_id', $data['member_id'])->exists()) {
                            throw ValidationException::withMessages([
                                'data.member_id' => 'This member is already registered in this sub-group.',
                            ]);
                        }

                        $subGroup->members()->attach($data['member_id']);
                    }),
            ])
            ->actions([
                Action::make('removeMember')
                    ->label('Remove')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (Member $record): void {
                        $subGroup = $this->getOwnerRecord();
                        $subGroup->members()->detach($record->id);
                    }),
            ])
            ->bulkActions([
                BulkActionGroup::make([]),
            ]);
    }
}