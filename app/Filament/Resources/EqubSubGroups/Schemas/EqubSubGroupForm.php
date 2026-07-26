<?php

namespace App\Filament\Resources\EqubSubGroups\Schemas;

use App\Models\EqubSubGroup;
use App\Models\Member;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class EqubSubGroupForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([

                // 1. SUB GROUP DETAILS BOX
                Section::make('Sub Group Details')
                    ->schema([
                        Select::make('equb_group_id')
                            ->relationship('equbGroup', 'name')
                            ->required(),
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Select::make('inviter_member_id')
                            ->label('Inviter Member')
                            ->options(fn () => Member::with('user')->get()->mapWithKeys(fn ($member) => [
                                $member->id => trim(($member->user?->phone ?? '').' — '.($member->full_name ?? '')),
                            ]))
                            ->searchable()
                            ->getSearchResultsUsing(fn (string $search) => Member::with('user')
                                ->whereHas('user', fn ($q) => $q->where('phone', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%"))
                                ->orWhere('full_name', 'like', "%{$search}%")
                                ->limit(50)
                                ->get()
                                ->mapWithKeys(fn ($member) => [
                                    $member->id => trim(($member->user?->phone ?? '').' — '.($member->full_name ?? '')),
                                ]))
                            ->getOptionLabelUsing(fn ($value): ?string => optional(Member::with('user')->find($value))->exists
                                    ? trim((optional(Member::with('user')->find($value))->user?->phone ?? '').' — '.(optional(Member::find($value))->full_name ?? ''))
                                    : null
                            ),
                        Toggle::make('has_won')
                            ->required(),
                        DateTimePicker::make('win_date'),
                    ])
                    ->columns(2),

                // 2. GROUP STATUS & TRACKING BOX
                Section::make('Group Status & Tracking')
                    ->schema([
                        Placeholder::make('overview')
                            ->hiddenLabel()
                            ->content(function (?EqubSubGroup $record) {
                                if (! $record) {
                                    return new HtmlString('<p class="text-sm text-gray-500 dark:text-gray-400">Save the sub-group first to see financial metrics.</p>');
                                }

                                $equbGroup = $record->equbGroup;

                                // Financial & Duration Metrics
                                $contributionAmount = $equbGroup?->fixed_contribution_amount ?? 0;
                                $durationValue = (int) ($equbGroup?->duration_value ?? 1);

                                // Safely handle enum or string for duration unit
                                $rawUnit = $equbGroup?->duration_unit ?? 'Months';
                                $durationUnit = is_object($rawUnit) && method_exists($rawUnit, '__toString') ? (string) $rawUnit : ($rawUnit->value ?? $rawUnit ?? 'Months');
                                $unitLabel = strtolower($durationUnit);

                                // Group Timelines
                                $groupStartDate = $equbGroup?->start_date ? \Carbon\Carbon::parse($equbGroup->start_date) : $equbGroup?->created_at;
                                $groupEndDate = $equbGroup?->end_date;

                                // Sub Group Timelines (Start = when sub group was created)
                                $subGroupStartDate = $record->created_at ?? now();

                                // Calculate Sub Group End Date using Duration Value * Duration Unit
                                $subGroupEndDate = (clone $subGroupStartDate);
                                if (str_contains($unitLabel, 'month')) {
                                    $subGroupEndDate->addMonths($durationValue);
                                } elseif (str_contains($unitLabel, 'year')) {
                                    $subGroupEndDate->addYears($durationValue);
                                } else {
                                    $subGroupEndDate->addDays($durationValue);
                                }

                                $members = $record->members()->with(['user'])->get();
                                $totalMembers = $members->count();

                                // Total Package Payment (Expected total from all subgroup members across full duration)
                                $totalExpected = $totalMembers * $durationValue * $contributionAmount;
                                
                                // Fetch paid subgroup payments directly from EqubSubGroupPayment
                                $totalPaid = $record->payments()
                                    ->where('status', \App\Enums\EqubPaymentStatus::Paid)
                                    ->sum('amount');
                                    
                                $totalUnpaid = max(0, $totalExpected - $totalPaid);

                                // Format amounts with commas (thousands separators)
                                $formattedTotalExpected = number_format($totalExpected);
                                $formattedTotalPaid = number_format($totalPaid);
                                $formattedTotalUnpaid = number_format($totalUnpaid);

                                $memberBreakdownHtml = '';

                                foreach ($members as $member) {
                                    // Query member payments directly from EqubSubGroupPayment via $record->payments()
                                    $memberPaid = $record->payments()
                                        ->where('member_id', $member->id)
                                        ->where('status', \App\Enums\EqubPaymentStatus::Paid)
                                        ->sum('amount');

                                    $memberExpected = $durationValue * $contributionAmount;
                                    $memberLeft = max(0, $memberExpected - $memberPaid);

                                    $formattedMemberPaid = number_format($memberPaid);
                                    $formattedMemberLeft = number_format($memberLeft);

                                    $phone = $member->user?->phone ?? 'No Phone';
                                    $name = $member->full_name;

                                    $memberBreakdownHtml .= "
                                        <div class='flex flex-row sm:flex-row sm:items-center justify-between py-3 border-b border-gray-200 dark:border-gray-700 text-sm gap-2'>
                                            <div>
                                                <div class='font-semibold text-gray-900 dark:text-gray-100'>{$name} <span class='text-xs font-normal text-gray-500'>({$phone})</span></div>
                                                <div class='text-xs text-gray-400 dark:text-gray-500 mt-0.5'>Duration Value: {$durationValue} ({$durationUnit})</div>
                                            </div>
                                            <div class='flex items-center gap-4 text-xs sm:text-sm'>
                                                <span class='text-gray-600 dark:text-gray-400'>Paid: <span class='text-green-600 dark:text-green-400 font-bold'>Birr {$formattedMemberPaid}</span></span>
                                                <span class='text-gray-600 dark:text-gray-400'>Left: <span class='text-red-500 dark:text-red-400 font-bold'>Birr {$formattedMemberLeft}</span></span>
                                            </div>
                                        </div>
                                    ";
                                }

                                // Format timeline strings nicely
                                $groupStartFormatted = $groupStartDate ? $groupStartDate->format('M d, Y') : 'N/A';
                                $groupEndFormatted = $groupEndDate ? \Carbon\Carbon::parse($groupEndDate)->format('M d, Y') : 'N/A';
                                $subGroupStartFormatted = $subGroupStartDate->format('M d, Y');
                                $subGroupEndFormatted = $subGroupEndDate->format('M d, Y');

                                return new HtmlString("
                                    <div class='w-full space-y-4'>
                                        <!-- Top Metrics Cards (Updated to 4 columns) -->
                                        <div class='grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4 bg-gray-50 dark:bg-gray-800/50 p-4 rounded-xl border border-gray-200 dark:border-gray-700'>
                                            <div class='flex flex-col text-left'>
                                                <span class='text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider'>Total Members</span>
                                                <span class='text-lg font-bold text-gray-900 dark:text-white mt-1'>{$totalMembers}</span>
                                            </div>
                                            <div class='flex flex-col text-left sm:border-l sm:border-gray-200 sm:dark:border-gray-700 sm:pl-4'>
                                                <span class='text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider'>Total Package Payment</span>
                                                <span class='text-lg font-bold text-blue-600 dark:text-blue-400 mt-1'>Birr {$formattedTotalExpected}</span>
                                            </div>
                                            <div class='flex flex-col text-left md:border-l md:border-gray-200 md:dark:border-gray-700 md:pl-4'>
                                                <span class='text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider'>Total Contributed</span>
                                                <span class='text-lg font-bold text-green-600 dark:text-green-400 mt-1'>Birr {$formattedTotalPaid}</span>
                                            </div>
                                            <div class='flex flex-col text-left sm:border-l sm:border-gray-200 sm:dark:border-gray-700 sm:pl-4'>
                                                <span class='text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider'>Total Unpaid</span>
                                                <span class='text-lg font-bold text-red-500 dark:text-red-400 mt-1'>Birr {$formattedTotalUnpaid}</span>
                                            </div>
                                        </div>

                                        <!-- Timeline Info Box -->
                                        <div class='grid grid-cols-1 sm:grid-cols-2 gap-4 bg-white dark:bg-gray-900 p-4 rounded-xl border border-gray-200 dark:border-gray-700 text-xs'>
                                            <div>
                                                <span class='font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 block mb-1'>Equb Group Timeline</span>
                                                <div class='text-gray-800 dark:text-gray-200'>Start: <strong class='font-semibold'>{$groupStartFormatted}</strong></div>
                                                <div class='text-gray-800 dark:text-gray-200'>End: <strong class='font-semibold'>{$groupEndFormatted}</strong></div>
                                            </div>
                                            <div>
                                                <span class='font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 block mb-1'>Sub Group Timeline</span>
                                                <div class='text-gray-800 dark:text-gray-200'>Created Start: <strong class='font-semibold'>{$subGroupStartFormatted}</strong></div>
                                                <div class='text-gray-800 dark:text-gray-200'>Calculated End: <strong class='font-semibold'>{$subGroupEndFormatted}</strong></div>
                                            </div>
                                        </div>

                                        <!-- Member Breakdown List -->
                                        <div>
                                            <h4 class='text-xs font-bold text-gray-900 dark:text-white mb-2 uppercase tracking-wider'>Member Breakdown</h4>
                                            <div class='max-h-[350px] overflow-y-auto pr-2'>
                                                {$memberBreakdownHtml}
                                            </div>
                                        </div>
                                    </div>
                                ");
                            }),
                    ]),
            ]);
    }
}