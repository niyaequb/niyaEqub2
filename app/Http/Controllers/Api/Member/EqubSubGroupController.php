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
    /**
     * GET /api/member/equb-sub-groups
     * Fetches all sub-groups created by or joined by the authenticated user.
     */
    public function index(Request $request)
    {
        try {
            $user = $request->user();

            // Safely resolve the member record
            $member = $user?->member;

            if (! $member) {
                return response()->json([
                    'success' => true,
                    'data'    => [],
                ], 200);
            }

            // Fetch groups where the member is either the inviter OR listed in the members relation
            $subGroups = EqubSubGroup::query()
                ->with(['equbGroup', 'members'])
                ->withCount('members')
                ->where('inviter_member_id', $member->id)
                ->orWhereHas('members', function ($q) use ($member) {
                    $q->where('members.id', $member->id);
                })
                ->latest()
                ->get();

            return response()->json([
                'success' => true,
                'data'    => $subGroups,
            ], 200);

        } catch (\Throwable $e) {
            Log::error('Fetch SubGroups Failed: ' . $e->getMessage(), [
                'exception' => $e,
                'user_id'   => $request->user()?->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch group equbs: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /api/member/equb-sub-groups
     * Creates a new sub-group.
     */
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
