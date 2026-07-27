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
        try {
            $query = trim((string) $request->get('query', $request->get('q', '')));

            if ($query === '') {
                return response()->json(['success' => true, 'data' => []], 200);
            }

            $currentMember = $request->user()?->member;


            $members = Member::query()
                ->with('user')
                ->where(function ($q) use ($query) {
                    $q->whereHas('user', function ($userQuery) use ($query) {
                        $userQuery->where('phone', 'like', "%{$query}%")
                            ->orWhere('name', 'like', "%{$query}%");
                    })->orWhere('full_name', 'like', "%{$query}%");
                })
                ->when($currentMember, fn ($q) => $q->where('id', '!=', $currentMember->id))
                ->limit(15)
                ->get();
                ->map(fn ($member) => [
                    'id'        => $member->id,
                    'full_name' => $member->full_name,
                    'phone'     => $member->user?->phone,
                ]);

            return response()->json(['success' => true, 'data' => $members], 200);
        } catch (\Throwable $e) {
            Log::error('Search Members Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
