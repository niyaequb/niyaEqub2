<?php

namespace App\Http\Controllers\Api\Member;

use App\Http\Controllers\Controller;
use App\Models\EqubSubGroup;
use App\Models\Member;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EqubSubGroupController extends Controller
{
    public function store(Request $request)
    {
        // 1. Validate incoming request
        $validated = $request->validate([
            'equb_group_id' => 'required|exists:equb_groups,id',
            'name'          => 'required|string|max:255',
            'members'       => 'nullable|array',
            'members.*'     => 'string', // Phone numbers
        ]);

        try {
            return DB::transaction(function () use ($request, $validated) {
                $user = $request->user();

                // 2. Safely resolve or create the Inviter Member profile
                $inviterMember = $user->member;

                if (!$inviterMember) {
                    // Create member record on the fly if missing
                    $inviterMember = Member::create([
                        'user_id'   => $user->id,
                        'full_name' => $user->name ?? 'Equb Member',
                    ]);
                }

                // 3. Create the Sub Group
                $subGroup = EqubSubGroup::create([
                    'equb_group_id'     => $validated['equb_group_id'],
                    'name'              => $validated['name'],
                    'inviter_member_id' => $inviterMember->id,
                    'has_won'           => false,
                ]);

                // 4. Attach the inviter as the first member
                $subGroup->members()->syncWithoutDetaching([$inviterMember->id]);

                // 5. Look up additional members by phone number safely
                if (!empty($validated['members'])) {
                    foreach ($validated['members'] as $phone) {
                        $phoneClean = trim($phone);
                        if (empty($phoneClean)) continue;

                        // Find member matching the phone number
                        $member = Member::whereHas('user', function ($q) use ($phoneClean) {
                            $q->where('phone', $phoneClean)
                              ->orWhere('phone', str_replace('+251', '0', $phoneClean));
                        })->first();

                        if ($member) {
                            $subGroup->members()->syncWithoutDetaching([$member->id]);
                        }
                    }
                }

                return response()->json([
                    'message' => 'Group Equb created successfully',
                    'data'    => $subGroup->load('members'),
                ], 201);
            });

        } catch (\Throwable $e) {
            // Log the detailed exception internally
            Log::error('Create SubGroup Failed: ' . $e->getMessage(), [
                'exception' => $e,
                'user_id'   => $request->user()?->id,
            ]);

            // Return clean JSON instead of crashing with a generic 500
            return response()->json([
                'message' => 'Failed to create group equb: ' . $e->getMessage(),
            ], 400);
        }
    }
}
