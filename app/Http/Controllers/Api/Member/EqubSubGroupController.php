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
     */
    public function index(Request $request)
    {
        try {
            $user = $request->user();
            $member = $user?->member;

            if (!$member) {
                return response()->json(['success' => true, 'data' => []], 200);
            }

            $subGroups = EqubSubGroup::query()
                ->with(['equbGroup', 'members.user'])
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
            Log::error('Fetch SubGroups Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /api/member/equb-sub-groups/{id}
     */
    public function show(Request $request, $id)
    {
        try {
            $subGroup = EqubSubGroup::with(['equbGroup', 'members.user', 'inviter'])
                ->withCount('members')
                ->findOrFail($id);

            return response()->json([
                'success' => true,
                'data'    => $subGroup,
            ], 200);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Group not found'], 404);
        }
    }

    /**
     * POST /api/member/equb-sub-groups
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'equb_group_id' => 'required|exists:equb_groups,id',
            'name'          => 'required|string|max:255',
            'members'       => 'nullable|array',
            'members.*'     => 'string',
        ]);

        try {
            return DB::transaction(function () use ($request, $validated) {
                $user = $request->user();
                $inviterMember = $user->member;

                if (!$inviterMember) {
                    $inviterMember = Member::create([
                        'user_id'   => $user->id,
                        'full_name' => $user->name ?? 'Equb Member',
                    ]);
                }

                $subGroup = EqubSubGroup::create([
                    'equb_group_id'     => $validated['equb_group_id'],
                    'name'              => $validated['name'],
                    'inviter_member_id' => $inviterMember->id,
                    'has_won'           => false,
                ]);

                // Sync inviter as member
                $subGroup->members()->syncWithoutDetaching([$inviterMember->id]);

                if (!empty($validated['members'])) {
                    foreach ($validated['members'] as $phone) {
                        $phoneClean = trim($phone);
                        if (empty($phoneClean)) continue;

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
                    'data'    => $subGroup->load(['equbGroup', 'members.user']),
                ], 201);
            });
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Creation failed: ' . $e->getMessage()], 400);
        }
    }

    /**
     * POST /api/member/equb-sub-groups/{id}/add-member
     */
    public function addMember(Request $request, $id)
    {
        $request->validate(['phone' => 'required|string']);

        $subGroup = EqubSubGroup::findOrFail($id);
        $phoneClean = trim($request->phone);

        $member = Member::whereHas('user', function ($q) use ($phoneClean) {
            $q->where('phone', $phoneClean)
              ->orWhere('phone', str_replace('+251', '0', $phoneClean));
        })->first();

        if (!$member) {
            return response()->json(['message' => 'No registered user found with this phone number.'], 404);
        }

        $subGroup->members()->syncWithoutDetaching([$member->id]);

        return response()->json([
            'message' => 'Member added successfully',
            'data'    => $subGroup->load(['equbGroup', 'members.user']),
        ]);
    }

    /**
     * DELETE /api/member/equb-sub-groups/{id}/remove-member/{memberId}
     */
    public function removeMember($id, $memberId)
    {
        $subGroup = EqubSubGroup::findOrFail($id);
        $subGroup->members()->detach($memberId);

        return response()->json([
            'message' => 'Member removed successfully',
            'data'    => $subGroup->load(['equbGroup', 'members.user']),
        ]);
    }

    /**
     * GET /api/member/members/search?query=...
     *
     * Looks up real members by phone or name, for the "add member"
     * autocomplete fields in the app (previously backed by mock data).
     */
    public function searchMembers(Request $request)
    {
        $query = trim($request->input('query', ''));

        if (empty($query)) {
            return response()->json(['data' => []]);
        }

        // Handle phone format variations (+251976... vs 0976... vs 251976...)
        $digits = preg_replace('/[^0-9]/', '', $query);
        $phoneVariants = [$query];

        if (!empty($digits)) {
            $phoneVariants[] = $digits;
            if (str_starts_with($digits, '251') && strlen($digits) >= 4) {
                $phoneVariants[] = '0' . substr($digits, 3);
            } elseif ((str_starts_with($digits, '09') || str_starts_with($digits, '07')) && strlen($digits) >= 3) {
                $phoneVariants[] = '+251' . substr($digits, 1);
                $phoneVariants[] = '251' . substr($digits, 1);
            }
        }

        $currentMember = $request->user()?->member;

        $members = Member::query()
            ->with('user')
            ->where(function ($q) use ($query, $phoneVariants) {
                $q->whereHas('user', function ($userQuery) use ($query, $phoneVariants) {
                    $userQuery->where(function ($pQ) use ($phoneVariants) {
                        foreach ($phoneVariants as $variant) {
                            $pQ->orWhere('phone', 'like', "%{$variant}%");
                        }
                    })->orWhere('name', 'like', "%{$query}%");
                })->orWhere('full_name', 'like', "%{$query}%");
            })
            ->when($currentMember, fn ($q) => $q->where('id', '!=', $currentMember->id))
            ->limit(15)
            ->get();

        return response()->json(['data' => $members]);
    }
}
