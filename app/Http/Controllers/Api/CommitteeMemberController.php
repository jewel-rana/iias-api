<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CommitteeMember;
use Illuminate\Http\Request;

class CommitteeMemberController extends Controller
{
    public function index(Request $request)
    {
        $query = CommitteeMember::query()
            ->with('role')
            ->orderBy('sort_order')
            ->orderBy('name');

        if ($request->boolean('active_only', true)) {
            $query->where('is_active', true);
        }

        if ($request->filled('role_id')) {
            $query->where('committee_role_id', $request->integer('role_id'));
        }

        return response()->json([
            'data' => $query->get()->map(fn (CommitteeMember $m) => $this->transform($m)),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:120'],
            'committee_role_id' => ['required', 'exists:committee_roles,id'],
            'member_id' => ['nullable', 'exists:members,id'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $member = CommitteeMember::query()->create([
            ...$data,
            'is_active' => $data['is_active'] ?? true,
            'sort_order' => $data['sort_order'] ?? ((int) CommitteeMember::query()->max('sort_order') + 1),
        ]);

        return response()->json($this->transform($member->load('role')), 201);
    }

    public function update(Request $request, CommitteeMember $committeeMember)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:120'],
            'committee_role_id' => ['required', 'exists:committee_roles,id'],
            'member_id' => ['nullable', 'exists:members,id'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $committeeMember->update($data);

        return response()->json($this->transform($committeeMember->fresh()->load('role')));
    }

    public function destroy(CommitteeMember $committeeMember)
    {
        $committeeMember->delete();

        return response()->json(['message' => 'Deleted']);
    }

    private function transform(CommitteeMember $m): array
    {
        return [
            'id' => (string) $m->id,
            'name' => $m->name,
            'phone' => $m->phone,
            'email' => $m->email,
            'committee_role_id' => (string) $m->committee_role_id,
            'role_name' => $m->role?->name,
            'role_code' => $m->role?->code,
            'member_id' => $m->member_id ? (string) $m->member_id : null,
            'notes' => $m->notes,
            'is_active' => $m->is_active,
            'sort_order' => $m->sort_order,
        ];
    }
}
