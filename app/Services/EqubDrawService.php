<?php

namespace App\Services;

use App\Enums\EqubMembershipStatus;
use App\Enums\EqubPaymentStatus;
use App\Models\EqubDraw;
use App\Models\EqubGroup;
use App\Models\EqubMembership;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EqubDrawService
{
    public function __construct(
        protected SmsService $smsService,
        protected FcmService $fcmService,
    ) {}

    /**
     * Run a draw for an Equb group. Picks a random eligible membership (active, paid, has_won=false),
     * records the draw, updates winner, and sends notifications.
     *
     * @return array{success: bool, draw?: EqubDraw, message?: string}
     */
    public function runDraw(int $equbGroupId, ?int $executedByAdminId = null, bool $skipTopicNotifications = false): array
    {
        $group = EqubGroup::with(['package', 'memberships.member.user'])->find($equbGroupId);

        if (!$group) {
            return ['success' => false, 'message' => 'Equb group not found.'];
        }

        // 1. Check if today is a valid draw date based on start date and frequency
        $enforceSchedule = config('services.equb.enforce_draw_schedule', false);
        if ($group->equb_start_date && $enforceSchedule) {
            $startDate = $group->equb_start_date->copy()->startOfDay();
            $today = now()->startOfDay();

            if ($today->lt($startDate)) {
                return [
                    'success' => false,
                    'message' => 'Equb has not started yet. Start date: ' . $startDate->toDateString(),
                ];
            }

            // Calculate next scheduled draw date (monthly same-day logic)
            $nextDrawDate = $startDate->copy();

            while ($nextDrawDate->lt($today)) {
                $nextDrawDate->addMonthNoOverflow(); // prevents 31 → 2nd type bugs
            }

            // If today is NOT exactly the scheduled date
            if (!$today->equalTo($nextDrawDate)) {
                Log::info("Draw attempt for group {$group->id} on non-scheduled date. Next draw: {$nextDrawDate->toDateString()}");
                return [
                    'success' => false,
                    'message' => 'Today is not a scheduled draw date. Next draw is on ' . $nextDrawDate->toDateString(),
                ];
            }
        }

        // 2. Check if the daily draw limit has been reached for this group
        if (config('services.equb.restrict_draw_frequency', true)) {
            $limit = $this->calculateDailyDrawLimit($group);

            $drawsToday = EqubDraw::query()
                ->where('equb_group_id', $group->id)
                ->whereDate('draw_date', now()->today())
                ->count();

            if ($drawsToday >= $limit) {
                return ['success' => false, 'message' => "Draw limit reached for today ($limit draws allowed)."];
            }
        }

        $eligible = $this->getEligibleMemberships($group);

        Log::info("Equb draw diagnostic: Group {$group->id}, Total Members: {$group->current_members_count}, Eligible Members: " . $eligible->count());

        if ($eligible->isEmpty()) {
            return ['success' => false, 'message' => 'No eligible members for draw.'];
        }

        // Get names of all members in this group
        $memberNames = $group->memberships()
            ->with('member')
            ->get()
            ->pluck('member.user.phone')
            ->filter()
            ->values()
            ->toArray();

        $groupName = $group?->name ?? 'Equb';

        if (!$skipTopicNotifications) {
            // Send FCM "Draw Started" notification to the topic
            $topic = FcmService::equbGroupTopic($group->id);
            $this->fcmService->sendToTopic($topic, [
                'type' => 'equb_draw_started',
                'date_time' => now()->toDateTimeString(),
                'equb_group_id' => (string) $group->id,
                'equb_group_name' => $groupName,
                'member_names' => json_encode($memberNames)
            ], "Equb Draw Started", "A lottery draw for {$groupName} has started!");

            // Optional: simulate the loading state for mobile app for a few seconds if configured
            $delay = config('services.equb.draw_delay', 0);
            if ($delay > 0) {
                sleep($delay);
            }
        }

        $winner = $this->pickWeightedWinner($eligible);

        try {
            $allDrawsForWinner = [];

            $draw = DB::transaction(function () use ($group, $winner, $executedByAdminId, &$allDrawsForWinner) {
                
                // --- SUB-GROUP SUPPORT: Expand the winning ticket to all sub-group members ---
                $winningMembers = $winner->equb_sub_group_id
                    ? EqubMembership::where('equb_sub_group_id', $winner->equb_sub_group_id)->get()
                    : collect([$winner]);

                $firstDraw = null;

                foreach ($winningMembers as $wMember) {
                    $d = EqubDraw::create([
                        'equb_group_id' => $group->id,
                        'draw_date' => now(),
                        'executed_by_admin_id' => $executedByAdminId,
                        'winner_membership_id' => $wMember->id,
                    ]);

                    $wMember->update([
                        'has_won' => true,
                        'win_date' => now(),
                    ]);

                    // Check for individual completion
                    app(\App\Services\EqubMembershipService::class)->completeIfEligible($wMember);

                    $loadedDraw = $d->load(['winnerMembership.member.user', 'equbGroup']);
                    $allDrawsForWinner[] = $loadedDraw;

                    if (!$firstDraw) {
                        $firstDraw = $loadedDraw;
                    }
                }

                // Return the representative draw to keep the method signature intact
                return $firstDraw; 
            });

            // --- SUB-GROUP SUPPORT: Send notifications to ALL winning members of the group ---
            foreach ($allDrawsForWinner as $winningDraw) {
                $this->sendWinnerNotifications($winningDraw);
            }

            if (!$skipTopicNotifications) {
                // Send FCM "Draw Completed" notification to the topic
                $topic = FcmService::equbGroupTopic($group->id);
                $this->fcmService->sendToTopic($topic, [
                    'type' => 'equb_draw_completed',
                    'equb_group_id' => (string) $group->id,
                    'winner_name' => $draw->winnerMembership->member->user->phone ?? 'Unknown',
                    'winner_membership_id' => (string) $draw->winner_membership_id,
                ], "Equb Draw Result", "The winner for {$groupName} has been announced!");
            }

            return ['success' => true, 'draw' => $draw];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Draw failed: ' . $e->getMessage()];
        }
    }

    /**
     * Run all remaining draws allowed for today for a specific group.
     * This handles batch notifications and multiple draws in one session.
     */
    public function runRemainingDrawsForToday(int $groupId, ?int $executedByAdminId = null): array
    {
        $group = EqubGroup::find($groupId);
        if (!$group) {
            return ['success' => false, 'message' => 'Equb group not found.'];
        }

        $limit = $this->calculateDailyDrawLimit($group);
        $drawsToday = EqubDraw::query()
            ->where('equb_group_id', $group->id)
            ->whereDate('draw_date', now()->today())
            ->count();

        $remaining = $limit - $drawsToday;

        Log::info("Equb batch draw diagnostic: Group {$group->id}, Daily Limit: {$limit}, Drawn Today: {$drawsToday}, Remaining: {$remaining}");

        if ($remaining <= 0) {
            return ['success' => false, 'message' => "Limit reached for today ({$limit} draws)."];
        }

        $eligible = $this->getEligibleMemberships($group);
        if ($eligible->isEmpty()) {
            return ['success' => false, 'message' => 'No eligible members for draw.'];
        }

        // We only draw as many as we have eligible members, capped by 'remaining'
        $toDrawCount = min($remaining, $eligible->count());
        $groupName = $group->name ?? 'Equb';
        $topic = FcmService::equbGroupTopic($group->id);

        // 1. Send single "Draw Started" notification for all winners
        $memberNames = $group->memberships()
            ->with('member')
            ->get()
            ->pluck('member.id')
            ->filter()
            ->values()
            ->toArray();

        // i want to update the member Names list to be like 00.id like 001, 002, 003 and so on instead of the phone numbers
        $memberNames = array_map(fn($id) => str_pad($id, 3, '0', STR_PAD_LEFT), $memberNames);

        $this->fcmService->sendToTopic($topic, [
            'type' => 'equb_draw_started',
            'date_time' => now()->toDateTimeString(),
            'equb_group_id' => (string) $group->id,
            'equb_group_name' => $groupName,
            'draw_count' => (string) $toDrawCount,
            'member_names' => json_encode($memberNames)
        ], "Equb Draw Started", "{$toDrawCount} lottery winner(s) will be announced for {$groupName}!");

        // 2. Single Delay for the whole batch
        $delay = config('services.equb.draw_delay', 0);
        if ($delay > 0) {
            sleep($delay);
        }

        $winners = collect();
        $tempEligible = clone $eligible;
        for ($i = 0; $i < $toDrawCount; $i++) {
            $winner = $this->pickWeightedWinner($tempEligible);
            if ($winner) {
                $winners->push($winner);
                $tempEligible = $tempEligible->reject(fn($m) => $m->id === $winner->id);
            }
        }
        $draws = [];
        $winnerNames = [];

        try {
            DB::beginTransaction();

            foreach ($winners as $winnerRep) {
                
                // --- SUB-GROUP SUPPORT: Expand the chosen ticket for batch drawing ---
                $winningMembers = $winnerRep->equb_sub_group_id
                    ? EqubMembership::where('equb_sub_group_id', $winnerRep->equb_sub_group_id)->get()
                    : collect([$winnerRep]);

                foreach ($winningMembers as $wMember) {
                    $draw = EqubDraw::create([
                        'equb_group_id' => $group->id,
                        'draw_date' => now(),
                        'executed_by_admin_id' => $executedByAdminId,
                        'winner_membership_id' => $wMember->id,
                    ]);

                    $wMember->update([
                        'has_won' => true,
                        'win_date' => now(),
                    ]);

                    // Check for individual completion
                    app(\App\Services\EqubMembershipService::class)->completeIfEligible($wMember);

                    $draws[] = $draw->load(['winnerMembership.member.user', 'equbGroup']);
                    $winnerNames[] = $wMember->member->id ?? 'Unknown Member';

                    $this->sendWinnerNotifications($draw);
                }
            }

            $winnerNames = array_map(fn($id) => str_pad($id, 3, '0', STR_PAD_LEFT), $winnerNames);

            DB::commit();

            // 3. Send single "Draw Completed" notification with all winners
            $winnersList = implode(', ', $winnerNames);
            $this->fcmService->sendToTopic($topic, [
                'type' => 'equb_draw_completed',
                'equb_group_id' => (string) $group->id,
                'winners' => json_encode($winnerNames),
                'winner_name' => $winnerNames[0] ?? 'Unknown', // Fallback for apps expecting single winner
                'draw_count' => (string) $toDrawCount
            ], "Equb Draw Result", "The winners for {$groupName} are: {$winnersList}");

            return [
                'success' => true,
                'draw_count' => $toDrawCount,
                'winners' => $winnerNames,
                'draws' => $draws
            ];
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error("Batch draw failed for group {$group->id}: " . $e->getMessage());
            return ['success' => false, 'message' => 'Draw failed: ' . $e->getMessage()];
        }
    }

    /**
     * Calculate the maximum number of draws allowed for a group today.
     */
    public function calculateDailyDrawLimit(EqubGroup $group): int
    {
        $restrict = config('services.equb.restrict_draw_frequency', true);
        $membersPerDraw = config('services.equb.members_per_draw', 50);

        Log::info("Equb limit calculation diagnostic: restrict=" . ($restrict ? 'true' : 'false') . ", membersPerDraw=" . $membersPerDraw . ", current_members=" . $group->current_members_count);

        if (!$restrict) {
            return 999;
        }

        return max(1, (int) ceil($group->current_members_count / $membersPerDraw));
    }

    /**
     * Get memberships eligible for draw: active, has_won=false, and (simplified) at least one paid payment.
     * Integrates Sub-Group logic to ensure strict eligibility across grouped members.
     */
    protected function getEligibleMemberships(EqubGroup $group)
    {
        $baseEligible = EqubMembership::query()
            ->with('cohort')
            ->where('equb_group_id', $group->id)
            ->where('status', EqubMembershipStatus::Active)
            ->where('has_won', false)
            ->whereHas('payments', fn($q) => $q->where('status', EqubPaymentStatus::Paid))
            ->get();

        $finalEligible = collect();
        
        // --- SUB-GROUP SUPPORT: Group memberships to evaluate their collective standing ---
        $grouped = $baseEligible->groupBy('equb_sub_group_id');

        foreach ($grouped as $subGroupId => $members) {
            if (empty($subGroupId)) {
                // Individuals (No Sub-Group) - add all of them normally
                foreach ($members as $member) {
                    $finalEligible->push($member);
                }
            } else {
                // Sub-group: We must ensure ALL active members of this subgroup are eligible
                $totalActiveInSubGroup = EqubMembership::where('equb_sub_group_id', $subGroupId)
                    ->where('status', EqubMembershipStatus::Active)
                    ->count();

                // If the number of eligible members matches the total active members in the subgroup
                // it means NO ONE in the subgroup is defaulting.
                if ($members->count() === $totalActiveInSubGroup) {
                    // Add only the FIRST member as the "Representative Ticket" for the draw
                    // so the group gets exactly one combined chance to win.
                    $finalEligible->push($members->first());
                }
            }
        }

        return $finalEligible;
    }

    protected function sendWinnerNotifications(EqubDraw $draw): void
    {
        $membership = $draw->winnerMembership;
        $member = $membership->member;
        $user = $member->user;
        $phone = $user?->phone;
        $groupName = $draw->equbGroup?->name ?? 'Niya Equb';
        
        $message = "Congratulations! You have won the draw for {$groupName}. " . 'Win date: ' . $draw->draw_date->format('Y-m-d');

        if ($phone) {
            $this->smsService->sendSms($phone, $message, null, $draw);
        }
    }

    /**
     * Pick a winner from a collection of memberships using weighted randomness based on cohort weights.
     */
    protected function pickWeightedWinner($memberships): ?EqubMembership
    {
        if ($memberships->isEmpty()) {
            return null;
        }

        // 1. Calculate total weight
        $totalWeight = $memberships->sum(function ($membership) {
            return (float) ($membership->cohort->win_weight ?? 1.00);
        });

        if ($totalWeight <= 0) {
            return $memberships->random();
        }

        // 2. Pick a random number
        $random = mt_rand(0, mt_getrandmax()) / mt_getrandmax() * $totalWeight;

        // 3. Find the membership
        $currentWeight = 0;
        foreach ($memberships as $membership) {
            $currentWeight += (float) ($membership->cohort->win_weight ?? 1.00);
            if ($random <= $currentWeight) {
                return $membership;
            }
        }

        return $memberships->last();
    }

    /**
     * Identify and notify sub-group members who have not paid, holding up their group.
     */
    public function notifySubGroupDefaulters(int $equbGroupId): array
    {
        $group = EqubGroup::find($equbGroupId);
        
        if (!$group) {
            return ['success' => false, 'message' => 'Equb group not found.'];
        }

        // Get all active sub-groups for this Equb
        $subGroups = \App\Models\EqubSubGroup::where('equb_group_id', $group->id)
            ->where('has_won', false)
            ->with(['memberships.member.user', 'memberships.payments' => function($q) {
                // Adjust this logic based on how you determine if the CURRENT round is paid
                $q->where('status', EqubPaymentStatus::Paid); 
            }])
            ->get();

        $notificationsSent = 0;
        $defaultersList = [];

        foreach ($subGroups as $subGroup) {
            $members = $subGroup->memberships->where('status', EqubMembershipStatus::Active);
            $unpaidMembers = collect();

            // Find exactly who didn't pay
            foreach ($members as $member) {
                if ($member->payments->isEmpty()) {
                    $unpaidMembers->push($member);
                }
            }

            // If there are unpaid members, the group is held back. Notify them!
            if ($unpaidMembers->isNotEmpty()) {
                foreach ($unpaidMembers as $defaulter) {
                    $phone = $defaulter->member->user->phone ?? null;
                    $groupName = $subGroup->name;
                    
                    if ($phone) {
                        $message = "Urgent: Your Equb group '{$groupName}' is on hold! You have pending payments preventing your group from entering the draw. Please pay immediately.";
                        $this->smsService->sendSms($phone, $message);
                        $notificationsSent++;
                        $defaultersList[] = $phone;
                    }
                }
            }
        }

        return [
            'success' => true, 
            'message' => "Notified {$notificationsSent} defaulters.",
            'defaulters' => $defaultersList
        ];
    }
}