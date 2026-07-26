<?php

namespace App\Filament\Resources\EqubSubGroupDraws\Pages;

use App\Filament\Resources\EqubSubGroupDraws\EqubSubGroupDrawResource;
use App\Models\EqubSubGroup;
use Filament\Resources\Pages\CreateRecord;

class CreateEqubSubGroupDraw extends CreateRecord
{
    protected static string $resource = EqubSubGroupDrawResource::class;

    // Mutate Data before saving main record
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['executed_by_admin_id'] = auth()->id();
        $data['draw_date'] = now();

        return $data;
    }

    // Execute the Lottery Engine AFTER the main draw record is created
    protected function afterCreate(): void
    {
        $record = $this->record;
        $data = $this->data; // This holds the raw form data, including 'manual_winners'

        $winningSubGroupIds = [];

        if ($record->draw_type === 'random') {
            $target = (int) $record->target_members;
            $currentTotalMembers = 0;

            // Fetch all eligible groups (has NOT won yet) and shuffle randomly
            $eligibleGroups = EqubSubGroup::withCount('members')
                ->where('has_won', false)
                ->inRandomOrder()
                ->get();

            // Run through random groups until target is met
            foreach ($eligibleGroups as $group) {
                if ($currentTotalMembers >= $target) {
                    break;
                }
                
                $winningSubGroupIds[] = $group->id;
                $currentTotalMembers += $group->members_count;
            }
        } else {
            // If Manual, just grab from the field we created
            $winningSubGroupIds = $data['manual_winners'] ?? [];
        }

        // Apply Results
        if (!empty($winningSubGroupIds)) {
            // 1. Attach winners to the Pivot table
            $record->winners()->attach($winningSubGroupIds);

            // 2. Mark those Sub Groups as won in their original table
            EqubSubGroup::whereIn('id', $winningSubGroupIds)->update([
                'has_won' => true,
                'win_date' => now(),
            ]);
        }
    }
}