<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CommitteeRole;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CommitteeRoleController extends Controller
{
    public function index(Request $request)
    {
        $query = CommitteeRole::query()->orderBy('sort_order')->orderBy('name');

        if ($request->boolean('active_only')) {
            $query->where('is_active', true);
        }

        return response()->json([
            'data' => $query->get()->map(fn (CommitteeRole $r) => $this->transform($r)),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'code' => ['nullable', 'string', 'max:80', 'unique:committee_roles,code'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $code = $data['code'] ?? Str::slug($data['name'], '_');
        if ($code === '') {
            $code = 'role_'.Str::lower(Str::random(6));
        }

        $role = CommitteeRole::query()->create([
            'name' => $data['name'],
            'code' => $code,
            'is_active' => $data['is_active'] ?? true,
            'sort_order' => $data['sort_order'] ?? ((int) CommitteeRole::query()->max('sort_order') + 1),
        ]);

        return response()->json($this->transform($role), 201);
    }

    public function update(Request $request, CommitteeRole $committeeRole)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'code' => [
                'nullable',
                'string',
                'max:80',
                Rule::unique('committee_roles', 'code')->ignore($committeeRole->id),
            ],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $committeeRole->update([
            'name' => $data['name'],
            'code' => $data['code'] ?? $committeeRole->code,
            'is_active' => $data['is_active'],
            'sort_order' => $data['sort_order'] ?? $committeeRole->sort_order,
        ]);

        return response()->json($this->transform($committeeRole->fresh()));
    }

    public function destroy(CommitteeRole $committeeRole)
    {
        if ($committeeRole->members()->exists()) {
            $committeeRole->update(['is_active' => false]);

            return response()->json([
                'message' => 'Role is in use, marked inactive.',
                'data' => $this->transform($committeeRole->fresh()),
            ]);
        }

        $committeeRole->delete();

        return response()->json(['message' => 'Deleted']);
    }

    private function transform(CommitteeRole $r): array
    {
        return [
            'id' => (string) $r->id,
            'name' => $r->name,
            'code' => $r->code,
            'sort_order' => $r->sort_order,
            'is_active' => $r->is_active,
        ];
    }
}
